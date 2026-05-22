<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Division_model extends CI_Model {

    public function getByProject(int $project_id, int $tenant_id)
    {
        return $this->db->where(['project_id' => $project_id, 'tenant_id' => $tenant_id])
                        ->order_by('code', 'ASC')
                        ->get('divisions')
                        ->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('divisions', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function codeExistsInProject(string $code, int $project_id): bool
    {
        return $this->db->where(['code' => $code, 'project_id' => $project_id])
                        ->count_all_results('divisions') > 0;
    }

    public function create(array $data): int
    {
        $this->db->insert('divisions', $data);
        return (int) $this->db->insert_id();
    }

    public function delete(int $id, int $tenant_id): bool
    {
        return $this->db->where(['id' => $id, 'tenant_id' => $tenant_id])
                        ->delete('divisions');
    }
}
