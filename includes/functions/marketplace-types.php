<?php
/**
 * marketplace_types — the marketplaces one tenant operates. Now
 * tenant-owned (each signed-up business gets its own artisan/business/
 * official rows, seeded at signup and editable from that tenant's own
 * admin panel) rather than one fixed global lookup shared by every
 * tenant. A new marketplace type is a new row, not a new set of
 * if-branches.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_marketplace_type(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM marketplace_types WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_find_marketplace_type_by_slug(string $slug): ?array
{
    return mp_db_fetch_one('SELECT * FROM marketplace_types WHERE tenant_id = ? AND slug = ? LIMIT 1', [mp_tenant_id(), $slug]);
}

/** Every marketplace type this tenant operates — drives the nav in templates/header.php and footer.php instead of a hardcoded 3-link list. */
function mp_all_marketplace_types(): array
{
    return mp_db_fetch_all('SELECT * FROM marketplace_types WHERE tenant_id = ? ORDER BY id ASC', [mp_tenant_id()]);
}

function mp_update_marketplace_type(int $id, array $data): void
{
    mp_db_update('marketplace_types', $data, 'id = ?', [$id]);
}

/**
 * Seeds the canonical artisan/business/official rows for a brand-new
 * tenant at signup (see signup/start.php) — the single source of
 * truth for "what a fresh tenant starts with," reused instead of
 * duplicating this seed list a second time inline in the signup page.
 */
function mp_seed_default_marketplace_types(int $tenantId): void
{
    $defaults = [
        ['slug' => 'artisan', 'name' => 'Artisan Marketplace', 'badge_label' => '🏺 Handmade'],
        ['slug' => 'business', 'name' => 'Business Shops', 'badge_label' => '🏪 Business Shop'],
        ['slug' => 'official', 'name' => 'Official Store', 'badge_label' => '⭐ Official Store'],
    ];

    foreach ($defaults as $type) {
        mp_db_insert('marketplace_types', [
            'tenant_id'   => $tenantId,
            'slug'        => $type['slug'],
            'name'        => $type['name'],
            'badge_label' => $type['badge_label'],
        ]);
    }
}
