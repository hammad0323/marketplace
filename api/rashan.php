<?php
/**
 * POST /api/rashan - Monthly Rashan builder.
 * actions: calc | lists | load | save | duplicate | delete | to_cart
 * items = JSON [{"p":productId,"v":variationId,"q":qty}, ...]
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (!is_post()) json_error('Method not allowed', 405);
csrf_check();
$action = input('action');
$cid = customer_id();

/** Parse + sanitise posted items. */
function rashan_items_in(): array
{
    $raw = json_decode((string) ($_POST['items'] ?? '[]'), true);
    $out = [];
    if (!is_array($raw)) return [];
    foreach (array_slice($raw, 0, 300) as $r) {
        $p = (int) ($r['p'] ?? 0); $v = (int) ($r['v'] ?? 0); $q = max(0, min(999, (int) ($r['q'] ?? 0)));
        if ($p && $q) $out[$p . '-' . $v] = ['product_id' => $p, 'variation_id' => $v, 'quantity' => $q];
    }
    return array_values($out);
}

function rashan_owned_list(int $id, int $cid): array
{
    $l = db_row('SELECT * FROM grocery_lists WHERE id = ? AND customer_id = ?', [$id, $cid]);
    if (!$l) json_error('List not found.', 404);
    return $l;
}

function rashan_list_payload(array $l): array
{
    $items = db_all('SELECT product_id AS p, variation_id AS v, quantity AS q FROM grocery_list_items WHERE list_id = ?', [$l['id']]);
    return ['id' => (int) $l['id'], 'name' => $l['name'], 'budget' => $l['monthly_budget'] !== null ? (float) $l['monthly_budget'] : null, 'notes' => (string) $l['notes'], 'items' => $items];
}

$requireLogin = function () use ($cid) { if (!$cid) json_error('Please log in to save your rashan lists.', 401); };

switch ($action) {
    case 'calc':
        $priced = price_lines(rashan_items_in());
        $budget = input_float('budget', 0.0);
        json_out(['ok' => true, 'count' => count($priced['lines']), 'quantity' => $priced['count'], 'total' => $priced['subtotal'], 'total_fmt' => money($priced['subtotal']),
            'budget' => $budget, 'remaining' => round($budget - $priced['subtotal'], 2), 'remaining_fmt' => money(abs($budget - $priced['subtotal'])), 'issues' => $priced['issues'],
            'lines' => array_map(function ($l) { return ['p' => $l['product_id'], 'v' => $l['variation_id'], 'unit_price' => $l['unit_price'], 'line_total' => $l['line_total'], 'problem' => $l['problem']]; }, $priced['lines'])]);

    case 'lists':
        $requireLogin();
        $rows = db_all('SELECT g.id, g.name, g.monthly_budget, g.updated_at, g.created_at, COUNT(i.id) AS items FROM grocery_lists g LEFT JOIN grocery_list_items i ON i.list_id = g.id
            WHERE g.customer_id = ? GROUP BY g.id ORDER BY COALESCE(g.updated_at, g.created_at) DESC', [$cid]);
        json_out(['ok' => true, 'lists' => $rows]);

    case 'load':
        $requireLogin();
        json_out(['ok' => true, 'list' => rashan_list_payload(rashan_owned_list(input_int('id'), $cid))]);

    case 'save':
        $requireLogin();
        $items = rashan_items_in();
        $name = mb_substr(input('name'), 0, 120) ?: ('My Rashan - ' . date('F Y'));
        $budget = input_float('budget', null);
        $notes = mb_substr(input('notes'), 0, 2000);
        $id = input_int('id');
        if ($id) rashan_owned_list($id, $cid);
        if (!$id && db_val('SELECT COUNT(*) FROM grocery_lists WHERE customer_id = ?', [$cid]) >= 20) json_error('You can save up to 20 lists. Please delete an old list.');
        $id = db_tx(function () use ($id, $cid, $name, $budget, $notes, $items) {
            if ($id) {
                db_exec('UPDATE grocery_lists SET name = ?, monthly_budget = ?, notes = ?, updated_at = NOW() WHERE id = ?', [$name, $budget, $notes ?: null, $id]);
                db_exec('DELETE FROM grocery_list_items WHERE list_id = ?', [$id]);
            } else {
                $id = db_insert('INSERT INTO grocery_lists (customer_id, name, monthly_budget, notes) VALUES (?, ?, ?, ?)', [$cid, $name, $budget, $notes ?: null]);
            }
            foreach ($items as $it) {
                if (sellable_item($it['product_id'], $it['variation_id'], true)) {
                    db_exec('INSERT INTO grocery_list_items (list_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)', [$id, $it['product_id'], $it['variation_id'], $it['quantity']]);
                }
            }
            return $id;
        });
        json_out(['ok' => true, 'id' => $id, 'message' => 'Rashan list "' . $name . '" saved.']);

    case 'duplicate':
        $requireLogin();
        $l = rashan_owned_list(input_int('id'), $cid);
        $newId = db_tx(function () use ($l, $cid) {
            $nid = db_insert('INSERT INTO grocery_lists (customer_id, name, monthly_budget, notes) VALUES (?, ?, ?, ?)', [$cid, mb_substr('Rashan - ' . date('F Y') . ' (copy of ' . $l['name'] . ')', 0, 120), $l['monthly_budget'], $l['notes']]);
            db_exec('INSERT INTO grocery_list_items (list_id, product_id, variation_id, quantity, note) SELECT ?, product_id, variation_id, quantity, note FROM grocery_list_items WHERE list_id = ?', [$nid, $l['id']]);
            return $nid;
        });
        json_out(['ok' => true, 'id' => $newId, 'list' => rashan_list_payload(db_row('SELECT * FROM grocery_lists WHERE id = ?', [$newId])), 'message' => 'List duplicated.']);

    case 'delete':
        $requireLogin();
        $l = rashan_owned_list(input_int('id'), $cid);
        db_exec('DELETE FROM grocery_lists WHERE id = ?', [$l['id']]);
        json_out(['ok' => true, 'message' => 'List deleted.']);

    case 'to_cart':
        $items = rashan_items_in();
        if (!$items) json_error('Your rashan list is empty.');
        $order = input('order') === '1';
        if ($order) cart_clear();
        $errors = [];
        foreach ($items as $it) {
            $err = cart_set($it['product_id'], $it['variation_id'], $it['quantity'], !$order);
            if ($err) {
                $name = db_val('SELECT name FROM products WHERE id = ?', [$it['product_id']]);
                $errors[] = $name . ': ' . $err;
            }
        }
        if ($order) {
            $_SESSION['checkout_order_type'] = 'rashan';
            $lid = input_int('list_id');
            $_SESSION['checkout_list_id'] = ($lid && $cid && db_val('SELECT id FROM grocery_lists WHERE id = ? AND customer_id = ?', [$lid, $cid])) ? $lid : null;
        }
        json_out(['ok' => true, 'count' => cart_count(), 'errors' => $errors, 'redirect' => $order ? url('checkout/') : url('cart/'),
            'message' => $errors ? 'Some items could not be added.' : ($order ? 'Proceeding to checkout…' : 'All items added to your cart.')]);

    default:
        json_error('Unknown action.');
}
