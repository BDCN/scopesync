<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Extraction_model extends CI_Model {

    public function getBySubmittal(int $submittal_id, int $tenant_id): array
    {
        return $this->db->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
                        ->order_by('created_at', 'ASC')
                        ->get('extractions')
                        ->result_array();
    }

    /**
     * Returns extractions for a document, newest first. First row is the latest.
     */
    public function getByDocument(int $document_id): array
    {
        return $this->db->where('document_id', $document_id)
                        ->order_by('created_at', 'DESC')
                        ->get('extractions')
                        ->result_array();
    }

    public function getByIdAndTenant(int $id, int $tenant_id)
    {
        return $this->db->get_where('extractions', [
            'id'        => $id,
            'tenant_id' => $tenant_id,
        ])->row_array();
    }

    public function create(array $data): int
    {
        $this->db->insert('extractions', $data);
        return (int) $this->db->insert_id();
    }

    /**
     * Returns up to $limit oldest pending extractions for the worker to process.
     */
    public function getPending(int $limit = 5): array
    {
        return $this->db->where('status', 'pending')
                        ->order_by('created_at', 'ASC')
                        ->limit($limit)
                        ->get('extractions')
                        ->result_array();
    }

    /**
     * Atomically claims a pending extraction by ID for processing.
     * Returns TRUE only if this call successfully claimed it (affected_rows > 0).
     */
    public function claimById(int $id): bool
    {
        $this->db->where(['id' => $id, 'status' => 'pending'])
                 ->update('extractions', [
                     'status'     => 'running',
                     'started_at' => date('Y-m-d H:i:s'),
                 ]);
        return $this->db->affected_rows() > 0;
    }

    public function markComplete(int $id, array $result): bool
    {
        return $this->db->where('id', $id)->update('extractions', array_merge($result, [
            'status'       => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function markFailed(int $id, string $error): bool
    {
        return $this->db->where('id', $id)->update('extractions', [
            'status'        => 'failed',
            'error_message' => substr($error, 0, 65535),
            'completed_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function recentForAdmin(int $limit = 50): array
    {
        return $this->db
            ->select('e.*, d.original_filename, d.doc_type, sj.name AS submittal_name, t.name AS tenant_name')
            ->from('extractions e')
            ->join('documents d',       'd.id = e.document_id',              'left')
            ->join('submittal_jobs sj', 'sj.id = e.submittal_job_id',        'left')
            ->join('tenants t',         't.id = e.tenant_id',                'left')
            ->order_by('e.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }
}
