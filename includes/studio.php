<?php
/**
 * Custom Size Studio — made-to-measure shalwar qameez, shirts and women's suits at ready-made prices.
 *
 * Garment *definitions* (which measurements exist, their limits and how to take them, which style
 * groups the mannequin can draw) live in code below because the mannequin drawing depends on them.
 * Everything a store owner changes — names, extra charges, production time, size charts, which style
 * options are offered and their prices — lives in the database and is edited in Admin » Custom Size Studio.
 */

function studio_definitions(): array
{
    $tip = [
        'height' => 'Stand straight against a wall without shoes. Mark the top of your head and measure from the floor to the mark.',
        'neck' => 'Measure around the base of your neck where a collar sits, keeping one finger between the tape and your neck.',
        'chest' => 'Wrap the tape around the fullest part of your chest, under the arms and across the shoulder blades. Keep it level and breathe normally.',
        'bust' => 'Wrap the tape around the fullest part of the bust, keeping it level across the back.',
        'shoulder' => 'Measure across the back from the edge of one shoulder bone to the other, following the natural curve.',
        'sleeve' => 'From the shoulder edge, run the tape down the outside of a slightly bent arm to the wrist bone.',
        'armhole' => 'Measure around the arm where it joins the shoulder, going under the armpit.',
        'qameez_length' => 'From the highest point of the shoulder (next to the neck) straight down to where you want the qameez to end — usually just below the knee.',
        'kameez_length' => 'From the highest point of the shoulder (next to the neck) straight down to where you want the kameez to end.',
        'shirt_length' => 'From the highest point of the shoulder (next to the neck) down the back to where you want the shirt to end — usually mid-seat.',
        'waist' => 'Measure around your natural waist, just above the navel, keeping the tape comfortably loose.',
        'hip' => 'Measure around the widest part of your hips and seat with feet together.',
        'shalwar_length' => 'From your waist (where you tie the shalwar / pajama) straight down to your ankle bone.',
        'trouser_length' => 'From your waist straight down the outside of the leg to your ankle bone.',
        'bottom' => 'Measure the width you want at the ankle opening (paincha), laid flat. Shalwar is usually 8–10", pajama 6–8".',
    ];
    $m = fn(string $label, float $min, float $max) => ['label' => $label, 'min' => $min, 'max' => $max];
    return [
        'mens_shalwar_qameez' => [
            'gender' => 'men', 'base' => 'kameez',
            'measurements' => [
                'height' => $m('Your height', 54, 84) + ['help' => $tip['height']],
                'neck' => $m('Collar / neck', 12, 22) + ['help' => $tip['neck']],
                'chest' => $m('Chest', 30, 64) + ['help' => $tip['chest']],
                'shoulder' => $m('Shoulder', 14, 24) + ['help' => $tip['shoulder']],
                'sleeve' => $m('Sleeve length', 18, 30) + ['help' => $tip['sleeve']],
                'qameez_length' => $m('Qameez length', 30, 50) + ['help' => $tip['qameez_length']],
                'waist' => $m('Waist', 26, 60) + ['help' => $tip['waist']],
                'hip' => $m('Hip / daman', 30, 66) + ['help' => $tip['hip']],
                'shalwar_length' => $m('Shalwar / pajama length', 32, 46) + ['help' => $tip['shalwar_length']],
                'bottom' => $m('Bottom (paincha) width', 5, 14) + ['help' => $tip['bottom']],
            ],
            'groups' => ['collar' => 'Collar', 'cuff' => 'Cuffs', 'daman' => 'Daman (hem)', 'pocket' => 'Pockets', 'bottom' => 'Bottom'],
        ],
        'mens_shirt' => [
            'gender' => 'men', 'base' => 'shirt',
            'measurements' => [
                'height' => $m('Your height', 54, 84) + ['help' => $tip['height']],
                'neck' => $m('Collar / neck', 12, 22) + ['help' => $tip['neck']],
                'chest' => $m('Chest', 30, 64) + ['help' => $tip['chest']],
                'shoulder' => $m('Shoulder', 14, 24) + ['help' => $tip['shoulder']],
                'sleeve' => $m('Sleeve length', 18, 30) + ['help' => $tip['sleeve']],
                'shirt_length' => $m('Shirt length', 24, 38) + ['help' => $tip['shirt_length']],
                'waist' => $m('Waist', 26, 60) + ['help' => $tip['waist']],
            ],
            'groups' => ['collar' => 'Collar', 'cuff' => 'Cuffs', 'pocket' => 'Pocket', 'fit' => 'Fit'],
        ],
        'womens_shalwar_kameez' => [
            'gender' => 'women', 'base' => 'kameez',
            'measurements' => [
                'height' => $m('Your height', 54, 78) + ['help' => $tip['height']],
                'bust' => $m('Bust', 28, 60) + ['help' => $tip['bust']],
                'shoulder' => $m('Shoulder', 12, 20) + ['help' => $tip['shoulder']],
                'armhole' => $m('Armhole', 6, 14) + ['help' => $tip['armhole']],
                'sleeve' => $m('Sleeve length', 4, 26) + ['help' => $tip['sleeve']],
                'kameez_length' => $m('Kameez length', 28, 56) + ['help' => $tip['kameez_length']],
                'waist' => $m('Waist', 22, 56) + ['help' => $tip['waist']],
                'hip' => $m('Hip', 30, 64) + ['help' => $tip['hip']],
                'trouser_length' => $m('Shalwar / trouser length', 30, 44) + ['help' => $tip['trouser_length']],
                'bottom' => $m('Bottom (paincha) width', 4, 16) + ['help' => $tip['bottom']],
            ],
            'groups' => ['neck' => 'Neckline', 'sleeve' => 'Sleeves', 'daman' => 'Daman (hem)', 'bottom' => 'Bottom'],
        ],
    ];
}

/** Drawing keys the mannequin understands per style group (admin chooses from these). */
function studio_drawable_options(): array
{
    return [
        'collar' => ['band' => 'Band (ban) collar', 'shirt' => 'Shirt collar', 'sherwani' => 'Sherwani collar', 'round' => 'Round neck (no collar)',
                     'classic' => 'Classic point collar', 'cutaway' => 'Cutaway collar', 'button_down' => 'Button-down collar', 'mandarin' => 'Mandarin collar'],
        'cuff' => ['plain' => 'Plain sleeve', 'round' => 'Round cuff', 'square' => 'Square cuff', 'french' => 'French (double) cuff', 'button' => 'Button cuff'],
        'daman' => ['straight' => 'Straight', 'round' => 'Round (curved)', 'a_line' => 'A-line flare'],
        'pocket' => ['none' => 'No pocket', 'chest' => 'Chest pocket', 'side' => 'Side pockets', 'both' => 'Chest + side pockets'],
        'bottom' => ['shalwar' => 'Shalwar', 'pajama' => 'Pajama', 'trouser' => 'Trouser', 'palazzo' => 'Palazzo', 'churidar' => 'Churidar'],
        'fit' => ['slim' => 'Slim fit', 'regular' => 'Regular fit', 'relaxed' => 'Relaxed fit'],
        'neck' => ['round' => 'Round neck', 'v_neck' => 'V-neck', 'boat' => 'Boat neck', 'band' => 'Band collar', 'sweetheart' => 'Sweetheart'],
        'sleeve' => ['full' => 'Full sleeves', 'three_quarter' => '3/4 sleeves', 'short' => 'Short sleeves', 'sleeveless' => 'Sleeveless'],
    ];
}

function studio_garment(int $id, bool $activeOnly = true): ?array
{
    $g = db_row('SELECT * FROM custom_garments WHERE id = ?' . ($activeOnly ? ' AND is_active = 1' : ''), [$id]);
    if (!$g || !isset(studio_definitions()[$g['code']])) {
        return null;
    }
    return $g + ['def' => studio_definitions()[$g['code']]];
}

function studio_garments(bool $activeOnly = true): array
{
    $rows = db_all('SELECT * FROM custom_garments' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, id');
    return array_values(array_filter($rows, fn($g) => isset(studio_definitions()[$g['code']])));
}

/** Active style options grouped: ['collar' => [['value_key'=>'band','label'=>..,'extra_price'=>..,'is_default'=>1], ...]] */
function studio_options(int $garmentId, bool $activeOnly = true): array
{
    $out = [];
    foreach (db_all('SELECT * FROM custom_style_options WHERE garment_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY option_group, sort_order, id', [$garmentId]) as $o) {
        $out[$o['option_group']][] = $o;
    }
    return $out;
}

/** ['S' => ['chest' => 40.0, ...], 'M' => [...]] in size order. */
function studio_chart(int $garmentId): array
{
    $order = array_flip(['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL']);
    $chart = [];
    foreach (db_all('SELECT size_label, measure_key, value FROM custom_size_chart WHERE garment_id = ?', [$garmentId]) as $r) {
        $chart[$r['size_label']][$r['measure_key']] = (float) $r['value'];
    }
    uksort($chart, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
    return $chart;
}

function studio_fit_table(int $garmentId): array
{
    return db_all('SELECT * FROM custom_size_fit WHERE garment_id = ? ORDER BY sort_order, id', [$garmentId]);
}

/**
 * Suggests a size from height (inches) and weight (kg). $fit: slim | regular | loose.
 * Returns ['size' => 'M', 'note' => '...'] or null when the garment has no fit table.
 */
function studio_recommend(int $garmentId, float $heightIn, float $weightKg, string $fit = 'regular'): ?array
{
    $rows = studio_fit_table($garmentId);
    if (!$rows || $heightIn <= 0 || $weightKg <= 0) {
        return null;
    }
    $best = null;
    $bestScore = PHP_FLOAT_MAX;
    foreach ($rows as $i => $r) {
        $hMid = ($r['height_min_in'] + $r['height_max_in']) / 2;
        $wMid = ($r['weight_min_kg'] + $r['weight_max_kg']) / 2;
        // Weight matters more than height for girth; height decides lengths (which are adjusted anyway).
        $score = abs($weightKg - $wMid) / max(1, $r['weight_max_kg'] - $r['weight_min_kg']) * 2
            + abs($heightIn - $hMid) / max(1, $r['height_max_in'] - $r['height_min_in']);
        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $i;
        }
    }
    if ($fit === 'loose' && isset($rows[$best + 1])) {
        $best++;
    } elseif ($fit === 'slim' && $best > 0 && $weightKg < $rows[$best]['weight_min_kg'] + 3) {
        $best--;
    }
    $size = $rows[$best]['size_label'];
    $note = 'We pre-filled ' . $size . ' measurements. Lengths are adjusted to your height — check chest and shoulder with a tape for the best fit.';
    return ['size' => $size, 'note' => $note];
}

/**
 * Measurements for a standard size with lengths scaled to the customer's height.
 */
function studio_size_measurements(int $garmentId, string $size, ?float $heightIn = null): array
{
    $chart = studio_chart($garmentId)[$size] ?? [];
    if ($heightIn && !empty($chart['height'])) {
        $ratio = $heightIn / $chart['height'];
        foreach (['sleeve', 'qameez_length', 'kameez_length', 'shirt_length', 'shalwar_length', 'trouser_length'] as $k) {
            if (isset($chart[$k])) {
                $chart[$k] = round($chart[$k] * $ratio * 2) / 2;
            }
        }
        $chart['height'] = $heightIn;
    }
    return $chart;
}

/** Colours offered for a product: its own Color variant values, otherwise the master colour list. */
function studio_colors(array $product): array
{
    $rows = db_all(
        "SELECT DISTINCT vv.option_value AS name, COALESCE(c.hex_code, '#cccccc') AS hex FROM product_variants v
         JOIN product_variant_values vv ON vv.variant_id = v.id AND vv.option_name = 'Color'
         LEFT JOIN colors c ON c.name = vv.option_value WHERE v.product_id = ? AND v.is_active = 1 ORDER BY vv.sort_order",
        [(int) $product['id']]
    );
    return $rows ?: db_all('SELECT name, hex_code AS hex FROM colors ORDER BY sort_order, name');
}

/**
 * Validates studio input for a product. Never trusts prices or labels from the browser.
 * Returns ['ok' => bool, 'errors' => [], 'spec' => [...], 'extra' => float, 'garment' => row]
 */
function studio_validate(array $product, array $in): array
{
    $errors = [];
    $garment = $product['custom_garment_id'] ? studio_garment((int) $product['custom_garment_id']) : null;
    if (!$garment) {
        return ['ok' => false, 'errors' => ['Custom sizing is not available for this product.'], 'spec' => null, 'extra' => 0.0, 'garment' => null];
    }
    $def = $garment['def'];

    $colorNames = array_column(studio_colors($product), 'name');
    $color = trim((string) ($in['color'] ?? ''));
    if (!in_array($color, $colorNames, true)) {
        $errors[] = 'Please choose a colour.';
    }

    $options = studio_options((int) $garment['id']);
    $styles = [];
    $extra = (float) $garment['fee'];
    foreach ($def['groups'] as $group => $label) {
        if (empty($options[$group])) {
            continue;
        }
        $chosen = (string) ($in['style'][$group] ?? '');
        $match = null;
        foreach ($options[$group] as $o) {
            if ($o['value_key'] === $chosen) {
                $match = $o;
            }
        }
        if (!$match) {
            $errors[] = 'Please choose a ' . strtolower($label) . ' style.';
            continue;
        }
        $styles[$group] = $match['value_key'];
        $extra += (float) $match['extra_price'];
    }

    $measurements = [];
    foreach ($def['measurements'] as $key => $f) {
        $raw = $in['m'][$key] ?? '';
        if (!is_numeric($raw)) {
            $errors[] = $f['label'] . ' is required.';
            continue;
        }
        $v = round((float) $raw * 2) / 2; // half-inch precision
        if ($v < $f['min'] || $v > $f['max']) {
            $errors[] = $f['label'] . ' must be between ' . $f['min'] . '" and ' . $f['max'] . '".';
            continue;
        }
        $measurements[$key] = $v;
    }
    // Basic sanity checks that catch common mistakes (e.g. entering half-chest)
    $girth = $measurements['chest'] ?? ($measurements['bust'] ?? null);
    if ($girth && isset($measurements['shoulder']) && $girth < $measurements['shoulder'] * 1.6) {
        $errors[] = 'Chest looks too small compared to shoulder — please measure all the way around the body (not across the front only).';
    }
    $chart = studio_chart((int) $garment['id']);
    $baseSize = (string) ($in['base_size'] ?? '');
    if ($baseSize !== '' && !isset($chart[$baseSize])) {
        $baseSize = '';
    }
    $notes = mb_substr(trim(strip_tags((string) ($in['notes'] ?? ''))), 0, 500);

    $spec = [
        'v' => 1,
        'garment' => $garment['code'],
        'garment_id' => (int) $garment['id'],
        'base_size' => $baseSize,
        'color' => $color,
        'styles' => $styles,
        'measurements' => $measurements,
        'unit' => 'in',
        'notes' => $notes,
    ];
    return ['ok' => !$errors, 'errors' => $errors, 'spec' => $spec, 'extra' => round($extra, 2), 'garment' => $garment];
}

/** Recomputes the extra charge of a stored spec from current database prices. */
function studio_spec_extra(array $spec): float
{
    $g = db_row('SELECT * FROM custom_garments WHERE id = ?', [(int) ($spec['garment_id'] ?? 0)]);
    if (!$g) {
        return 0.0;
    }
    $extra = (float) $g['fee'];
    foreach ((array) ($spec['styles'] ?? []) as $group => $key) {
        $extra += (float) db_val('SELECT extra_price FROM custom_style_options WHERE garment_id = ? AND option_group = ? AND value_key = ?', [(int) $g['id'], (string) $group, (string) $key]);
    }
    return round($extra, 2);
}

function studio_spec_lead_days(array $spec): int
{
    return (int) db_val('SELECT production_days_max FROM custom_garments WHERE id = ?', [(int) ($spec['garment_id'] ?? 0)]);
}

/** Human-readable lines for cart, order, invoice and emails. */
function studio_summary(array $spec): array
{
    $defs = studio_definitions();
    $def = $defs[$spec['garment'] ?? ''] ?? null;
    $lines = [];
    if (!$def) {
        return $lines;
    }
    $labels = [];
    foreach (db_all('SELECT option_group, value_key, label FROM custom_style_options WHERE garment_id = ?', [(int) $spec['garment_id']]) as $o) {
        $labels[$o['option_group']][$o['value_key']] = $o['label'];
    }
    $lines['Colour'] = $spec['color'];
    if (!empty($spec['base_size'])) {
        $lines['Based on size'] = $spec['base_size'];
    }
    foreach ((array) $spec['styles'] as $group => $key) {
        $lines[$def['groups'][$group] ?? ucfirst($group)] = $labels[$group][$key] ?? $key;
    }
    $m = [];
    foreach ((array) $spec['measurements'] as $k => $v) {
        $m[] = ($def['measurements'][$k]['label'] ?? $k) . ' ' . rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '"';
    }
    $lines['Measurements'] = implode(', ', $m);
    if (!empty($spec['notes'])) {
        $lines['Notes'] = $spec['notes'];
    }
    return $lines;
}

function studio_summary_text(array $spec): string
{
    return implode(' · ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys(studio_summary($spec)), studio_summary($spec)));
}

/** Saved measurement profiles of the signed-in customer for a garment. */
function studio_saved_profiles(string $garmentCode): array
{
    $cid = customer_id();
    if (!$cid) {
        return [];
    }
    $rows = db_all('SELECT id, profile_name, data, updated_at, created_at FROM customer_measurements WHERE customer_id = ? AND garment_code = ? ORDER BY COALESCE(updated_at, created_at) DESC', [$cid, $garmentCode]);
    foreach ($rows as &$r) {
        $r['data'] = json_decode($r['data'], true) ?: [];
    }
    return $rows;
}

function studio_save_profile(string $garmentCode, string $name, array $measurements): void
{
    $cid = customer_id();
    if (!$cid || !$measurements) {
        return;
    }
    $name = mb_substr(trim($name), 0, 60) ?: 'My measurements';
    $existing = db_val('SELECT id FROM customer_measurements WHERE customer_id = ? AND garment_code = ? AND profile_name = ?', [$cid, $garmentCode, $name]);
    if ($existing) {
        db_exec('UPDATE customer_measurements SET data = ? WHERE id = ?', [json_encode($measurements), (int) $existing]);
    } else {
        db_insert('customer_measurements', ['customer_id' => $cid, 'garment_code' => $garmentCode, 'profile_name' => $name, 'data' => json_encode($measurements)]);
    }
}

/** Products that can be ordered through the studio. */
function studio_products(int $limit = 24, ?int $garmentId = null): array
{
    $sql = "SELECT p.* FROM products p JOIN custom_garments g ON g.id = p.custom_garment_id AND g.is_active = 1 WHERE p.status = 'published'";
    $params = [];
    if ($garmentId) {
        $sql .= ' AND p.custom_garment_id = ?';
        $params[] = $garmentId;
    }
    return hydrate_cards(db_all($sql . ' ORDER BY p.is_featured DESC, p.id DESC LIMIT ' . (int) $limit, $params));
}
