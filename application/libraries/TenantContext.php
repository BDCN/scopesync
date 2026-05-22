<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TenantContext {

    protected $CI;
    protected $_data = [];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->loadFromSession();
    }

    public function loadFromSession()
    {
        $this->_data = [
            'tenant_id'    => $this->CI->session->userdata('tenant_id'),
            'tenant_slug'  => $this->CI->session->userdata('tenant_slug'),
            'tenant_name'  => $this->CI->session->userdata('tenant_name'),
            'user_id'      => $this->CI->session->userdata('user_id'),
            'user_name'    => $this->CI->session->userdata('user_name'),
            'user_email'   => $this->CI->session->userdata('user_email'),
            'user_role'    => $this->CI->session->userdata('user_role'),
        ];
    }

    public function setFromUser(array $user, array $tenant)
    {
        $data = [
            'tenant_id'   => (int) $tenant['id'],
            'tenant_slug' => $tenant['slug'],
            'tenant_name' => $tenant['name'],
            'user_id'     => (int) $user['id'],
            'user_name'   => $user['name'],
            'user_email'  => $user['email'],
            'user_role'   => $user['role'],
        ];
        $this->CI->session->set_userdata($data);
        $this->_data = $data;
    }

    public function id()        { return (int) ($this->_data['tenant_id'] ?? 0); }
    public function slug()      { return $this->_data['tenant_slug'] ?? ''; }
    public function name()      { return $this->_data['tenant_name'] ?? ''; }
    public function userId()    { return (int) ($this->_data['user_id'] ?? 0); }
    public function userName()  { return $this->_data['user_name'] ?? ''; }
    public function userEmail() { return $this->_data['user_email'] ?? ''; }
    public function userRole()  { return $this->_data['user_role'] ?? ''; }

    public function isLoggedIn()
    {
        return ! empty($this->_data['tenant_id']) && ! empty($this->_data['user_id']);
    }
}
