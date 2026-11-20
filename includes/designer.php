<?php
/**
 * Design Studio: customers design their own T-shirt / hoodie (images + text on the front and back),
 * preview it in 3D and either add it to the bag at an automatic price or request a quote that the
 * store confirms. Blanks are ordinary products with products.design_type set; the garment price and
 * stock come from the product's Color/Size variants, the print price from the settings below.
 * Every price is recalculated on the server from the saved design — nothing priced by the browser is trusted.
 */

/** Text fonts: name => [CSS font stack, weight]. Loaded from Google Fonts on the Design Studio page. */
const UC_DESIGN_FONTS = [
    'Montserrat' => ['Montserrat, Arial, sans-serif', 800], 'Anton' => ['Anton, Impact, sans-serif', 400],
    'Bebas Neue' => ['"Bebas Neue", Impact, sans-serif', 400], 'Playfair Display' => ['"Playfair Display", Georgia, serif', 700],
    'Pacifico' => ['Pacifico, "Brush Script MT", cursive', 400], 'Permanent Marker' => ['"Permanent Marker", "Comic Sans MS", cursive', 400],
    'Courier Prime' => ['"Courier Prime", "Courier New", monospace', 700],
];

/** Blank garment types. Print areas are in the 600 × 700 drawing space used by assets/js/designer.js. */
function design_types(): array
{
    return [
        'tshirt' => ['label' => 'Regular T-shirt', 'short' => 'Regular tee', 'area_cm' => ['front' => [30, 40], 'back' => [30, 40]]],
        'oversized' => ['label' => 'Oversized T-shirt', 'short' => 'Oversized tee', 'area_cm' => ['front' => [32, 42], 'back' => [32, 42]]],
        'hoodie' => ['label' => 'Pullover Hoodie', 'short' => 'Hoodie', 'area_cm' => ['front' => [28, 26], 'back' => [32, 38]]],
    ];
}

function design_enabled(): bool
{
    return setting_bool('design_enabled', true);
}

/** 'auto' = price shown and added to bag instantly; 'quote' = the store confirms the price first. */
function design_mode(): string
{
    return setting('design_pricing_mode', 'auto') === 'quote' ? 'quote' : 'auto';
}

function design_pricing(): array
{
    return [
        'small' => max(0, (float) setting('design_price_small', 300)),
        'medium' => max(0, (float) setting('design_price_medium', 500)),
        'large' => max(0, (float) setting('design_price_large', 800)),
        'text' => max(0, (float) setting('design_price_text', 150)),
        'small_max' => min(100, max(1, (float) setting('design_small_max', 12))),
        'medium_max' => min(100, max(1, (float) setting('design_medium_max', 35))),
        'multi_discount' => min(90, max(0, (float) setting('design_multi_discount', 10))),
        'both_sides_fee' => max(0, (float) setting('design_both_sides_fee', 0)),
        'min_print' => max(0, (float) setting('design_min_print', 0)),
    ];
}

function design_max_elements(): int
{
    return max(1, min(20, (int) setting('design_max_elements', 8)));
}

function design_production_days(): int
{
    return max(0, (int) setting('design_production_days', 3));
}

function design_ai_bg_enabled(): bool
{
    return (string) decrypt_secret(setting('design_removebg_key', '')) !== '';
}

/** Size class of an element from the share of the print area it covers (rotation ignored). */
function design_size_class(array $el, ?array $pricing = null): string
{
    $pricing = $pricing ?? design_pricing();
    $pct = max(0, min(1, (float) $el['w'])) * max(0, min(1, (float) $el['h'])) * 100;
    return $pct <= $pricing['small_max'] ? 'small' : ($pct <= $pricing['medium_max'] ? 'medium' : 'large');
}

/**
 * Print price per piece for a list of validated elements.
 * Images are priced by size, text per line; 2+ images get the multi-image discount;
 * printing on both sides adds the both-sides fee; the minimum print charge applies last.
 */
function design_price(array $elements): array
{
    $pr = design_pricing();
    $lines = [];
    $imgTotal = $textTotal = 0.0;
    $images = $texts = 0;
    $sides = [];
    foreach ($elements as $el) {
        $sides[$el['side']] = true;
        if ($el['kind'] === 'text') {
            $texts++;
            $textTotal += $pr['text'];
            $lines[] = ['side' => $el['side'], 'kind' => 'text', 'label' => 'Text “' . mb_strimwidth((string) $el['text'], 0, 24, '…') . '”', 'price' => $pr['text']];
        } else {
            $images++;
            $cls = design_size_class($el, $pr);
            $imgTotal += $pr[$cls];
            $lines[] = ['side' => $el['side'], 'kind' => 'image', 'size' => $cls, 'label' => ucfirst($cls) . ' image', 'price' => $pr[$cls]];
        }
    }
    $discount = $images >= 2 ? round($imgTotal * $pr['multi_discount'] / 100, 2) : 0.0;
    $sidesFee = count($sides) > 1 ? $pr['both_sides_fee'] : 0.0;
    $total = $imgTotal + $textTotal - $discount + $sidesFee;
    $minApplied = false;
    if ($elements && $total < $pr['min_print']) {
        $total = $pr['min_print'];
        $minApplied = true;
    }
    return ['lines' => $lines, 'images' => $images, 'texts' => $texts, 'discount' => $discount, 'sides_fee' => $sidesFee,
        'min_applied' => $minApplied, 'total' => round($total, 2)];
}

// ---------------------------------------------------------------------
//  Ownership: a random cookie ties uploads and designs to the visitor
// ---------------------------------------------------------------------
function design_owner_key(bool $create = true): ?string
{
    $c = $_COOKIE['uc_dz'] ?? '';
    if (!preg_match('/^[a-f0-9]{40}$/', $c)) {
        if (!$create) {
            return null;
        }
        $c = random_token(20);
        setcookie('uc_dz', $c, ['expires' => time() + 86400 * 180, 'path' => (BASE_PATH ?: '') . '/', 'secure' => uc_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['uc_dz'] = $c;
    }
    return hash('sha256', $c);
}

// ---------------------------------------------------------------------
//  Blanks
// ---------------------------------------------------------------------
/** Published blank products with colours, sizes and stock per colour/size. */
function design_blanks(): array
{
    $types = design_types();
    $rows = db_all("SELECT * FROM products WHERE status = 'published' AND design_type IS NOT NULL AND design_type <> '' ORDER BY FIELD(design_type, 'tshirt', 'oversized', 'hoodie'), id");
    $out = [];
    foreach ($rows as $p) {
        if (!isset($types[$p['design_type']])) {
            continue;
        }
        $colors = $sizes = $stock = [];
        foreach (product_variants((int) $p['id']) as $v) {
            $c = $v['options']['Color'] ?? null;
            $s = $v['options']['Size'] ?? null;
            if (!$c || !$s) {
                continue;
            }
            $colors[$c] = true;
            $sizes[$s] = true;
            $stock[$c][$s] = available_qty($p, $v);
        }
        if (!$colors) {
            continue;
        }
        $hex = [];
        foreach (db_all('SELECT name, hex_code FROM colors WHERE name IN (' . db_in(array_keys($colors)) . ')', array_keys($colors)) as $c) {
            $hex[$c['name']] = safe_color($c['hex_code'], '#cccccc');
        }
        $order = size_option_order();
        $sizeList = array_keys($sizes);
        usort($sizeList, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        $pi = price_info($p);
        $out[] = [
            'id' => (int) $p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'type' => $p['design_type'], 'label' => $types[$p['design_type']]['label'],
            'price' => $pi['final'], 'regular' => $pi['regular'], 'area_cm' => $types[$p['design_type']]['area_cm'],
            'colors' => array_map(fn($c) => ['name' => $c, 'hex' => $hex[$c] ?? '#cccccc'], array_keys($colors)),
            'sizes' => $sizeList, 'stock' => $stock, 'url' => url('product/' . $p['slug']),
        ];
    }
    return $out;
}

function size_option_order(): array
{
    return array_flip(['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL']);
}

function design_blank_product(int $id): ?array
{
    return db_row("SELECT * FROM products WHERE id = ? AND status = 'published' AND design_type IS NOT NULL AND design_type <> ''", [$id]);
}

function design_variant(int $productId, string $color, string $size): ?array
{
    foreach (product_variants($productId) as $v) {
        if (($v['options']['Color'] ?? null) === $color && ($v['options']['Size'] ?? null) === $size) {
            return $v;
        }
    }
    return null;
}

// ---------------------------------------------------------------------
//  Uploads
// ---------------------------------------------------------------------
function design_asset_save(string $path, string $owner, bool $bgRemoved = false, ?int $sourceId = null): array
{
    $full = UC_ROOT . '/uploads/' . $path;
    $info = @getimagesize($full) ?: [0, 0];
    $token = bin2hex(random_bytes(16));
    $id = db_insert('design_assets', ['token' => $token, 'owner_key' => $owner, 'image' => $path, 'width' => (int) $info[0], 'height' => (int) $info[1],
        'bg_removed' => $bgRemoved ? 1 : 0, 'source_id' => $sourceId]);
    return ['id' => $id, 'token' => $token, 'url' => img_url($path), 'w' => (int) $info[0], 'h' => (int) $info[1], 'bg_removed' => $bgRemoved];
}

function design_asset(string $token, ?string $owner = null): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $a = db_row('SELECT * FROM design_assets WHERE token = ?', [$token]);
    return $a && ($owner === null || hash_equals($a['owner_key'], $owner)) ? $a : null;
}

/** Stores image bytes that did not come from a form upload (remove.bg result, rendered preview). */
function design_store_bytes(string $bytes, int $maxWidth): string
{
    if (strlen($bytes) > 12 * 1024 * 1024) {
        throw new RuntimeException('Image is too large.');
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !isset(UC_IMAGE_MIMES[$info['mime']]) || $info[0] * $info[1] > 40000000) {
        throw new RuntimeException('The image could not be processed.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'ucd');
    file_put_contents($tmp, $bytes);
    try {
        return store_image_file($tmp, $info['mime'], $maxWidth);
    } finally {
        @unlink($tmp);
    }
}

/** Background removal with remove.bg. The API key stays on the server and is never sent to the browser. */
function design_remove_bg_ai(array $asset, string $owner): array
{
    $key = (string) decrypt_secret(setting('design_removebg_key', ''));
    if ($key === '' || !function_exists('curl_init')) {
        throw new RuntimeException('Automatic background removal is not available. Use Quick remove instead.');
    }
    $file = UC_ROOT . '/uploads/' . $asset['image'];
    $ch = curl_init('https://api.remove.bg/v1.0/removebg');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $key],
        CURLOPT_POSTFIELDS => ['image_file' => new CURLFile($file), 'size' => 'auto', 'format' => 'png'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) {
        error_log('remove.bg failed: HTTP ' . $code . ' ' . substr((string) $body, 0, 200));
        throw new RuntimeException('Background removal failed. Please try Quick remove.');
    }
    $path = design_store_bytes($body, 3000);
    return design_asset_save($path, $owner, true, (int) $asset['id']);
}

// ---------------------------------------------------------------------
//  Designs
// ---------------------------------------------------------------------
/**
 * Validates a design posted by the editor. $elementsJson is the editor's element list;
 * positions are fractions of the print area (cx/cy = centre, w/h = size).
 */
function design_validate(int $productId, string $color, string $elementsJson, string $owner): array
{
    $errors = [];
    $product = design_blank_product($productId);
    if (!$product) {
        return ['ok' => false, 'errors' => ['Please choose a T-shirt or hoodie.']];
    }
    $colors = [];
    foreach (product_variants($productId) as $v) {
        if (isset($v['options']['Color'])) {
            $colors[$v['options']['Color']] = true;
        }
    }
    if (!isset($colors[$color])) {
        $errors[] = 'Please choose a colour.';
    }
    $raw = json_decode($elementsJson, true);
    if (!is_array($raw) || !$raw) {
        return ['ok' => false, 'errors' => array_merge($errors, ['Add at least one image or text to your design.'])];
    }
    if (count($raw) > design_max_elements()) {
        $errors[] = 'A design can have up to ' . design_max_elements() . ' images and texts.';
    }
    $num = fn($v, $min, $max) => is_numeric($v) && is_finite((float) $v) ? max($min, min($max, round((float) $v, 4))) : null;
    $els = [];
    foreach (array_slice($raw, 0, design_max_elements()) as $e) {
        if (!is_array($e)) {
            continue;
        }
        $side = ($e['side'] ?? '') === 'back' ? 'back' : 'front';
        $el = ['side' => $side, 'cx' => $num($e['cx'] ?? null, -0.25, 1.25), 'cy' => $num($e['cy'] ?? null, -0.25, 1.25),
            'w' => $num($e['w'] ?? null, 0.02, 1.6), 'h' => $num($e['h'] ?? null, 0.02, 1.6), 'rot' => $num($e['rot'] ?? 0, -360, 360) ?? 0.0];
        if (in_array(null, [$el['cx'], $el['cy'], $el['w'], $el['h']], true)) {
            $errors[] = 'One of the design elements has an invalid position.';
            continue;
        }
        if (($e['kind'] ?? '') === 'text') {
            $text = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($e['text'] ?? '')));
            $font = (string) ($e['font'] ?? 'Montserrat');
            if ($text === '' || mb_strlen($text) > 40) {
                $errors[] = 'Text must be 1–40 characters.';
                continue;
            }
            $el += ['kind' => 'text', 'text' => $text, 'font' => isset(UC_DESIGN_FONTS[$font]) ? $font : 'Montserrat',
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? '')) ? strtoupper($e['color']) : '#111111'];
        } else {
            $asset = design_asset((string) ($e['asset'] ?? ''), $owner);
            if (!$asset) {
                $errors[] = 'One of your images could not be found. Please upload it again.';
                continue;
            }
            $el += ['kind' => 'image', 'asset' => $asset['token'], 'image' => $asset['image'], 'bg_removed' => (int) $asset['bg_removed'], 'flip' => !empty($e['flip'])];
        }
        $els[] = $el;
    }
    if (!$els && !$errors) {
        $errors[] = 'Add at least one image or text to your design.';
    }
    return ['ok' => !$errors, 'errors' => array_values(array_unique($errors)), 'product' => $product, 'color' => $color, 'elements' => $els];
}

/** Rendered preview (data:image/png;base64,…) from the editor, re-encoded on the server. */
function design_store_preview(?string $dataUrl): ?string
{
    if (!$dataUrl || !preg_match('#^data:image/(png|webp|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        return null;
    }
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || strlen($bytes) > 4 * 1024 * 1024) {
        return null;
    }
    try {
        return design_store_bytes($bytes, 1200);
    } catch (RuntimeException $e) {
        return null;
    }
}

function design_save(array $v, string $owner, ?string $previewFront, ?string $previewBack): array
{
    $token = random_token(20);
    $price = design_price($v['elements']);
    db_insert('designs', [
        'token' => $token, 'owner_key' => $owner, 'customer_id' => customer_id(), 'product_id' => (int) $v['product']['id'], 'color' => $v['color'],
        'spec' => json_encode(['type' => $v['product']['design_type'], 'elements' => $v['elements']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'preview_front' => design_store_preview($previewFront), 'preview_back' => design_store_preview($previewBack),
        'print_price' => $price['total'], 'ip_address' => client_ip(),
    ]);
    return design_by_token($token);
}

function design_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    $d = db_row('SELECT * FROM designs WHERE token = ?', [$token]);
    if ($d) {
        $d['spec_data'] = json_decode((string) $d['spec'], true) ?: ['type' => 'tshirt', 'elements' => []];
    }
    return $d;
}

/** Short human summary, e.g. ['Garment' => 'Oversized T-shirt', 'Front' => '2 images, 1 text', …] */
function design_summary(array $design, ?array $request = null): array
{
    $spec = $design['spec_data'] ?? (json_decode((string) $design['spec'], true) ?: []);
    $count = ['front' => ['image' => 0, 'text' => 0], 'back' => ['image' => 0, 'text' => 0]];
    foreach ($spec['elements'] ?? [] as $el) {
        $count[$el['side']][$el['kind']]++;
    }
    $fmt = function (array $c): string {
        $parts = [];
        if ($c['image']) {
            $parts[] = $c['image'] . ' image' . ($c['image'] > 1 ? 's' : '');
        }
        if ($c['text']) {
            $parts[] = $c['text'] . ' text' . ($c['text'] > 1 ? 's' : '');
        }
        return $parts ? implode(', ', $parts) : '—';
    };
    $out = ['Garment' => design_types()[$spec['type'] ?? 'tshirt']['label'] ?? 'T-shirt', 'Colour' => $design['color'], 'Front' => $fmt($count['front']), 'Back' => $fmt($count['back'])];
    if ($request) {
        $out['Quote'] = $request['reference'];
    }
    return $out;
}

/**
 * Adds a saved design to the bag: one line per size, on the blank's Color/Size variant (blank stock applies).
 * With a quoted request the confirmed print price is used and quantities are fixed.
 */
function cart_add_design(array $design, array $sizes, ?array $request = null): array
{
    $product = design_blank_product((int) $design['product_id']);
    if (!$product) {
        return ['ok' => false, 'message' => 'This garment is no longer available.'];
    }
    $sizes = array_filter(array_map('intval', $sizes), fn($q) => $q > 0);
    if (!$sizes) {
        return ['ok' => false, 'message' => 'Choose a size and quantity.'];
    }
    $plan = [];
    foreach ($sizes as $size => $qty) {
        $variant = design_variant((int) $product['id'], $design['color'], (string) $size);
        if (!$variant) {
            return ['ok' => false, 'message' => 'Size ' . $size . ' is not available in ' . $design['color'] . '.'];
        }
        $available = available_qty($product, $variant);
        if ($qty > $available) {
            return ['ok' => false, 'message' => $available > 0 ? 'Only ' . $available . ' left in size ' . $size . '.' : 'Size ' . $size . ' is out of stock in ' . $design['color'] . '.'];
        }
        $plan[] = [$variant, min(UC_CART_MAX_QTY, $qty)];
    }
    $cartId = cart_id(true);
    $summary = design_summary($design, $request);
    foreach ($plan as [$variant, $qty]) {
        $spec = ['type' => 'design', 'design' => $design['token'], 'request' => $request ? (int) $request['id'] : null];
        $json = json_encode($spec);
        $hash = md5($json . '|' . $variant['id']);
        $existing = db_row('SELECT id FROM cart_items WHERE cart_id = ? AND custom_hash = ? AND is_custom = 2', [$cartId, $hash]);
        if ($existing) {
            db_exec('UPDATE cart_items SET qty = ? WHERE id = ?', [$request ? $qty : min(UC_CART_MAX_QTY, $qty + (int) db_val('SELECT qty FROM cart_items WHERE id = ?', [(int) $existing['id']])), (int) $existing['id']]);
        } else {
            db_insert('cart_items', ['cart_id' => $cartId, 'product_id' => (int) $product['id'], 'variant_id' => (int) $variant['id'], 'qty' => $qty,
                'is_custom' => 2, 'custom_spec' => $json, 'custom_color' => $design['color'],
                'custom_measurements' => mb_substr(implode(' · ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($summary), $summary)), 0, 600),
                'custom_hash' => $hash]);
        }
    }
    db_exec("UPDATE designs SET status = IF(status = 'draft', 'in_cart', status) WHERE id = ?", [(int) $design['id']]);
    return ['ok' => true, 'message' => 'Your ' . strtolower(design_types()[$product['design_type']]['short']) . ' design was added to your bag.'];
}

/** Print price per piece for a design line in the bag, or an error when it cannot be bought as is. */
function design_line_pricing(array $spec): array
{
    $design = design_by_token((string) ($spec['design'] ?? ''));
    if (!$design) {
        return ['error' => 'This design is no longer available', 'fee' => 0.0, 'design' => null, 'request' => null];
    }
    if (!empty($spec['request'])) {
        $r = db_row('SELECT * FROM design_requests WHERE id = ? AND design_id = ?', [(int) $spec['request'], (int) $design['id']]);
        if (!$r || !in_array($r['status'], ['quoted', 'ordered'], true) || $r['quoted_print_price'] === null) {
            return ['error' => 'This quote is no longer valid', 'fee' => 0.0, 'design' => $design, 'request' => $r];
        }
        return ['error' => null, 'fee' => (float) $r['quoted_print_price'], 'design' => $design, 'request' => $r];
    }
    if (design_mode() === 'quote') {
        return ['error' => 'Custom designs now need a price confirmation — please request a quote', 'fee' => 0.0, 'design' => $design, 'request' => null];
    }
    return ['error' => null, 'fee' => design_price($design['spec_data']['elements'] ?? [])['total'], 'design' => $design, 'request' => null];
}

// ---------------------------------------------------------------------
//  Quote requests
// ---------------------------------------------------------------------
function design_request_statuses(): array
{
    return ['new' => 'Waiting for price', 'quoted' => 'Price confirmed', 'ordered' => 'Ordered', 'cancelled' => 'Cancelled'];
}

function design_request_badge(string $s): string
{
    $map = ['new' => 'warning', 'quoted' => 'info', 'ordered' => 'success', 'cancelled' => 'danger'];
    return '<span class="uc-status uc-status-' . ($map[$s] ?? 'secondary') . '">' . e(design_request_statuses()[$s] ?? $s) . '</span>';
}

function design_request_reference(): string
{
    do {
        $ref = 'DSN' . date('ymd') . random_int(1000, 9999);
    } while (db_val('SELECT 1 FROM design_requests WHERE reference = ?', [$ref]));
    return $ref;
}

function design_request_for_viewer(string $ref, ?string $token): ?array
{
    $r = db_row('SELECT * FROM design_requests WHERE reference = ?', [$ref]);
    if (!$r) {
        return null;
    }
    if (($r['customer_id'] && (int) $r['customer_id'] === (int) customer_id()) || ($token && hash_equals($r['access_token'], $token))
        || in_array($ref, $_SESSION['my_designs'] ?? [], true)) {
        return $r;
    }
    return null;
}

function design_request_link(array $r): string
{
    return url('design-studio/request/' . $r['reference'] . '?t=' . $r['access_token']);
}

function mail_design_quote(array $r, string $title, string $message): void
{
    if (empty($r['email'])) {
        return;
    }
    $html = mail_layout($title, '<p>Hi ' . e(strtok($r['name'], ' ')) . ',</p><p>' . nl2br(e($message)) . '</p>'
        . '<p><a href="' . e(design_request_link($r)) . '" style="background:#111;color:#fff;padding:12px 22px;text-decoration:none;display:inline-block">View your design &amp; price</a></p>');
    send_mail($r['email'], $title . ' — ' . $r['reference'], $html);
}

/** Customer-facing totals for a request: garment price per size + print price per piece. */
function design_request_totals(array $r, array $design): array
{
    $product = db_row('SELECT * FROM products WHERE id = ?', [(int) $design['product_id']]);
    $sizes = json_decode((string) $r['sizes'], true) ?: [];
    $print = $r['quoted_print_price'] !== null ? (float) $r['quoted_print_price'] : (float) $r['estimate_print'];
    $lines = [];
    $total = 0.0;
    foreach ($sizes as $size => $qty) {
        $variant = $product ? design_variant((int) $product['id'], $design['color'], (string) $size) : null;
        $garment = $product ? price_info($product, $variant)['final'] : 0.0;
        $lines[] = ['size' => $size, 'qty' => (int) $qty, 'garment' => $garment, 'print' => $print, 'total' => ($garment + $print) * (int) $qty];
        $total += ($garment + $print) * (int) $qty;
    }
    return ['lines' => $lines, 'total' => $total, 'print' => $print, 'final' => $r['quoted_print_price'] !== null];
}
