<?php
/**
 * Wishlist — guests keep a session wishlist, customers a persistent one.
 */

function wishlist_enabled(): bool
{
    return setting_bool('wishlist_enabled', true);
}

function wishlist_id(bool $create = false): ?int
{
    $cid = customer_id();
    if ($cid) {
        $id = db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$cid]);
        if (!$id && $create) {
            $id = db_insert('wishlists', ['customer_id' => $cid]);
        }
        return $id ? (int) $id : null;
    }
    $token = $_SESSION['wishlist_token'] ?? null;
    if (!$token && $create) {
        $token = $_SESSION['wishlist_token'] = random_token(32);
    }
    if (!$token) {
        return null;
    }
    $id = db_val('SELECT id FROM wishlists WHERE session_token = ?', [hash('sha256', $token)]);
    if (!$id && $create) {
        $id = db_insert('wishlists', ['session_token' => hash('sha256', $token)]);
    }
    return $id ? (int) $id : null;
}

function wishlist_product_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $wid = wishlist_id();
        $ids = $wid ? array_map('intval', db_col('SELECT product_id FROM wishlist_items WHERE wishlist_id = ? ORDER BY created_at DESC', [$wid])) : [];
    }
    return $ids;
}

function in_wishlist(int $productId): bool
{
    return in_array($productId, wishlist_product_ids(), true);
}

/** Toggle; returns true when now in the wishlist. */
function wishlist_toggle(int $productId): bool
{
    $wid = wishlist_id(true);
    if (db_exec('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wid, $productId])) {
        return false;
    }
    db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wid, $productId]);
    return true;
}

function wishlist_merge_guest(?string $token, int $customerId): void
{
    if (!$token) {
        return;
    }
    $guest = db_val('SELECT id FROM wishlists WHERE session_token = ?', [hash('sha256', $token)]);
    if (!$guest) {
        return;
    }
    $mine = db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$customerId]);
    if (!$mine) {
        db_exec('UPDATE wishlists SET customer_id = ?, session_token = NULL WHERE id = ?', [$customerId, $guest]);
        return;
    }
    db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) SELECT ?, product_id FROM wishlist_items WHERE wishlist_id = ?', [$mine, $guest]);
    db_exec('DELETE FROM wishlists WHERE id = ?', [$guest]);
}

function wishlist_count(): int
{
    return count(wishlist_product_ids());
}
