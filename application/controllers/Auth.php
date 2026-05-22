<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model(['User_model', 'Tenant_model']);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function login()
    {
        if ($this->tenantcontext->isLoggedIn()) {
            redirect('');
        }

        if ($this->input->method() === 'post') {
            $this->_processLogin();
            return;
        }

        $this->loadView('auth/login', ['page_title' => 'Sign In']);
    }

    private function _processLogin()
    {
        $this->form_validation->set_rules('email',    'Email',    'required|valid_email|trim');
        $this->form_validation->set_rules('password', 'Password', 'required');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('auth/login', [
                'page_title' => 'Sign In',
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $email = $this->input->post('email', TRUE);
        $pass  = $this->input->post('password');

        $user = $this->User_model->getByEmail($email);

        if ( ! $user || ! password_verify($pass, $user['password_hash'])) {
            $this->loadView('auth/login', [
                'page_title' => 'Sign In',
                'error'      => 'Invalid email or password.',
            ]);
            return;
        }

        if ($user['status'] !== 'active') {
            $this->loadView('auth/login', [
                'page_title' => 'Sign In',
                'error'      => 'Your account is not active. Please contact support.',
            ]);
            return;
        }

        $tenant = $this->Tenant_model->getById((int) $user['tenant_id']);

        if ( ! $tenant || $tenant['status'] !== 'active') {
            $this->loadView('auth/login', [
                'page_title' => 'Sign In',
                'error'      => 'Your account is not active. Please contact support.',
            ]);
            return;
        }

        $this->User_model->updateLastLogin((int) $user['id']);
        $this->tenantcontext->setFromUser($user, $tenant);
        $this->auditlog->log('user', 'login', (int) $user['id']);

        redirect('');
    }

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    public function register()
    {
        if ($this->tenantcontext->isLoggedIn()) {
            redirect('');
        }

        if ($this->input->method() === 'post') {
            $this->_processRegister();
            return;
        }

        $this->loadView('auth/register', ['page_title' => 'Create Your Account']);
    }

    private function _processRegister()
    {
        $this->form_validation->set_rules('company_name', 'Company Name', 'required|trim|max_length[255]');
        $this->form_validation->set_rules('your_name',    'Your Name',    'required|trim|max_length[255]');
        $this->form_validation->set_rules('email',        'Email',        'required|valid_email|trim|max_length[255]');
        $this->form_validation->set_rules('password',     'Password',     'required|min_length[8]');
        $this->form_validation->set_rules('password_confirm', 'Confirm Password', 'required|matches[password]');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('auth/register', [
                'page_title' => 'Create Your Account',
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $email        = $this->input->post('email', TRUE);
        $companyName  = $this->input->post('company_name', TRUE);
        $yourName     = $this->input->post('your_name', TRUE);
        $password     = $this->input->post('password');

        if ($this->User_model->emailExists($email)) {
            $this->loadView('auth/register', [
                'page_title' => 'Create Your Account',
                'error'      => 'An account with that email already exists.',
            ]);
            return;
        }

        $this->db->trans_start();

        $slug     = $this->Tenant_model->generateSlug($companyName);
        $tenantId = $this->Tenant_model->create([
            'slug'             => $slug,
            'name'             => $companyName,
            'plan'             => 'starter',
            'status'           => 'active',
            'industry_default' => 'electrical',
            'trial_ends_at'    => date('Y-m-d H:i:s', strtotime('+14 days')),
        ]);

        $userId = $this->User_model->create([
            'tenant_id'     => $tenantId,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'name'          => $yourName,
            'role'          => 'owner',
            'status'        => 'active',
        ]);

        $this->Tenant_model->createSettings($tenantId, ['company_name' => $companyName]);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            log_message('error', 'Auth::register — transaction failed for ' . $email);
            $this->loadView('auth/register', [
                'page_title' => 'Create Your Account',
                'error'      => 'Registration failed. Please try again.',
            ]);
            return;
        }

        $tenant = $this->Tenant_model->getById($tenantId);
        $user   = $this->User_model->getById($userId);

        $this->tenantcontext->setFromUser($user, $tenant);
        $this->auditlog->log('user', 'register', $userId, ['email' => $email]);

        $this->session->set_flashdata('success', 'Welcome to ScopeSync! Your account is ready.');
        redirect('');
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function logout()
    {
        if ($this->tenantcontext->isLoggedIn()) {
            $this->auditlog->log('user', 'logout', $this->tenantcontext->userId());
        }
        $this->session->sess_destroy();
        redirect('login');
    }

    // -------------------------------------------------------------------------
    // Forgot password
    // -------------------------------------------------------------------------

    public function forgotPassword()
    {
        if ($this->tenantcontext->isLoggedIn()) {
            redirect('');
        }

        if ($this->input->method() === 'post') {
            $this->_processForgotPassword();
            return;
        }

        $this->loadView('auth/forgot_password', ['page_title' => 'Reset Password']);
    }

    private function _processForgotPassword()
    {
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|trim');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('auth/forgot_password', [
                'page_title' => 'Reset Password',
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $email = $this->input->post('email', TRUE);
        $user  = $this->User_model->getByEmail($email);

        // Always show the same message regardless of whether email exists (prevents enumeration)
        if ($user && $user['status'] === 'active') {
            $token      = $this->_generateResetToken((int) $user['id'], $user['password_hash']);
            $resetUrl   = site_url('reset-password/' . $token);
            $this->auditlog->log(
                'user', 'forgot_password', (int) $user['id'],
                [], (int) $user['tenant_id'], (int) $user['id']
            );

            $this->load->library('email');
            $this->email->initialize([
                'protocol' => 'smtp',
                'smtp_host' => config_item('smtp_host'),
                'smtp_user' => config_item('smtp_user'),
                'smtp_pass' => config_item('smtp_password'),
                'smtp_port' => config_item('smtp_port'),
                'mailtype'  => 'html',
            ]);
            $this->email->from(config_item('smtp_from'), 'ScopeSync');
            $this->email->to($email);
            $this->email->subject('ScopeSync — Password Reset');
            $this->email->message(
                '<p>Click the link below to reset your password (expires in 1 hour):</p>'
                . '<p><a href="' . $resetUrl . '">' . $resetUrl . '</a></p>'
            );

            if ( ! $this->email->send()) {
                // In dev: expose link via flash so the flow can still be tested
                log_message('error', 'Auth::forgotPassword — email send failed: ' . $this->email->print_debugger());
                if (ENVIRONMENT !== 'production') {
                    $this->session->set_flashdata('dev_reset_link', $resetUrl);
                }
            }
        }

        $this->session->set_flashdata('success', 'If that email is on file, a reset link has been sent.');
        redirect('forgot-password');
    }

    // -------------------------------------------------------------------------
    // Reset password
    // -------------------------------------------------------------------------

    public function resetPassword(string $token = '')
    {
        if ($this->tenantcontext->isLoggedIn()) {
            redirect('');
        }

        $user = $this->_verifyResetToken($token);

        if ( ! $user) {
            $this->session->set_flashdata('error', 'This reset link is invalid or has expired.');
            redirect('forgot-password');
            return;
        }

        if ($this->input->method() === 'post') {
            $this->_processResetPassword($user, $token);
            return;
        }

        $this->loadView('auth/reset_password', [
            'page_title' => 'Set New Password',
            'token'      => $token,
        ]);
    }

    private function _processResetPassword(array $user, string $token)
    {
        $this->form_validation->set_rules('password',         'Password',         'required|min_length[8]');
        $this->form_validation->set_rules('password_confirm', 'Confirm Password', 'required|matches[password]');

        if ($this->form_validation->run() === FALSE) {
            $this->loadView('auth/reset_password', [
                'page_title' => 'Set New Password',
                'token'      => $token,
                'errors'     => $this->form_validation->error_array(),
            ]);
            return;
        }

        $password = $this->input->post('password');
        $this->User_model->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        $this->auditlog->log(
            'user', 'password_reset', (int) $user['id'],
            [], (int) $user['tenant_id'], (int) $user['id']
        );

        $this->session->set_flashdata('success', 'Password updated successfully. Please log in.');
        redirect('login');
    }

    // -------------------------------------------------------------------------
    // Token helpers
    // -------------------------------------------------------------------------

    private function _generateResetToken(int $userId, string $passwordHash): string
    {
        $expires = time() + 3600;
        $payload = $userId . '.' . $expires;
        $key     = config_item('encryption_key') . $passwordHash;
        $hmac    = hash_hmac('sha256', $payload, $key);
        $raw     = base64_encode($payload . '.' . $hmac);
        return rtrim(strtr($raw, '+/', '-_'), '=');
    }

    private function _verifyResetToken(string $token)
    {
        if (empty($token)) return FALSE;

        $padded  = str_pad(strtr($token, '-_', '+/'), strlen($token) + (4 - strlen($token) % 4) % 4, '=');
        $decoded = base64_decode($padded);
        if ( ! $decoded) return FALSE;

        $parts = explode('.', $decoded, 3);
        if (count($parts) !== 3) return FALSE;

        [$userId, $expires, $hmac] = $parts;

        if (time() > (int) $expires) return FALSE;

        $user = $this->User_model->getById((int) $userId);
        if ( ! $user || $user['status'] !== 'active') return FALSE;

        $expected = hash_hmac('sha256', $userId . '.' . $expires, config_item('encryption_key') . $user['password_hash']);

        if ( ! hash_equals($expected, $hmac)) return FALSE;

        return $user;
    }
}
