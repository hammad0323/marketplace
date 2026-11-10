<?php
/**
 * Centralised inventory. ALL stock changes go through stock_change(), which
 * locks the inventory row (SELECT ... FOR UPDATE), writes the new balance and
 * logs an inventory_movements row with type, quantity, reason and admin.
 * Online orders and POS sales share the same rows, so stock is always unified.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function stock_get(int $productId, int $variationId = 0): int
{
    return (int) db_val('SELECT quantity FROM inventory WHERE product_id = ? AND variation_id = ?', [$productId, $variationId]);
}

/** Ensure an inventory row exists (no movement logged). */
function stock_ensure_row(int $productId, int $variationId = 0): void
{
    db_exec('INSERT IGNORE INTO inventory (product_id, variation_id, quantity) VALUES (?, ?, 0)', [$productId, $variationId]);
}

/**
 * Apply a stock change atomically. Must run inside db_tx() for multi-line
 * operations; on its own it wraps itself in a transaction.
 *
 * @param bool $allowNegative  false => throws if the result would go below zero
 * @return int new balance
 */
function stock_change(int $productId, int $variationId, int $delta, string $type, array $meta = [], bool $allowNegative = false): int
{
    return db_tx(function () use ($productId, $variationId, $delta, $type, $meta, $allowNegative) {
        stock_ensure_row($productId, $variationId);
        $row = db_row('SELECT id, quantity FROM inventory WHERE product_id = ? AND variation_id = ? FOR UPDATE', [$productId, $variationId]);
        $new = (int) $row['quantity'] + $delta;
        if ($new < 0 && !$allowNegative) {
            $name = db_val('SELECT name FROM products WHERE id = ?', [$productId]);
            throw new RuntimeException('Not enough stock for "' . $name . '" (available: ' . (int) $row['quantity'] . ').');
        }
        db_exec('UPDATE inventory SET quantity = ? WHERE id = ?', [$new, $row['id']]);
        db_exec('INSERT INTO inventory_movements (product_id, variation_id, movement_type, qty_change, balance_after, unit_cost, reference_type, reference_id, reason, admin_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $productId, $variationId, $type, $delta, $new,
            isset($meta['unit_cost']) ? (float) $meta['unit_cost'] : null,
            $meta['ref_type'] ?? null, isset($meta['ref_id']) ? (int) $meta['ref_id'] : null,
            isset($meta['reason']) ? mb_substr($meta['reason'], 0, 255) : null,
            $meta['admin_id'] ?? (admin_id() ?: null),
        ]);
        // Low stock alert for admins (only when crossing the threshold downward)
        if ($delta < 0) {
            $p = db_row('SELECT name, low_stock_threshold, track_stock FROM products WHERE id = ?', [$productId]);
            $before = $new - $delta;
            if ($p && $p['track_stock'] && $new <= (int) $p['low_stock_threshold'] && $before > (int) $p['low_stock_threshold']) {
                notify_admin($new <= 0 ? 'out_of_stock' : 'low_stock',
                    ($new <= 0 ? 'Out of stock: ' : 'Low stock: ') . $p['name'],
                    'Remaining quantity: ' . $new, 'inventory.php?q=' . urlencode($p['name']), $productId);
            }
        }
        return $new;
    });
}

/** Set an absolute stock level (adjustment / stock count). */
function stock_set(int $productId, int $variationId, int $newQty, string $reason, string $type = 'adjustment'): int
{
    return db_tx(function () use ($productId, $variationId, $newQty, $reason, $type) {
        stock_ensure_row($productId, $variationId);
        $cur = (int) db_val('SELECT quantity FROM inventory WHERE product_id = ? AND variation_id = ? FOR UPDATE', [$productId, $variationId]);
        $delta = $newQty - $cur;
        if ($delta === 0) return $cur;
        return stock_change($productId, $variationId, $delta, $type, ['reason' => $reason, 'ref_type' => 'manual'], true);
    });
}

function movement_types(): array
{
    return ['opening' => 'Opening stock', 'purchase' => 'Purchase (stock in)', 'sale' => 'Online sale', 'pos_sale' => 'POS sale',
        'adjustment' => 'Manual adjustment', 'cancel_restock' => 'Cancelled - restocked', 'import' => 'Bulk import', 'return' => 'Customer return',
        'damage' => 'Damaged / expired'];
}

/**
 * Receive a purchase into stock (status: received). Idempotent: purchases
 * already received are not added again.
 */
function purchase_receive(int $purchaseId): void
{
    db_tx(function () use ($purchaseId) {
        $pur = db_row('SELECT * FROM purchases WHERE id = ? FOR UPDATE', [$purchaseId]);
        if (!$pur || $pur['status'] === 'received' && db_val("SELECT COUNT(*) FROM inventory_movements WHERE reference_type = 'purchase' AND reference_id = ?", [$purchaseId])) {
            return;
        }
        foreach (db_all('SELECT * FROM purchase_items WHERE purchase_id = ?', [$purchaseId]) as $it) {
            stock_change((int) $it['product_id'], (int) $it['variation_id'], (int) $it['quantity'], 'purchase', [
                'ref_type' => 'purchase', 'ref_id' => $purchaseId, 'unit_cost' => $it['unit_cost'],
                'reason' => 'Purchase #' . $purchaseId . ($pur['reference_no'] ? ' (' . $pur['reference_no'] . ')' : ''),
            ]);
            // keep latest purchase cost on the product/variation
            if ((float) $it['unit_cost'] > 0) {
                if ((int) $it['variation_id']) {
                    db_exec('UPDATE product_variations SET cost_price = ? WHERE id = ?', [$it['unit_cost'], $it['variation_id']]);
                } else {
                    db_exec('UPDATE products SET cost_price = ? WHERE id = ?', [$it['unit_cost'], $it['product_id']]);
                }
            }
        }
        db_exec("UPDATE purchases SET status = 'received' WHERE id = ?", [$purchaseId]);
    });
}
