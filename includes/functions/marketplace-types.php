<?php
/**
 * marketplace_types — lookup table for the marketplaces the platform
 * operates. A new marketplace type is a new row, not a new set of
 * if-branches.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_marketplace_type(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM marketplace_types WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_marketplace_type_by_slug(string $slug): ?array
{
    return mp_db_fetch_one('SELECT * FROM marketplace_types WHERE slug = ? LIMIT 1', [$slug]);
}
