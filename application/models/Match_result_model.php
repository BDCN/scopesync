<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Match_result_model extends CI_Model {

    public function create(array $data): int
    {
        $this->db->insert('match_results', [
            'tenant_id'              => $data['tenant_id'],
            'submittal_job_id'       => $data['submittal_job_id'],
            'spec_extraction_id'     => $data['spec_extraction_id'],
            'cutsheet_extraction_id' => $data['cutsheet_extraction_id'],
            'catalog_number'         => $data['catalog_number'],
            'product_category'       => $data['product_category'] ?? null,
            'overall_result'         => $data['overall_result'],
            'attribute_results'      => json_encode([
                'attribute_results'         => $data['attribute_results']         ?? [],
                'listing_results'           => $data['listing_results']           ?? [],
                'unmatched_spec_attributes' => $data['unmatched_spec_attributes'] ?? [],
            ]),
        ]);
        return (int) $this->db->insert_id();
    }

    public function existsForSubmittal(int $submittal_id, int $tenant_id): bool
    {
        return $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->count_all_results('match_results') > 0;
    }

    public function getBySubmittal(int $submittal_id, int $tenant_id): array
    {
        return $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->order_by('product_category', 'ASC')
            ->order_by('catalog_number', 'ASC')
            ->get('match_results')
            ->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('match_results', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function countBySubmittal(int $submittal_id, int $tenant_id): int
    {
        return (int) $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->count_all_results('match_results');
    }
}
