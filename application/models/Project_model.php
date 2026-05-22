<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Project_model extends CI_Model {

    public function getByTenant(int $tenant_id, string $status = 'active')
    {
        $q = $this->db->where('tenant_id', $tenant_id);
        if ($status !== NULL) {
            $q->where('status', $status);
        }
        return $q->order_by('created_at', 'DESC')->get('projects')->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('projects', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function create(array $data): int
    {
        $this->db->insert('projects', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, int $tenant_id, array $data): bool
    {
        return $this->db->where(['id' => $id, 'tenant_id' => $tenant_id])
                        ->update('projects', $data);
    }

    public function archive(int $id, int $tenant_id): bool
    {
        return $this->db->where(['id' => $id, 'tenant_id' => $tenant_id])
                        ->update('projects', ['status' => 'archived']);
    }
}
