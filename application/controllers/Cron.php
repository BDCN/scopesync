<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        if ( ! $this->input->is_cli_request()) {
            show_404();
        }
        $this->load->model(['Extraction_model', 'Document_model', 'Submittal_model', 'Match_result_model']);
        $this->load->library(['ClaudeClient', 'MatchingEngine']);
    }

    // -------------------------------------------------------------------------
    // process — invoked by scripts/worker.php every minute
    // -------------------------------------------------------------------------

    public function process()
    {
        $pending = $this->Extraction_model->getPending(5);

        if (empty($pending)) {
            $this->_log('No pending extractions.');
            return;
        }

        $this->_log('Found ' . count($pending) . ' pending extraction(s).');

        foreach ($pending as $extraction) {
            if ( ! $this->Extraction_model->claimById((int) $extraction['id'])) {
                $this->_log("Extraction #{$extraction['id']} already claimed — skipping.");
                continue;
            }

            $this->_log("Processing extraction #{$extraction['id']} (document #{$extraction['document_id']}, type: {$extraction['extraction_type']}).");
            $this->_processExtraction($extraction);
        }

        $this->_log('Worker run complete.');
    }

    // -------------------------------------------------------------------------

    protected function _processExtraction(array $extraction)
    {
        $doc = $this->Document_model->getByIdRaw((int) $extraction['document_id']);
        if ( ! $doc) {
            $this->Extraction_model->markFailed((int) $extraction['id'], 'Document record not found in DB.');
            $this->_log("Extraction #{$extraction['id']} failed: document not found.");
            return;
        }

        $storagePath = APPPATH . '../storage/' . $doc['storage_path'];
        if ( ! file_exists($storagePath)) {
            $this->Extraction_model->markFailed((int) $extraction['id'], "File not found at storage path: {$doc['storage_path']}");
            $this->_log("Extraction #{$extraction['id']} failed: file missing at {$storagePath}.");
            return;
        }

        // Gather context for template substitution
        $submittal = $this->Submittal_model->getByIdRaw((int) $extraction['submittal_job_id']);
        $project   = NULL;
        $industry  = 'electrical';
        if ($submittal) {
            $project  = $this->db->get_where('projects', ['id' => $submittal['project_id']])->row_array();
            $industry = $project
                ? ($this->db->get_where('industries', ['id' => $project['industry_id']])->row_array()['slug'] ?? 'electrical')
                : 'electrical';
        }

        $promptName = ($extraction['extraction_type'] === 'spec_section')
            ? 'spec_section_extraction'
            : 'cut_sheet_extraction';

        $options = [
            'industry'                  => $industry,
            'submittal_name'            => $submittal['name']         ?? '',
            'expected_division'         => $submittal['spec_section'] ?? '',
            'expected_product_category' => '',
        ];

        $this->_log("Calling Claude API for extraction #{$extraction['id']} using prompt '{$promptName}'...");
        $result = $this->claudeclient->extract($promptName, $storagePath, $options);

        if ($result['status'] === 'completed') {
            $this->Extraction_model->markComplete((int) $extraction['id'], [
                'model_used'      => $result['model'],
                'prompt_version'  => $result['prompt_version'],
                'input_tokens'    => $result['input_tokens'],
                'output_tokens'   => $result['output_tokens'],
                'raw_response'    => $result['raw_response'],
                'structured_data' => $result['structured_data'],
                'confidence'      => $result['confidence'],
            ]);

            $this->auditlog->log(
                'extraction', 'complete', (int) $extraction['id'],
                [
                    'document_id'   => $extraction['document_id'],
                    'model'         => $result['model'],
                    'input_tokens'  => $result['input_tokens'],
                    'output_tokens' => $result['output_tokens'],
                    'confidence'    => $result['confidence'],
                ],
                (int) $extraction['tenant_id']
            );

            $this->_log("Extraction #{$extraction['id']} completed. in={$result['input_tokens']} out={$result['output_tokens']} tokens, confidence={$result['confidence']}.");

        } else {
            $this->Extraction_model->markFailed((int) $extraction['id'], $result['error']);

            $this->auditlog->log(
                'extraction', 'failed', (int) $extraction['id'],
                ['error' => $result['error']],
                (int) $extraction['tenant_id']
            );

            $this->_log("Extraction #{$extraction['id']} failed: {$result['error']}");
        }

        // Update submittal status based on outstanding extractions
        if ($submittal) {
            $this->_syncSubmittalStatus((int) $extraction['submittal_job_id'], (int) $extraction['tenant_id']);
        }
    }

    protected function _syncSubmittalStatus(int $submittalId, int $tenantId)
    {
        $extractions = $this->Extraction_model->getBySubmittal($submittalId, $tenantId);
        if (empty($extractions)) {
            return;
        }

        $statuses = array_column($extractions, 'status');

        if (in_array('pending', $statuses, TRUE) || in_array('running', $statuses, TRUE)) {
            $newStatus = 'extracting';
        } elseif (in_array('failed', $statuses, TRUE) && ! in_array('completed', $statuses, TRUE)) {
            $newStatus = 'failed';
        } else {
            $newStatus = 'review'; // at least some completed — ready for human review
        }

        $this->Submittal_model->update($submittalId, $tenantId, ['status' => $newStatus]);

        if ($newStatus === 'review') {
            $this->_triggerMatching($submittalId, $tenantId, $extractions);
        }
    }

    protected function _triggerMatching(int $submittalId, int $tenantId, array $extractions)
    {
        // Guard: skip if matching already ran for this submittal
        // Atomic claim: only the first caller that flips matching_status from NULL → running proceeds.
        $this->db->where(['id' => $submittalId, 'tenant_id' => $tenantId, 'matching_status' => null])
                 ->update('submittal_jobs', ['matching_status' => 'running']);
        if ($this->db->affected_rows() === 0) {
            $this->_log("Matching for submittal #{$submittalId} already claimed or complete — skipping.");
            return;
        }

        // Partition completed extractions by type
        $specExtrs = [];
        $csExtrs   = [];
        foreach ($extractions as $e) {
            if ($e['status'] !== 'completed' || empty($e['structured_data'])) {
                continue;
            }
            if ($e['extraction_type'] === 'spec_section') {
                $specExtrs[] = $e;
            } elseif ($e['extraction_type'] === 'cut_sheet') {
                $csExtrs[] = $e;
            }
        }

        if (empty($specExtrs) || empty($csExtrs)) {
            // Not enough completed extractions yet — release the claim so a future run can retry
            $this->Submittal_model->update($submittalId, $tenantId, ['matching_status' => null]);
            $this->_log("Submittal #{$submittalId}: skipping matching — need both spec and cut sheet extractions (spec=" . count($specExtrs) . ", cutsheets=" . count($csExtrs) . ").");
            return;
        }

        $this->_log("Running matching engine for submittal #{$submittalId} (" . count($specExtrs) . " spec, " . count($csExtrs) . " cut sheet)...");

        try {
            $totalResults = 0;
            foreach ($specExtrs as $specExtr) {
                $results = $this->matchingengine->run($specExtr, $csExtrs);
                foreach ($results as $result) {
                    $this->Match_result_model->create($result);
                    $totalResults++;
                }
            }

            $this->Submittal_model->update($submittalId, $tenantId, ['matching_status' => 'complete']);

            $this->auditlog->log(
                'submittal', 'matching_complete', $submittalId,
                ['match_results_count' => $totalResults],
                $tenantId
            );

            $this->_log("Matching complete for submittal #{$submittalId}: {$totalResults} result(s).");

        } catch (Exception $e) {
            $this->Submittal_model->update($submittalId, $tenantId, ['matching_status' => 'failed']);
            $this->_log("Matching failed for submittal #{$submittalId}: " . $e->getMessage());
        }
    }

    protected function _log(string $msg)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    }
}
