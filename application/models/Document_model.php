<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_model extends CI_Model {

    public function getBySubmittal(int $submittal_id, int $tenant_id): array
    {
        return $this->db->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
                        ->order_by('created_at', 'ASC')
                        ->get('documents')
                        ->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('documents', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function getByIdRaw(int $id)
    {
        return $this->db->get_where('documents', ['id' => $id])->row_array();
    }

    public function findBySha256(int $tenant_id, string $sha256)
    {
        return $this->db->get_where('documents', [
            'tenant_id' => $tenant_id,
            'sha256'    => $sha256,
        ])->row_array();
    }

    public function countThisMonth(int $tenant_id): int
    {
        return (int) $this->db
            ->where('tenant_id', $tenant_id)
            ->where('created_at >=', date('Y-m-01 00:00:00'))
            ->count_all_results('documents');
    }

    public function create(array $data): int
    {
        $this->db->insert('documents', $data);
        return (int) $this->db->insert_id();
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->where('id', $id)->update('documents', $data);
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('documents', ['id' => $id]);
    }
}
