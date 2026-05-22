<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AuditLog {

    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Write an entry to audit_log.
     * $tenant_id / $user_id override session values — needed for auth events
     * where the session hasn't been populated yet.
     */
    public function log(
        string $entity_type,
        string $action,
        int    $entity_id  = NULL,
        array  $metadata   = [],
        int    $tenant_id  = NULL,
        int    $user_id    = NULL
    ) {
        $this->CI->db->insert('audit_log', [
            'tenant_id'   => $tenant_id  !== NULL ? $tenant_id  : $this->CI->tenantcontext->id(),
            'user_id'     => $user_id    !== NULL ? $user_id    : ($this->CI->tenantcontext->userId() ?: NULL),
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'action'      => $action,
            'metadata'    => $metadata ? json_encode($metadata) : NULL,
            'ip_address'  => $this->CI->input->ip_address(),
            'user_agent'  => substr((string) ($this->CI->input->user_agent() ?? ''), 0, 500),
        ]);
    }
}
