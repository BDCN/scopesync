<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        if ( ! $this->input->is_cli_request()) {
            show_404();
        }
        $this->load->model(['Extraction_model', 'Document_model', 'Submittal_model']);
        $this->load->library('ClaudeClient');
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
    }

    protected function _log(string $msg)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    }
}
