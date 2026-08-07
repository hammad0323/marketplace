<?php
/**
 * cms_banners — homepage/marketplace hero content, editable from
 * admin/banners.php. Pages call mp_active_banner() and fall back to
 * their own hardcoded copy when nothing is configured, so an empty
 * table never breaks a page.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

/** The active banner for one marketplace (or the main homepage, when $marketplaceTypeId is null), within this tenant. */
function mp_active_banner(?int $marketplaceTypeId): ?array
{
    if ($marketplaceTypeId === null) {
        return mp_db_fetch_one(
            'SELECT * FROM cms_banners WHERE tenant_id = ? AND marketplace_type_id IS NULL AND is_active = 1 ORDER BY sort_order ASC LIMIT 1',
            [mp_tenant_id()]
        );
    }

    return mp_db_fetch_one(
        'SELECT * FROM cms_banners WHERE tenant_id = ? AND marketplace_type_id = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 1',
        [mp_tenant_id(), $marketplaceTypeId]
    );
}

function mp_all_banners(): array
{
    return mp_db_fetch_all(
        'SELECT cms_banners.*, marketplace_types.name AS marketplace_name
         FROM cms_banners
         LEFT JOIN marketplace_types ON marketplace_types.id = cms_banners.marketplace_type_id
         WHERE cms_banners.tenant_id = ?
         ORDER BY cms_banners.marketplace_type_id IS NULL DESC, cms_banners.sort_order ASC',
        [mp_tenant_id()]
    );
}

function mp_find_banner(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM cms_banners WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_insert_banner(array $data): int
{
    return mp_db_insert('cms_banners', $data);
}

function mp_update_banner(int $id, array $data): void
{
    mp_db_update('cms_banners', $data, 'id = ?', [$id]);
}

function mp_delete_banner(int $id): void
{
    mp_db_execute('DELETE FROM cms_banners WHERE id = ?', [$id]);
}
