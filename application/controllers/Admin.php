<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->requireLogin();
        // Restrict to owner/admin roles
        if ( ! in_array($this->tenantcontext->userRole(), ['owner', 'admin'], TRUE)) {
            show_404();
        }
        $this->load->model('Extraction_model');
    }

    // -------------------------------------------------------------------------
    // Extractions — recent extraction log with token costs
    // -------------------------------------------------------------------------

    public function extractions()
    {
        $extractions = $this->Extraction_model->recentForAdmin(100);

        $totalInputTokens  = 0;
        $totalOutputTokens = 0;
        foreach ($extractions as $e) {
            $totalInputTokens  += (int) ($e['input_tokens']  ?? 0);
            $totalOutputTokens += (int) ($e['output_tokens'] ?? 0);
        }

        $this->loadView('admin/extractions', [
            'page_title'          => 'Extraction Log',
            'extractions'         => $extractions,
            'total_input_tokens'  => $totalInputTokens,
            'total_output_tokens' => $totalOutputTokens,
        ]);
    }
}
