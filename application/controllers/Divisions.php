<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Divisions extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        $this->load->model(['Division_model', 'Project_model']);
    }

    public function create(int $projectId)
    {
        $project = $this->Project_model->getByIdAndTenant($projectId, $this->tenantcontext->id());
        if ( ! $project) {
            show_404();
        }

        if ($this->input->method() !== 'post') {
            redirect('projects/' . $projectId);
            return;
        }

        $this->form_validation->set_rules('code', 'Division Code', 'required|trim|max_length[16]');
        $this->form_validation->set_rules('name', 'Division Name', 'required|trim|max_length[255]');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', implode(' ', $this->form_validation->error_array()));
            redirect('projects/' . $projectId);
            return;
        }

        $code = $this->input->post('code', TRUE);
        $name = $this->input->post('name', TRUE);

        if ($this->Division_model->codeExistsInProject($code, $projectId)) {
            $this->session->set_flashdata('error', 'Division code "' . htmlspecialchars($code) . '" already exists in this project.');
            redirect('projects/' . $projectId);
            return;
        }

        $id = $this->Division_model->create([
            'tenant_id'  => $this->tenantcontext->id(),
            'project_id' => $projectId,
            'code'       => $code,
            'name'       => $name,
        ]);

        $this->auditlog->log('division', 'create', $id, ['project_id' => $projectId, 'code' => $code]);

        $this->session->set_flashdata('success', 'Division "' . htmlspecialchars($code) . ' — ' . htmlspecialchars($name) . '" added.');
        redirect('projects/' . $projectId);
    }

    public function deleteDivision(int $id)
    {
        if ($this->input->method() !== 'post') {
            redirect('projects');
            return;
        }

        $division = $this->Division_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $division) {
            show_404();
        }

        $this->Division_model->delete($id, $this->tenantcontext->id());
        $this->auditlog->log('division', 'delete', $id, ['code' => $division['code']]);

        $this->session->set_flashdata('success', 'Division deleted.');
        redirect('projects/' . $division['project_id']);
    }
}
