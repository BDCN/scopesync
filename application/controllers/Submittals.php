<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Submittals extends MY_Controller {

    const PLAN_LIMITS = ['starter' => 25, 'pro' => 100, 'team' => 400];
    const MAX_BYTES   = 52428800; // 50 MB

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        $this->load->model([
            'Submittal_model',
            'Division_model',
            'Project_model',
            'Document_model',
            'Extraction_model',
            'Tenant_model',
            'Match_result_model',
            'Review_decision_model',
        ]);
    }

    // -------------------------------------------------------------------------
    // Create — POST from project view modal
    // -------------------------------------------------------------------------

    public function create(int $divisionId)
    {
        $division = $this->Division_model->getByIdAndTenant($divisionId, $this->tenantcontext->id());
        if ( ! $division) {
            show_404();
        }

        if ($this->input->method() !== 'post') {
            redirect('projects/' . $division['project_id']);
            return;
        }

        $this->form_validation->set_rules('name',             'Submittal Name',   'required|trim|max_length[255]');
        $this->form_validation->set_rules('submittal_number', 'Submittal Number', 'trim|max_length[64]');
        $this->form_validation->set_rules('spec_section',     'Spec Section',     'trim|max_length[64]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', implode(' ', $this->form_validation->error_array()));
            redirect('projects/' . $division['project_id']);
            return;
        }

        $id = $this->Submittal_model->create([
            'tenant_id'        => $this->tenantcontext->id(),
            'project_id'       => (int) $division['project_id'],
            'division_id'      => $divisionId,
            'name'             => $this->input->post('name', TRUE),
            'submittal_number' => $this->input->post('submittal_number', TRUE) ?: NULL,
            'spec_section'     => $this->input->post('spec_section', TRUE) ?: NULL,
            'status'           => 'draft',
            'created_by'       => $this->tenantcontext->userId(),
        ]);

        $this->auditlog->log('submittal', 'create', $id, [
            'division_id' => $divisionId,
            'name'        => $this->input->post('name', TRUE),
        ]);

        $this->session->set_flashdata('success', 'Submittal job created.');
        redirect('submittals/' . $id);
    }

    // -------------------------------------------------------------------------
    // View — submittal detail with upload UI and extraction results
    // -------------------------------------------------------------------------

    public function view(int $id)
    {
        $submittal = $this->Submittal_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $submittal) {
            show_404();
        }

        $division = $this->Division_model->getByIdAndTenant(
            (int) $submittal['division_id'],
            $this->tenantcontext->id()
        );

        $project = $this->Project_model->getByIdAndTenant(
            (int) $submittal['project_id'],
            $this->tenantcontext->id()
        );

        $documents = $this->Document_model->getBySubmittal($id, $this->tenantcontext->id());

        // Map document_id → latest extraction
        $extractionsByDoc = [];
        foreach ($documents as $doc) {
            $rows = $this->Extraction_model->getByDocument((int) $doc['id']);
            $extractionsByDoc[(int) $doc['id']] = $rows ? $rows[0] : NULL;
        }

        $this->loadView('submittals/view', [
            'page_title'         => htmlspecialchars($submittal['name']),
            'submittal'          => $submittal,
            'division'           => $division,
            'project'            => $project,
            'documents'          => $documents,
            'extractions_by_doc' => $extractionsByDoc,
            'csrf_token_name'    => $this->security->get_csrf_token_name(),
            'csrf_hash'          => $this->security->get_csrf_hash(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Upload — XHR POST, returns JSON
    // -------------------------------------------------------------------------

    public function upload(int $submittalId)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->output->set_content_type('application/json');

        $submittal = $this->Submittal_model->getByIdAndTenant($submittalId, $this->tenantcontext->id());
        if ( ! $submittal) {
            echo json_encode(['success' => FALSE, 'error' => 'Submittal not found.']);
            return;
        }

        $docType = $this->input->post('doc_type', TRUE);
        if ( ! in_array($docType, ['spec_section', 'cut_sheet'], TRUE)) {
            echo json_encode(['success' => FALSE, 'error' => 'doc_type must be spec_section or cut_sheet.']);
            return;
        }

        // File presence
        if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['file']['error'] ?? -1;
            echo json_encode(['success' => FALSE, 'error' => $this->_uploadErrMsg($errCode)]);
            return;
        }

        $tmpPath  = $_FILES['file']['tmp_name'];
        $origName = $this->_sanitiseFilename($_FILES['file']['name']);
        $fileSize = (int) $_FILES['file']['size'];

        // Size check (also validated client-side, but enforce server-side)
        if ($fileSize > self::MAX_BYTES) {
            echo json_encode(['success' => FALSE, 'error' => 'File exceeds the 50 MB limit.']);
            return;
        }

        // MIME check via finfo — not just extension
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);
        if ($mimeType !== 'application/pdf') {
            echo json_encode(['success' => FALSE, 'error' => 'Only PDF files are accepted (detected: ' . htmlspecialchars($mimeType) . ').']);
            return;
        }

        // SHA-256 dedup within tenant
        $sha256   = hash_file('sha256', $tmpPath);
        $existing = $this->Document_model->findBySha256($this->tenantcontext->id(), $sha256);
        if ($existing) {
            echo json_encode(['success' => FALSE, 'error' => 'This exact file has already been uploaded (duplicate detected).']);
            return;
        }

        // Plan upload limit
        $tenant = $this->Tenant_model->getById($this->tenantcontext->id());
        $plan   = $tenant['plan'] ?? 'starter';
        $limit  = self::PLAN_LIMITS[$plan] ?? 25;
        $used   = $this->Document_model->countThisMonth($this->tenantcontext->id());
        if ($used >= $limit) {
            echo json_encode(['success' => FALSE, 'error' => "Monthly upload limit ({$limit} files) reached on the {$plan} plan."]);
            return;
        }

        $tid = $this->tenantcontext->id();
        $pid = (int) $submittal['project_id'];
        $sid = $submittalId;

        // Insert documents row first (storage_path filled after we know the ID)
        $docId = $this->Document_model->create([
            'tenant_id'         => $tid,
            'submittal_job_id'  => $sid,
            'doc_type'          => $docType,
            'original_filename' => $origName,
            'storage_path'      => '',
            'mime_type'         => $mimeType,
            'size_bytes'        => $fileSize,
            'sha256'            => $sha256,
            'uploaded_by'       => $this->tenantcontext->userId(),
        ]);

        // Build storage path using document ID and move the file
        $subDir  = ($docType === 'spec_section') ? 'spec' : 'cutsheets';
        $relPath = "tenants/{$tid}/projects/{$pid}/submittals/{$sid}/input/{$subDir}/{$docId}.pdf";
        $absDir  = APPPATH . "../storage/tenants/{$tid}/projects/{$pid}/submittals/{$sid}/input/{$subDir}";
        $absPath = "{$absDir}/{$docId}.pdf";

        if ( ! is_dir($absDir) && ! mkdir($absDir, 0775, TRUE)) {
            $this->Document_model->delete($docId);
            echo json_encode(['success' => FALSE, 'error' => 'Could not create storage directory. Check server permissions.']);
            return;
        }

        if ( ! move_uploaded_file($tmpPath, $absPath)) {
            $this->Document_model->delete($docId);
            echo json_encode(['success' => FALSE, 'error' => 'Failed to store file. Check server permissions on storage/.']);
            return;
        }

        $this->Document_model->update($docId, ['storage_path' => $relPath]);

        // Create extractions row
        $extractionId = $this->Extraction_model->create([
            'tenant_id'        => $tid,
            'submittal_job_id' => $sid,
            'document_id'      => $docId,
            'extraction_type'  => $docType, // spec_section | cut_sheet
            'status'           => 'pending',
        ]);

        // Bump submittal status from draft to uploading
        if ($submittal['status'] === 'draft') {
            $this->Submittal_model->update($sid, $tid, ['status' => 'uploading']);
        }

        $this->auditlog->log('document', 'upload', $docId, [
            'submittal_id'  => $sid,
            'doc_type'      => $docType,
            'filename'      => $origName,
            'size_bytes'    => $fileSize,
            'extraction_id' => $extractionId,
        ]);

        // Return new CSRF hash so subsequent XHR uploads from same page session work
        echo json_encode([
            'success'        => TRUE,
            'document_id'    => $docId,
            'extraction_id'  => $extractionId,
            'doc_type'       => $docType,
            'filename'       => $origName,
            'size_bytes'     => $fileSize,
            'csrf_hash'      => $this->security->get_csrf_hash(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Compliance matrix — read-only table view of all match results
    // -------------------------------------------------------------------------

    public function compliance(int $id)
    {
        $submittal = $this->Submittal_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $submittal) {
            show_404();
        }

        $division = $this->Division_model->getByIdAndTenant(
            (int) $submittal['division_id'],
            $this->tenantcontext->id()
        );
        $project = $this->Project_model->getByIdAndTenant(
            (int) $submittal['project_id'],
            $this->tenantcontext->id()
        );

        $matchResults = $this->Match_result_model->getBySubmittal($id, $this->tenantcontext->id());

        // Build matrix: categories[category]['attrs'][attr_name] = []
        //                              ['catalogs'][cat_num] = overall_result
        //                              ['cells'][attr_name][cat_num] = cell
        $matrix      = [];
        $allCatalogs = []; // ordered list per category

        foreach ($matchResults as $mr) {
            $cat    = $mr['product_category'] ?: 'General';
            $catNum = $mr['catalog_number'];

            if ( ! isset($matrix[$cat])) {
                $matrix[$cat]      = ['attrs' => [], 'catalogs' => [], 'cells' => [], 'overall' => []];
                $allCatalogs[$cat] = [];
            }

            $matrix[$cat]['catalogs'][$catNum]  = $catNum;
            $matrix[$cat]['overall'][$catNum]   = $mr['overall_result'];
            $allCatalogs[$cat][$catNum]         = $catNum;

            $decoded     = json_decode($mr['attribute_results'], TRUE);
            $attrResults = $decoded['attribute_results'] ?? [];

            foreach ($attrResults as $ar) {
                $attrName = $ar['attribute'];
                if ( ! isset($matrix[$cat]['attrs'][$attrName])) {
                    $matrix[$cat]['attrs'][$attrName] = $attrName;
                }
                $matrix[$cat]['cells'][$attrName][$catNum] = $ar;
            }

            // Also record listing results per catalog
            $listingResults = $decoded['listing_results'] ?? [];
            foreach ($listingResults as $lr) {
                $key = '_listing_' . $lr['required_listing'];
                if ( ! isset($matrix[$cat]['attrs'][$key])) {
                    $matrix[$cat]['attrs'][$key] = $lr['required_listing'] . ' (listing)';
                }
                $matrix[$cat]['cells'][$key][$catNum] = [
                    'result'        => $lr['result'],
                    'spec_value'    => $lr['required_listing'],
                    'product_value' => $lr['matched_listing'],
                ];
            }
        }

        $hasResults = ! empty($matchResults);

        $this->loadView('submittals/compliance', [
            'page_title'  => 'Compliance Matrix — ' . htmlspecialchars($submittal['name']),
            'submittal'   => $submittal,
            'division'    => $division,
            'project'     => $project,
            'matrix'      => $matrix,
            'has_results' => $hasResults,
        ]);
    }

    // -------------------------------------------------------------------------
    // Review queue — approve / override / reject per match result
    // -------------------------------------------------------------------------

    public function review(int $id)
    {
        $submittal = $this->Submittal_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $submittal) {
            show_404();
        }

        $division = $this->Division_model->getByIdAndTenant(
            (int) $submittal['division_id'],
            $this->tenantcontext->id()
        );
        $project = $this->Project_model->getByIdAndTenant(
            (int) $submittal['project_id'],
            $this->tenantcontext->id()
        );

        $matchResults    = $this->Match_result_model->getBySubmittal($id, $this->tenantcontext->id());
        $decisionMap     = $this->Review_decision_model->mapByMatchResult($id, $this->tenantcontext->id());
        $allDecided      = $this->Review_decision_model->allDecided($id, $this->tenantcontext->id());
        $hasRejections   = $this->Review_decision_model->hasRejections($id, $this->tenantcontext->id());

        // Decode attribute_results for each match result
        $decoded = [];
        foreach ($matchResults as $mr) {
            $decoded[$mr['id']] = json_decode($mr['attribute_results'], TRUE);
        }

        $this->loadView('submittals/review', [
            'page_title'    => 'Review — ' . htmlspecialchars($submittal['name']),
            'submittal'     => $submittal,
            'division'      => $division,
            'project'       => $project,
            'match_results' => $matchResults,
            'decoded'       => $decoded,
            'decision_map'  => $decisionMap,
            'all_decided'   => $allDecided,
            'has_rejections'=> $hasRejections,
            'csrf_token_name' => $this->security->get_csrf_token_name(),
            'csrf_hash'     => $this->security->get_csrf_hash(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Decide — POST, XHR, save a review decision for one match result
    // -------------------------------------------------------------------------

    public function decide(int $submittalId)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->output->set_content_type('application/json');

        $submittal = $this->Submittal_model->getByIdAndTenant($submittalId, $this->tenantcontext->id());
        if ( ! $submittal) {
            echo json_encode(['success' => FALSE, 'error' => 'Submittal not found.']);
            return;
        }

        $matchResultId  = (int) $this->input->post('match_result_id', TRUE);
        $decision       = $this->input->post('decision', TRUE);
        $overrideNotes  = $this->input->post('override_notes', TRUE) ?: null;

        if ( ! in_array($decision, ['approved', 'overridden', 'rejected'], TRUE)) {
            echo json_encode(['success' => FALSE, 'error' => 'Invalid decision value.']);
            return;
        }

        $matchResult = $this->Match_result_model->getByIdAndTenant($matchResultId, $this->tenantcontext->id());
        if ( ! $matchResult || (int) $matchResult['submittal_job_id'] !== $submittalId) {
            echo json_encode(['success' => FALSE, 'error' => 'Match result not found.']);
            return;
        }

        if ($decision === 'overridden' && empty($overrideNotes)) {
            echo json_encode(['success' => FALSE, 'error' => 'Override justification is required.']);
            return;
        }

        $this->Review_decision_model->save([
            'tenant_id'        => $this->tenantcontext->id(),
            'submittal_job_id' => $submittalId,
            'match_result_id'  => $matchResultId,
            'decision'         => $decision,
            'override_notes'   => $overrideNotes,
            'decided_by'       => $this->tenantcontext->userId(),
        ]);

        $this->auditlog->log('match_result', $decision, $matchResultId, [
            'submittal_id'   => $submittalId,
            'catalog_number' => $matchResult['catalog_number'],
            'override_notes' => $overrideNotes,
        ]);

        // If all decided, advance submittal status
        $allDecided    = $this->Review_decision_model->allDecided($submittalId, $this->tenantcontext->id());
        $hasRejections = $this->Review_decision_model->hasRejections($submittalId, $this->tenantcontext->id());
        $newStatus     = null;

        if ($allDecided) {
            $newStatus = $hasRejections ? 'review' : 'assembling';
            $this->Submittal_model->update($submittalId, $this->tenantcontext->id(), ['status' => $newStatus]);
        }

        echo json_encode([
            'success'    => TRUE,
            'all_decided'=> $allDecided,
            'new_status' => $newStatus,
            'csrf_hash'  => $this->security->get_csrf_hash(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Assemble — POST; triggers PDF generation for an assembling submittal
    // -------------------------------------------------------------------------

    public function assemble(int $id)
    {
        if ($this->input->method() !== 'post') {
            redirect('submittals/' . $id);
            return;
        }

        $submittal = $this->Submittal_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $submittal) {
            show_404();
        }

        if ($submittal['status'] !== 'assembling') {
            $this->session->set_flashdata('error', 'Submittal must be in "assembling" status to generate a package.');
            redirect('submittals/' . $id);
            return;
        }

        $this->load->library('SubmittalAssembler');

        try {
            $outputPath = $this->submittalassembler->build($id, $this->tenantcontext->id());

            // Store relative path (strip APPPATH prefix so it's portable)
            $storagePath = ltrim(str_replace(realpath(APPPATH . '..') . DIRECTORY_SEPARATOR, '', realpath($outputPath)), '/\\');
            $storagePath = str_replace('\\', '/', $storagePath);

            $this->Submittal_model->update($id, $this->tenantcontext->id(), [
                'status'      => 'complete',
                'output_path' => $storagePath,
                'assembled_at'=> date('Y-m-d H:i:s'),
            ]);

            $this->auditlog->log('submittal', 'package_generated', $id, [
                'output_path' => $storagePath,
            ]);

            $this->session->set_flashdata('success', 'Submittal package generated successfully.');
        } catch (Exception $e) {
            log_message('error', 'SubmittalAssembler failed for submittal ' . $id . ': ' . $e->getMessage());
            $this->session->set_flashdata('error', 'PDF generation failed: ' . htmlspecialchars($e->getMessage()));
        }

        redirect('submittals/' . $id);
    }

    // -------------------------------------------------------------------------
    // Download — GET; streams the generated package PDF to browser
    // -------------------------------------------------------------------------

    public function download(int $id)
    {
        $submittal = $this->Submittal_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $submittal || empty($submittal['output_path'])) {
            show_404();
        }

        $absPath = realpath(APPPATH . '../' . ltrim($submittal['output_path'], '/'));
        if ( ! $absPath || ! is_file($absPath)) {
            $this->session->set_flashdata('error', 'Package file not found. Please regenerate.');
            redirect('submittals/' . $id);
            return;
        }

        $filename = 'submittal-' . $id . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($absPath));
        header('Cache-Control: private, no-cache');

        readfile($absPath);
        exit;
    }

    // -------------------------------------------------------------------------
    // Re-run — POST, returns JSON
    // -------------------------------------------------------------------------

    public function rerun(int $extractionId)
    {
        if ($this->input->method() !== 'post') {
            show_404();
        }

        $this->output->set_content_type('application/json');

        $extraction = $this->Extraction_model->getByIdAndTenant($extractionId, $this->tenantcontext->id());
        if ( ! $extraction) {
            echo json_encode(['success' => FALSE, 'error' => 'Extraction not found.']);
            return;
        }

        $newId = $this->Extraction_model->create([
            'tenant_id'        => $extraction['tenant_id'],
            'submittal_job_id' => $extraction['submittal_job_id'],
            'document_id'      => $extraction['document_id'],
            'extraction_type'  => $extraction['extraction_type'],
            'status'           => 'pending',
        ]);

        $this->auditlog->log('extraction', 'requeue', $newId, [
            'original_extraction_id' => $extractionId,
            'document_id'            => $extraction['document_id'],
        ]);

        echo json_encode([
            'success'        => TRUE,
            'extraction_id'  => $newId,
            'csrf_hash'      => $this->security->get_csrf_hash(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function _sanitiseFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\-]/', '_', $name);
        return substr($name, 0, 255) ?: 'upload.pdf';
    }

    protected function _uploadErrMsg(int $code): string
    {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        return $msgs[$code] ?? 'Unknown upload error.';
    }
}
