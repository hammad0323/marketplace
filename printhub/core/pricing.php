<?php
/**
 * Pricing engine. Every shop defines its own rates in shop_pricing (all in PKR):
 *   print      price per printed page / sheet / copy, optionally narrowed by category, paper size, colour, sides, paper type, GSM
 *   item       price per piece for item-based categories (visiting cards, photos, ID cards)
 *   paper      surcharge per sheet (or piece) for a paper type / GSM
 *   finishing  binding, lamination, cutting… per copy / sheet / order / piece
 *   discount   % off an item when its quantity reaches min_qty
 * A rule with an empty field matches everything. The highest quantity tier reached wins; among rules of the
 * same tier the most specific one wins (so set colour / size on bulk-tier prices that should not apply to everything). When something cannot be priced the item is marked unpriced and the order goes to
 * the shop for a quote — prices are never invented.
 */

function pricing_rules(int $shopId): array
{
    static $cache = [];
    return $cache[$shopId] ??= db_all('SELECT * FROM shop_pricing WHERE shop_id = ? AND is_active = 1 ORDER BY id', [$shopId]);
}

function pricing_option_labels(): array
{
    static $l = null;
    if ($l === null) {
        $l = [];
        foreach (db_all('SELECT id, label FROM printing_options') as $o) {
            $l[(int) $o['id']] = $o['label'];
        }
    }
    return $l;
}

/** How many of the rule's filters match the item, or -1 when any filter conflicts. */
function pricing_match(array $rule, array $item): int
{
    $score = 0;
    foreach (['category_id' => 'category_id', 'paper_size_id' => 'paper_size_id', 'color' => 'color', 'sides' => 'sides', 'paper_type_id' => 'paper_type_id', 'gsm_id' => 'gsm_id'] as $rf => $if) {
        if ($rule[$rf] === null || $rule[$rf] === '') {
            continue;
        }
        if ((string) $rule[$rf] !== (string) ($item[$if] ?? '')) {
            return -1;
        }
        $score++;
    }
    return $score;
}

/** Best rule of a type for the item at quantity $qty. */
function pricing_pick(array $rules, string $type, array $item, int $qty, ?int $finishingId = null): ?array
{
    $best = null;
    $bestKey = [-1, -1];
    foreach ($rules as $r) {
        if ($r['rule_type'] !== $type || ($finishingId !== null && (int) $r['finishing_id'] !== $finishingId) || ($finishingId === null && $r['finishing_id'] !== null && $type === 'finishing')) {
            continue;
        }
        if ((int) $r['min_qty'] > $qty) {
            continue;
        }
        $score = pricing_match($r, $item);
        if ($score < 0) {
            continue;
        }
        $key = [(int) $r['min_qty'], $score]; // the highest quantity tier reached wins, then the most specific rule
        if ($key[0] > $bestKey[0] || ($key[0] === $bestKey[0] && $key[1] > $bestKey[1])) {
            $best = $r;
            $bestKey = $key;
        }
    }
    return $best;
}

function pricing_basis(array $item): string
{
    if (!empty($item['subcategory_id'])) {
        $b = db_val('SELECT pricing_basis FROM subcategories WHERE id = ?', [(int) $item['subcategory_id']]);
        if ($b) {
            return $b;
        }
    }
    return (string) (db_val('SELECT pricing_basis FROM categories WHERE id = ?', [(int) ($item['category_id'] ?? 0)]) ?: 'pages');
}

/**
 * Prices one item. $item: category_id, subcategory_id, paper_size_id, color, sides, paper_type_id, gsm_id,
 * finishing (option ids), pages, copies, quantity.
 * Returns ['priced' => bool, 'basis', 'lines' => [[label, amount]], 'subtotal', 'discount', 'total', 'missing' => [..]].
 */
function price_item(int $shopId, array $item): array
{
    $rules = pricing_rules($shopId);
    $labels = pricing_option_labels();
    $basis = pricing_basis($item);
    $pages = max(1, (int) ($item['pages'] ?? 1));
    $copies = max(1, (int) ($item['copies'] ?? 1));
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $double = ($item['sides'] ?? 'single') === 'double';
    $sheets = $basis === 'pages' ? ($double ? (int) ceil($pages / 2) : $pages) * $copies : $quantity;
    $qty = $basis === 'pages' ? $pages * $copies : $quantity;
    $lines = [];
    $missing = [];
    $sum = 0.0;

    $unitAmount = function (array $r) use ($basis, $pages, $copies, $quantity, $sheets): float {
        $p = (float) $r['price'];
        return match ($r['unit']) {
            'per_page' => $basis === 'pages' ? $p * $pages * $copies : $p * $quantity,
            'per_sheet' => $p * $sheets,
            'per_copy' => $basis === 'pages' ? $p * $copies : $p * $quantity,
            'per_item' => $p * $quantity,
            'per_order' => $p,
            default => 0.0,
        };
    };
    $unitText = function (array $r) use ($basis, $pages, $copies, $quantity, $sheets): string {
        $p = money($r['price']);
        return match ($r['unit']) {
            'per_page' => $basis === 'pages' ? "$pages page" . ($pages > 1 ? 's' : '') . " × $copies × $p" : "$quantity × $p",
            'per_sheet' => "$sheets sheet" . ($sheets > 1 ? 's' : '') . " × $p",
            'per_copy' => ($basis === 'pages' ? $copies : $quantity) . " × $p",
            'per_item' => "$quantity × $p",
            default => 'flat',
        };
    };

    $base = pricing_pick($rules, $basis === 'items' ? 'item' : 'print', $item, $qty) ?? ($basis === 'items' ? pricing_pick($rules, 'print', $item, $qty) : null);
    if ($base) {
        $amt = $unitAmount($base);
        $sum += $amt;
        $label = $basis === 'items' ? 'Printing' : (($item['color'] ?? 'bw') === 'color' ? 'Colour printing' : 'B&W printing') . ($double ? ', double-sided' : '');
        $lines[] = ['label' => $label . ' (' . $unitText($base) . ')', 'amount' => round($amt, 2)];
    } else {
        $missing[] = 'printing';
    }
    if (!empty($item['paper_type_id']) || !empty($item['gsm_id'])) {
        $paper = pricing_pick($rules, 'paper', $item, $qty);
        if ($paper && (float) $paper['price'] > 0) {
            $amt = $unitAmount($paper);
            $sum += $amt;
            $what = trim(($labels[(int) ($paper['paper_type_id'] ?? 0)] ?? '') . ' ' . ($labels[(int) ($paper['gsm_id'] ?? 0)] ?? '')) ?: 'Paper';
            $lines[] = ['label' => $what . ' (' . $unitText($paper) . ')', 'amount' => round($amt, 2)];
        }
    }
    foreach (array_unique(array_map('intval', (array) ($item['finishing'] ?? []))) as $fid) {
        $f = pricing_pick($rules, 'finishing', $item, $qty, $fid);
        if (!$f) {
            $missing[] = $labels[$fid] ?? 'finishing';
            continue;
        }
        $amt = $unitAmount($f);
        $sum += $amt;
        $lines[] = ['label' => ($labels[$fid] ?? 'Finishing') . ' (' . $unitText($f) . ')', 'amount' => round($amt, 2)];
    }
    $discount = 0.0;
    $d = pricing_pick($rules, 'discount', $item, $qty);
    if ($d && $sum > 0) {
        $discount = round($sum * min(90, (float) $d['price']) / 100, 2);
        $lines[] = ['label' => 'Quantity discount (' . rtrim(rtrim(number_format((float) $d['price'], 2), '0'), '.') . '%)', 'amount' => -$discount];
    }
    return ['priced' => !$missing, 'basis' => $basis, 'lines' => $lines, 'subtotal' => round($sum, 2), 'discount' => $discount,
        'total' => round($sum - $discount, 2), 'missing' => $missing, 'sheets' => $sheets, 'qty' => $qty];
}

/** Prices a whole order (several items) and applies the shop's minimum order charge. */
function price_order(int $shopId, array $items): array
{
    $settings = shop_settings($shopId);
    $out = ['items' => [], 'priced' => true, 'subtotal' => 0.0, 'minimum_adjustment' => 0.0, 'total' => 0.0];
    foreach ($items as $it) {
        $p = price_item($shopId, $it);
        $out['items'][] = $p;
        $out['priced'] = $out['priced'] && $p['priced'];
        $out['subtotal'] += $p['total'];
    }
    $min = (float) $settings['min_order_charge'];
    if ($out['subtotal'] > 0 && $out['subtotal'] < $min) {
        $out['minimum_adjustment'] = round($min - $out['subtotal'], 2);
    }
    $out['total'] = round($out['subtotal'] + $out['minimum_adjustment'], 2);
    $out['subtotal'] = round($out['subtotal'], 2);
    return $out;
}

/** Human description of a rule for the owner's price list. */
function pricing_rule_text(array $r): string
{
    $l = pricing_option_labels();
    $parts = [];
    if ($r['category_id']) $parts[] = (string) db_val('SELECT name FROM categories WHERE id = ?', [(int) $r['category_id']]);
    if ($r['paper_size_id']) $parts[] = $l[(int) $r['paper_size_id']] ?? '';
    if ($r['color']) $parts[] = $r['color'] === 'color' ? 'Colour' : 'B&W';
    if ($r['sides']) $parts[] = $r['sides'] === 'double' ? 'Double-sided' : 'Single-sided';
    if ($r['paper_type_id']) $parts[] = $l[(int) $r['paper_type_id']] ?? '';
    if ($r['gsm_id']) $parts[] = $l[(int) $r['gsm_id']] ?? '';
    if ($r['finishing_id']) array_unshift($parts, $l[(int) $r['finishing_id']] ?? '');
    return $parts ? implode(' · ', array_filter($parts)) : 'All';
}

function pricing_units(): array
{
    return ['per_page' => 'per page', 'per_sheet' => 'per sheet', 'per_copy' => 'per copy', 'per_item' => 'per piece', 'per_order' => 'per order', 'percent' => '% off'];
}

/** Validates and saves a pricing rule for a shop. Returns error string or null. */
function pricing_rule_save(int $shopId, array $in, ?int $id = null): ?string
{
    $types = ['print', 'paper', 'finishing', 'item', 'discount'];
    $type = (string) ($in['rule_type'] ?? '');
    if (!in_array($type, $types, true)) {
        return 'Choose what this price is for.';
    }
    $opt = function ($v, string $group) {
        $v = (int) $v;
        return $v && db_val('SELECT 1 FROM printing_options WHERE id = ? AND option_group = ?', [$v, $group]) ? $v : null;
    };
    $cat = (int) ($in['category_id'] ?? 0);
    $unit = (string) ($in['unit'] ?? 'per_page');
    if ($type === 'discount') {
        $unit = 'percent';
    } elseif (!isset(pricing_units()[$unit]) || $unit === 'percent') {
        return 'Choose a valid unit.';
    }
    $price = round((float) ($in['price'] ?? -1), 2);
    if ($price < 0 || $price > 1000000 || ($type === 'discount' && $price > 90)) {
        return $type === 'discount' ? 'Discount must be between 0 and 90%.' : 'Enter a valid price in PKR.';
    }
    $data = [
        'shop_id' => $shopId, 'rule_type' => $type, 'category_id' => $cat && db_val('SELECT 1 FROM categories WHERE id = ?', [$cat]) ? $cat : null,
        'paper_size_id' => $opt($in['paper_size_id'] ?? 0, 'paper_size'), 'color' => in_array($in['color'] ?? '', ['bw', 'color'], true) ? $in['color'] : null,
        'sides' => in_array($in['sides'] ?? '', ['single', 'double'], true) ? $in['sides'] : null, 'paper_type_id' => $opt($in['paper_type_id'] ?? 0, 'paper_type'),
        'gsm_id' => $opt($in['gsm_id'] ?? 0, 'gsm'), 'finishing_id' => $type === 'finishing' ? $opt($in['finishing_id'] ?? 0, 'finishing') : null,
        'unit' => $unit, 'price' => $price, 'min_qty' => max(1, min(1000000, (int) ($in['min_qty'] ?? 1))), 'is_active' => isset($in['is_active']) ? (in_array($in['is_active'], [1, '1', true, 'on'], true) ? 1 : 0) : 1,
    ];
    if ($type === 'finishing' && !$data['finishing_id']) {
        return 'Choose the finishing service.';
    }
    if ($id) {
        unset($data['shop_id']);
        db_update('shop_pricing', $data, 'id = ? AND shop_id = ?', [$id, $shopId]);
    } else {
        db_insert('shop_pricing', $data);
    }
    return null;
}
