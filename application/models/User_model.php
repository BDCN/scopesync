<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function getById(int $id)
    {
        return $this->db->get_where('users', ['id' => $id])->row_array();
    }

    public function getByEmail(string $email)
    {
        return $this->db->get_where('users', ['email' => $email])->row_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('users', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function emailExists(string $email): bool
    {
        return $this->db->where('email', $email)->count_all_results('users') > 0;
    }

    public function create(array $data): int
    {
        $this->db->insert('users', $data);
        return (int) $this->db->insert_id();
    }

    public function updatePassword(int $id, string $hash)
    {
        $this->db->where('id', $id)->update('users', ['password_hash' => $hash]);
    }

    public function updateLastLogin(int $id)
    {
        $this->db->where('id', $id)->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
