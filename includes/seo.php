<?php
/**
 * SEO: per-entity metadata, head tags, Open Graph / Twitter, JSON-LD schema,
 * breadcrumbs, redirects.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

$GLOBALS['PAGE'] = [
    'title' => '', 'description' => '', 'canonical' => '', 'robots' => 'index,follow', 'og_image' => '', 'og_type' => 'website',
    'og_title' => '', 'og_description' => '', 'twitter_card' => 'summary_large_image', 'twitter_title' => '', 'twitter_description' => '',
    'keywords' => '', 'schema' => [], 'breadcrumbs' => [], 'prev' => '', 'next' => '', 'body_class' => '',
];

function page_set(array $data): void
{
    foreach ($data as $k => $v) {
        $GLOBALS['PAGE'][$k] = $v;
    }
}

function page_get(string $k, $default = '')
{
    return $GLOBALS['PAGE'][$k] ?? $default;
}

function page_add_schema(array $schema): void
{
    $GLOBALS['PAGE']['schema'][] = $schema;
}

function seo_fields(): array
{
    return ['seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords', 'canonical_url', 'meta_robots', 'og_title', 'og_description',
        'og_image', 'twitter_card', 'twitter_title', 'twitter_description', 'image_alt', 'breadcrumb_title', 'schema_json'];
}

function seo_get(string $type, int $id = 0, string $key = ''): array
{
    $row = db_row('SELECT * FROM seo_metadata WHERE entity_type = ? AND entity_id = ? AND entity_key = ?', [$type, $id, $key]);
    $out = [];
    foreach (seo_fields() as $f) $out[$f] = $row[$f] ?? '';
    return $out;
}

function seo_save(string $type, int $id, array $data, string $key = ''): void
{
    $vals = [];
    foreach (seo_fields() as $f) {
        $v = trim((string) ($data[$f] ?? ''));
        if ($f === 'schema_json' && $v !== '' && json_decode($v) === null) $v = ''; // only valid JSON
        if ($f === 'meta_robots' && $v !== '' && !preg_match('/^(no)?index\s*,\s*(no)?follow$/', $v)) $v = '';
        $vals[$f] = $v === '' ? null : $v;
    }
    $cols = implode(', ', array_keys($vals));
    $ph = implode(', ', array_fill(0, count($vals), '?'));
    $upd = implode(', ', array_map(function ($c) { return "$c = VALUES($c)"; }, array_keys($vals)));
    db_exec("INSERT INTO seo_metadata (entity_type, entity_id, entity_key, $cols) VALUES (?, ?, ?, $ph) ON DUPLICATE KEY UPDATE $upd",
        array_merge([$type, $id, $key], array_values($vals)));
}

/** Apply stored SEO overrides on top of computed defaults. */
function seo_apply(array $seo, array $defaults): void
{
    $title = $seo['seo_title'] ?: $defaults['title'];
    $desc = $seo['meta_description'] ?: ($defaults['description'] ?? '');
    page_set([
        'title' => $title,
        'description' => $desc,
        'canonical' => $seo['canonical_url'] ?: ($defaults['canonical'] ?? ''),
        'robots' => $seo['meta_robots'] ?: ($defaults['robots'] ?? 'index,follow'),
        'og_title' => $seo['og_title'] ?: $title,
        'og_description' => $seo['og_description'] ?: $desc,
        'og_image' => $seo['og_image'] ? upload_url($seo['og_image']) : ($defaults['og_image'] ?? ''),
        'twitter_card' => $seo['twitter_card'] ?: 'summary_large_image',
        'twitter_title' => $seo['twitter_title'] ?: ($seo['og_title'] ?: $title),
        'twitter_description' => $seo['twitter_description'] ?: ($seo['og_description'] ?: $desc),
        'keywords' => trim(($seo['focus_keyword'] ?? '') . ($seo['secondary_keywords'] ? ', ' . $seo['secondary_keywords'] : ''), ', '),
    ]);
    if (!empty($seo['schema_json']) && ($j = json_decode($seo['schema_json'], true))) {
        page_add_schema($j);
    }
}

/** Render all <head> SEO tags. */
function seo_head(): string
{
    $P = $GLOBALS['PAGE'];
    $siteName = setting('site_name', 'Fahim Store');
    $title = $P['title'] ? $P['title'] . (stripos($P['title'], $siteName) === false ? ' | ' . $siteName : '') : $siteName . ' - ' . setting('site_tagline', 'Books, Stationery & Grocery');
    $desc = $P['description'] ?: setting('meta_description', '');
    $canonical = $P['canonical'] ?: abs_url(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    if (strpos($canonical, 'http') !== 0) $canonical = abs_url($canonical);
    $og = $P['og_image'] ?: url('assets/images/og-default.png');
    if (strpos($og, 'http') !== 0) $og = site_origin() . $og;

    $h = '<title>' . e($title) . "</title>\n";
    $h .= '<meta name="description" content="' . e(str_limit($desc, 300)) . "\">\n";
    if ($P['keywords']) $h .= '<meta name="keywords" content="' . e($P['keywords']) . "\">\n";
    $h .= '<meta name="robots" content="' . e($P['robots']) . "\">\n";
    if (strpos($P['robots'], 'noindex') === false) $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
    if ($P['prev']) $h .= '<link rel="prev" href="' . e($P['prev']) . "\">\n";
    if ($P['next']) $h .= '<link rel="next" href="' . e($P['next']) . "\">\n";
    $h .= '<meta property="og:site_name" content="' . e($siteName) . "\">\n";
    $h .= '<meta property="og:type" content="' . e($P['og_type']) . "\">\n";
    $h .= '<meta property="og:title" content="' . e($P['og_title'] ?: $title) . "\">\n";
    $h .= '<meta property="og:description" content="' . e(str_limit($P['og_description'] ?: $desc, 300)) . "\">\n";
    $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
    $h .= '<meta property="og:image" content="' . e($og) . "\">\n";
    $h .= '<meta property="og:locale" content="en_PK">' . "\n";
    $h .= '<meta name="twitter:card" content="' . e($P['twitter_card']) . "\">\n";
    $h .= '<meta name="twitter:title" content="' . e($P['twitter_title'] ?: ($P['og_title'] ?: $title)) . "\">\n";
    $h .= '<meta name="twitter:description" content="' . e(str_limit($P['twitter_description'] ?: $desc, 200)) . "\">\n";
    $h .= '<meta name="twitter:image" content="' . e($og) . "\">\n";
    if ($tw = setting('twitter_handle', '')) $h .= '<meta name="twitter:site" content="' . e($tw) . "\">\n";
    if ($v = setting('google_verification', '')) $h .= '<meta name="google-site-verification" content="' . e($v) . "\">\n";

    $schemas = $P['schema'];
    if ($P['breadcrumbs']) $schemas[] = schema_breadcrumbs($P['breadcrumbs']);
    foreach ($schemas as $s) {
        $h .= '<script type="application/ld+json">' . json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
    }
    return $h;
}

/* ------------------------------------------------------------------
 * Breadcrumbs: [['Home', url], ['Books', url], ['Class 5', null]]
 * ------------------------------------------------------------------ */
function breadcrumbs_html(array $crumbs): string
{
    $h = '<nav aria-label="breadcrumb" class="fs-breadcrumb"><ol class="breadcrumb mb-0">';
    $n = count($crumbs);
    foreach ($crumbs as $i => $c) {
        if ($i === $n - 1 || empty($c[1])) {
            $h .= '<li class="breadcrumb-item active" aria-current="page">' . e($c[0]) . '</li>';
        } else {
            $h .= '<li class="breadcrumb-item"><a href="' . e($c[1]) . '">' . e($c[0]) . '</a></li>';
        }
    }
    return $h . '</ol></nav>';
}

function schema_breadcrumbs(array $crumbs): array
{
    $items = [];
    foreach ($crumbs as $i => $c) {
        $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0]];
        if (!empty($c[1])) $item['item'] = abs_url($c[1]);
        $items[] = $item;
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function schema_organization(): array
{
    $same = array_values(array_filter([setting('facebook_url'), setting('instagram_url'), setting('youtube_url'), setting('tiktok_url')]));
    $s = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => setting('site_name', 'Fahim Store'), 'url' => abs_url(''),
        'logo' => abs_url('assets/images/logo-icon.png')];
    if ($p = setting('store_phone')) $s['contactPoint'] = ['@type' => 'ContactPoint', 'telephone' => $p, 'contactType' => 'customer service', 'areaServed' => 'PK', 'availableLanguage' => ['English', 'Urdu']];
    if ($same) $s['sameAs'] = $same;
    return $s;
}

function schema_website(): array
{
    return ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('site_name', 'Fahim Store'), 'url' => abs_url(''),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => abs_url('search/') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string']];
}

function schema_local_business(): array
{
    $s = ['@context' => 'https://schema.org', '@type' => 'GroceryStore', 'name' => setting('site_name', 'Fahim Store'), 'url' => abs_url(''),
        'image' => abs_url('assets/images/logo-icon.png'), 'telephone' => setting('store_phone', ''), 'priceRange' => 'Rs',
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => setting('store_address', ''), 'addressLocality' => setting('store_city', ''), 'addressCountry' => 'PK'],
        'openingHours' => setting('opening_hours_schema', 'Mo-Su 09:00-22:00')];
    if (($lat = setting('store_lat')) && ($lng = setting('store_lng'))) {
        $s['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
    }
    return $s;
}

function schema_product(array $p, array $images, array $variations = []): array
{
    $price = product_display_price($p);
    $avail = product_in_stock($p) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $s = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'sku' => $p['sku'], 'url' => abs_url(product_url($p)),
        'description' => str_limit((string) ($p['description'] ?? $p['short_description'] ?? $p['name']), 500)];
    $imgs = array_map(function ($i) { return abs_url(upload_url($i['image'])); }, $images);
    $s['image'] = $imgs ?: [abs_url(product_image_url($p))];
    if (!empty($p['barcode']) && preg_match('/^\d{8,14}$/', $p['barcode'])) $s['gtin'] = $p['barcode'];
    if (!empty($p['isbn'])) $s['isbn'] = $p['isbn'];
    if (!empty($p['brand_name'])) $s['brand'] = ['@type' => 'Brand', 'name' => $p['brand_name']];
    if ($variations) {
        $prices = array_map(function ($v) { return effective_price($v['price'], $v['sale_price']); }, $variations);
        $s['offers'] = ['@type' => 'AggregateOffer', 'priceCurrency' => 'PKR', 'lowPrice' => number_format(min($prices), 2, '.', ''),
            'highPrice' => number_format(max($prices), 2, '.', ''), 'offerCount' => count($variations), 'availability' => $avail];
    } else {
        $s['offers'] = ['@type' => 'Offer', 'url' => abs_url(product_url($p)), 'priceCurrency' => 'PKR', 'price' => number_format($price['price'], 2, '.', ''),
            'availability' => $avail, 'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', 'name' => setting('site_name', 'Fahim Store')]];
    }
    if ((int) $p['rating_count'] > 0) {
        $s['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float) $p['rating_avg'], 'reviewCount' => (int) $p['rating_count']];
    }
    return $s;
}

function schema_faq(array $faqs): array
{
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]];
    }, $faqs)];
}

/** Record a 301 when a slug-based URL changes. */
function seo_add_redirect(string $oldPath, string $newPath): void
{
    $oldPath = '/' . trim(parse_url($oldPath, PHP_URL_PATH) ?: $oldPath, '/') . '/';
    $newPath = '/' . trim(parse_url($newPath, PHP_URL_PATH) ?: $newPath, '/') . '/';
    $bp = base_path();
    if ($bp && strpos($oldPath, $bp . '/') === 0) $oldPath = substr($oldPath, strlen($bp));
    if ($bp && strpos($newPath, $bp . '/') === 0) $newPath = substr($newPath, strlen($bp));
    if ($oldPath === $newPath) return;
    // collapse chains: anything pointing at old now points at new
    db_exec('UPDATE redirects SET new_path = ? WHERE new_path = ?', [$newPath, $oldPath]);
    db_exec('DELETE FROM redirects WHERE old_path = ?', [$newPath]);
    db_exec('INSERT INTO redirects (old_path, new_path, status_code) VALUES (?, ?, 301) ON DUPLICATE KEY UPDATE new_path = VALUES(new_path)', [$oldPath, $newPath]);
}
