<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Submittals extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        $this->load->model(['Submittal_model', 'Division_model', 'Project_model']);
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
    // View — submittal detail (stub for Phase 3 upload/extraction UI)
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

        $this->loadView('submittals/view', [
            'page_title' => htmlspecialchars($submittal['name']),
            'submittal'  => $submittal,
            'division'   => $division,
            'project'    => $project,
        ]);
    }
}
