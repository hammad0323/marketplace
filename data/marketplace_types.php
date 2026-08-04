<?php
/** Plain query functions for the marketplace_types lookup table. */

function mp_find_marketplace_type(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM marketplace_types WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_marketplace_type_by_slug(string $slug): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM marketplace_types WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}
