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
