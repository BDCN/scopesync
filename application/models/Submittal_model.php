<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Submittal_model extends CI_Model {

    public function getByProject(int $project_id, int $tenant_id)
    {
        return $this->db->where(['project_id' => $project_id, 'tenant_id' => $tenant_id])
                        ->order_by('created_at', 'DESC')
                        ->get('submittal_jobs')
                        ->result_array();
    }

    public function getByDivision(int $division_id, int $tenant_id)
    {
        return $this->db->where(['division_id' => $division_id, 'tenant_id' => $tenant_id])
                        ->order_by('created_at', 'DESC')
                        ->get('submittal_jobs')
                        ->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('submittal_jobs', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function create(array $data): int
    {
        $this->db->insert('submittal_jobs', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, int $tenant_id, array $data): bool
    {
        return $this->db->where(['id' => $id, 'tenant_id' => $tenant_id])
                        ->update('submittal_jobs', $data);
    }
}
