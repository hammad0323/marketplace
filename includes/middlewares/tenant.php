<?php
/**
 * Ambient "current tenant" accessor — resolved once per request from
 * the Host header (see config/tenant.php, which calls
 * mp_set_current_tenant() exactly once, near the end of the bootstrap
 * chain). Every table-owning query function reads the current tenant
 * via mp_tenant_id() the same way the rest of this project already
 * reads "the current admin" via mp_current_admin() in auth.php — an
 * ambient, request-derived accessor, not a parameter threaded through
 * every function call.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_set_current_tenant(?array $tenant): void
{
    $GLOBALS['_mp_current_tenant'] = $tenant;
}

function mp_current_tenant(): ?array
{
    return $GLOBALS['_mp_current_tenant'] ?? null;
}

/**
 * The current tenant's id. Throws rather than silently returning 0 —
 * a query function that calls this before a tenant has been resolved
 * (or outside a tenant context, e.g. platform/ pages) has a real bug
 * that should fail loudly in development, not write tenant_id = 0.
 */
function mp_tenant_id(): int
{
    $tenant = mp_current_tenant();
    if (!$tenant) {
        throw new RuntimeException('mp_tenant_id() called with no tenant resolved for this request.');
    }
    return (int) $tenant['id'];
}
