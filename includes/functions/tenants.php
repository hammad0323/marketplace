<?php
/**
 * tenants — plus the small set of deliberately cross-tenant "platform"
 * aggregate functions used only by platform/ pages. Those are kept in
 * this one file (clearly marked below) rather than scattered, so
 * "this query is intentionally global, not a scoping bug" stays
 * auditable in a single place.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_tenant(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM tenants WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_tenant_by_subdomain(string $subdomain): ?array
{
    return mp_db_fetch_one('SELECT * FROM tenants WHERE subdomain = ? LIMIT 1', [$subdomain]);
}

function mp_insert_tenant(array $data): int
{
    return mp_db_insert('tenants', $data);
}

function mp_suspend_tenant(int $id, string $reason): void
{
    mp_db_execute(
        'UPDATE tenants SET status = ?, suspended_at = NOW(), suspended_reason = ? WHERE id = ?',
        ['suspended', $reason, $id]
    );
}

function mp_activate_tenant(int $id): void
{
    mp_db_execute(
        "UPDATE tenants SET status = 'active', suspended_at = NULL, suspended_reason = NULL WHERE id = ?",
        [$id]
    );
}

// -----------------------------------------------------------------
// Platform-only cross-tenant aggregates — deliberately NOT scoped by
// mp_tenant_id(), used only from platform/ pages by a platform_admin.
// -----------------------------------------------------------------

function mp_platform_all_tenants(): array
{
    return mp_db_fetch_all('SELECT * FROM tenants ORDER BY created_at DESC');
}

function mp_platform_tenant_counts(int $tenantId): array
{
    return [
        'vendors' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM vendors WHERE tenant_id = ?', [$tenantId]),
        'customers' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$tenantId]),
        'products' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tenantId]),
        'orders' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM orders WHERE tenant_id = ?', [$tenantId]),
        'revenue' => (float) (mp_db_fetch_value(
            "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE tenant_id = ? AND status != 'cancelled'",
            [$tenantId]
        ) ?? 0),
    ];
}

function mp_platform_stats(): array
{
    return [
        'total_tenants' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM tenants'),
        'trial_tenants' => (int) mp_db_fetch_value("SELECT COUNT(*) FROM tenants WHERE status = 'trial'"),
        'active_tenants' => (int) mp_db_fetch_value("SELECT COUNT(*) FROM tenants WHERE status = 'active'"),
        'suspended_tenants' => (int) mp_db_fetch_value("SELECT COUNT(*) FROM tenants WHERE status = 'suspended'"),
        'total_vendors' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM vendors'),
        'total_customers' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM customers'),
        'total_orders' => (int) mp_db_fetch_value('SELECT COUNT(*) FROM orders'),
        'total_revenue' => (float) (mp_db_fetch_value(
            "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'cancelled'"
        ) ?? 0),
    ];
}
