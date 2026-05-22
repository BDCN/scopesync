<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tenant_model extends CI_Model {

    public function getById(int $id)
    {
        return $this->db->get_where('tenants', ['id' => $id])->row_array();
    }

    public function slugExists(string $slug): bool
    {
        return $this->db->where('slug', $slug)->count_all_results('tenants') > 0;
    }

    public function create(array $data): int
    {
        $this->db->insert('tenants', $data);
        return (int) $this->db->insert_id();
    }

    public function createSettings(int $tenant_id, array $extra = [])
    {
        $row = array_merge(['tenant_id' => $tenant_id, 'primary_color' => '#1A73E8'], $extra);
        $this->db->insert('tenant_settings', $row);
    }

    public function getSettings(int $tenant_id)
    {
        return $this->db->get_where('tenant_settings', ['tenant_id' => $tenant_id])->row_array();
    }

    public function generateSlug(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $base = trim($base, '-');
        $base = substr($base, 0, 50);
        $slug = $base;
        $i    = 2;
        while ($this->slugExists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
