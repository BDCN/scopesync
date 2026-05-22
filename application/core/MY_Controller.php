<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    protected function requireLogin()
    {
        if ( ! $this->tenantcontext->isLoggedIn()) {
            $this->session->set_flashdata('error', 'Please log in to continue.');
            redirect('login');
        }
    }

    protected function loadView($view, array $data = [])
    {
        $data['content'] = $this->load->view($view, $data, TRUE);
        $this->load->view('layouts/main', $data);
    }
}
