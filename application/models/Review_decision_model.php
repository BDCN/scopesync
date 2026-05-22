<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Review_decision_model extends CI_Model {

    /**
     * Insert or update a decision for a given match_result_id (UPSERT via UNIQUE KEY).
     */
    public function save(array $data): bool
    {
        $existing = $this->db->get_where('review_decisions', [
            'match_result_id' => $data['match_result_id'],
        ])->row_array();

        if ($existing) {
            return (bool) $this->db->where('id', $existing['id'])->update('review_decisions', [
                'decision'       => $data['decision'],
                'override_notes' => $data['override_notes'] ?? null,
                'decided_by'     => $data['decided_by'],
                'decided_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->insert('review_decisions', [
            'tenant_id'        => $data['tenant_id'],
            'submittal_job_id' => $data['submittal_job_id'],
            'match_result_id'  => $data['match_result_id'],
            'decision'         => $data['decision'],
            'override_notes'   => $data['override_notes'] ?? null,
            'decided_by'       => $data['decided_by'],
            'decided_at'       => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id() > 0;
    }

    public function getBySubmittal(int $submittal_id, int $tenant_id): array
    {
        return $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->get('review_decisions')
            ->result_array();
    }

    public function getByMatchResult(int $match_result_id)
    {
        return $this->db->get_where('review_decisions', [
            'match_result_id' => $match_result_id,
        ])->row_array() ?: null;
    }

    /**
     * Returns TRUE when every match_result row for this submittal has a decision.
     */
    public function allDecided(int $submittal_id, int $tenant_id): bool
    {
        $totalResults = $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->count_all_results('match_results');

        if ($totalResults === 0) {
            return FALSE;
        }

        $decidedCount = $this->db
            ->where(['submittal_job_id' => $submittal_id, 'tenant_id' => $tenant_id])
            ->count_all_results('review_decisions');

        return $decidedCount >= $totalResults;
    }

    public function hasRejections(int $submittal_id, int $tenant_id): bool
    {
        return $this->db->where([
            'submittal_job_id' => $submittal_id,
            'tenant_id'        => $tenant_id,
            'decision'         => 'rejected',
        ])->count_all_results('review_decisions') > 0;
    }

    /**
     * Build a map of match_result_id → decision row for a submittal.
     */
    public function mapByMatchResult(int $submittal_id, int $tenant_id): array
    {
        $rows = $this->getBySubmittal($submittal_id, $tenant_id);
        $map  = [];
        foreach ($rows as $row) {
            $map[(int) $row['match_result_id']] = $row;
        }
        return $map;
    }
}
