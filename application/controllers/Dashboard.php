<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        $this->load->model('Project_model');
    }

    public function index()
    {
        $projects = $this->Project_model->getByTenant($this->tenantcontext->id());

        $this->loadView('dashboard/index', [
            'page_title' => 'Dashboard',
            'projects'   => $projects,
        ]);
    }
}
