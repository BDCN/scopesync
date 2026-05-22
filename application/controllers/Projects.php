<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Projects extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        $this->load->model(['Project_model', 'Division_model', 'Submittal_model']);
    }

    // -------------------------------------------------------------------------
    // Index — list all active projects
    // -------------------------------------------------------------------------

    public function index()
    {
        $projects = $this->Project_model->getByTenant($this->tenantcontext->id());

        $this->loadView('projects/index', [
            'page_title' => 'Projects',
            'projects'   => $projects,
        ]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create()
    {
        if ($this->input->method() === 'post') {
            $this->_processCreate();
            return;
        }

        $this->loadView('projects/create', ['page_title' => 'New Project']);
    }

    private function _processCreate()
    {
        $this->form_validation->set_rules('name',           'Project Name',    'required|trim|max_length[255]');
        $this->form_validation->set_rules('project_number', 'Project Number',  'trim|max_length[64]');
        $this->form_validation->set_rules('gc_name',        'General Contractor', 'trim|max_length[255]');
        $this->form_validation->set_rules('architect_name', 'Architect',       'trim|max_length[255]');
        $this->form_validation->set_rules('location',       'Location',        'trim|max_length[255]');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('projects/create', [
                'page_title' => 'New Project',
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $id = $this->Project_model->create([
            'tenant_id'      => $this->tenantcontext->id(),
            'name'           => $this->input->post('name', TRUE),
            'project_number' => $this->input->post('project_number', TRUE) ?: NULL,
            'gc_name'        => $this->input->post('gc_name', TRUE) ?: NULL,
            'architect_name' => $this->input->post('architect_name', TRUE) ?: NULL,
            'location'       => $this->input->post('location', TRUE) ?: NULL,
            'status'         => 'active',
            'created_by'     => $this->tenantcontext->userId(),
        ]);

        $this->auditlog->log('project', 'create', $id, ['name' => $this->input->post('name', TRUE)]);

        $this->session->set_flashdata('success', 'Project created successfully.');
        redirect('projects/' . $id);
    }

    // -------------------------------------------------------------------------
    // View — project detail with divisions and submittals
    // -------------------------------------------------------------------------

    public function view(int $id)
    {
        $project = $this->_getOwnedProject($id);

        $divisions = $this->Division_model->getByProject($id, $this->tenantcontext->id());

        // Attach submittals to each division
        foreach ($divisions as &$div) {
            $div['submittals'] = $this->Submittal_model->getByDivision(
                (int) $div['id'],
                $this->tenantcontext->id()
            );
        }
        unset($div);

        $this->loadView('projects/view', [
            'page_title' => htmlspecialchars($project['name']),
            'project'    => $project,
            'divisions'  => $divisions,
        ]);
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function edit(int $id)
    {
        $project = $this->_getOwnedProject($id);

        if ($this->input->method() === 'post') {
            $this->_processEdit($project);
            return;
        }

        $this->loadView('projects/edit', [
            'page_title' => 'Edit Project',
            'project'    => $project,
        ]);
    }

    private function _processEdit(array $project)
    {
        $this->form_validation->set_rules('name',           'Project Name',       'required|trim|max_length[255]');
        $this->form_validation->set_rules('project_number', 'Project Number',     'trim|max_length[64]');
        $this->form_validation->set_rules('gc_name',        'General Contractor', 'trim|max_length[255]');
        $this->form_validation->set_rules('architect_name', 'Architect',          'trim|max_length[255]');
        $this->form_validation->set_rules('location',       'Location',           'trim|max_length[255]');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('projects/edit', [
                'page_title' => 'Edit Project',
                'project'    => $project,
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $this->Project_model->update((int) $project['id'], $this->tenantcontext->id(), [
            'name'           => $this->input->post('name', TRUE),
            'project_number' => $this->input->post('project_number', TRUE) ?: NULL,
            'gc_name'        => $this->input->post('gc_name', TRUE) ?: NULL,
            'architect_name' => $this->input->post('architect_name', TRUE) ?: NULL,
            'location'       => $this->input->post('location', TRUE) ?: NULL,
        ]);

        $this->auditlog->log('project', 'edit', (int) $project['id']);

        $this->session->set_flashdata('success', 'Project updated successfully.');
        redirect('projects/' . $project['id']);
    }

    // -------------------------------------------------------------------------
    // Archive
    // -------------------------------------------------------------------------

    public function archive(int $id)
    {
        $project = $this->_getOwnedProject($id);

        if ($this->input->method() !== 'post') {
            redirect('projects/' . $id);
            return;
        }

        $this->Project_model->archive($id, $this->tenantcontext->id());
        $this->auditlog->log('project', 'archive', $id);

        $this->session->set_flashdata('success', 'Project archived.');
        redirect('projects');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _getOwnedProject(int $id): array
    {
        $project = $this->Project_model->getByIdAndTenant($id, $this->tenantcontext->id());
        if ( ! $project) {
            show_404();
        }
        return $project;
    }
}
