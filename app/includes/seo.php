<?php
/**
 * SEO: page meta, canonical URLs, Open Graph/Twitter, JSON-LD, sitemap,
 * robots.txt, ads.txt, redirects.
 *
 * Pages set $GLOBALS['page_meta'] keys:
 *   title, description, canonical, image, type, noindex, breadcrumbs[], jsonld[], prev, next
 */

function meta_set(array $meta): void
{
    $GLOBALS['page_meta'] = array_merge($GLOBALS['page_meta'] ?? [], $meta);
}

function meta(string $key, $default = null)
{
    return $GLOBALS['page_meta'][$key] ?? $default;
}

function page_title(): string
{
    $site = setting('site_name', 'Beglet');
    $t = meta('title');
    if (!$t) {
        return setting('browser_title', $site . ' — Premium Crafted Leather');
    }
    $tpl = setting('seo_title_template', '{title} | {site}');
    return str_replace(['{title}', '{site}'], [$t, $site], $tpl);
}

function page_description(): string
{
    return excerpt(meta('description') ?: setting('seo_default_description', ''), 300);
}

/** Canonical: explicit, else current path without tracking/filter params. */
function canonical_url(): string
{
    $c = meta('canonical');
    if ($c) {
        return preg_match('#^https?://#', $c) ? $c : url($c);
    }
    $path = current_path();
    $keep = [];
    if (!empty($_GET['page']) && (int) $_GET['page'] > 1) {
        $keep['page'] = (int) $_GET['page'];
    }
    return url($path, $keep);
}

/** Path relative to the app root, e.g. "product/x". */
function current_path(): string
{
    $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $bp = base_path();
    if ($bp !== '' && strpos($uri, $bp) === 0) {
        $uri = substr($uri, strlen($bp));
    }
    return trim(rawurldecode($uri), '/');
}

function robots_meta(): string
{
    if (!setting_bool('seo_indexing_enabled', true) || meta('noindex')) {
        return 'noindex, nofollow';
    }
    return 'index, follow, max-image-preview:large';
}

function breadcrumbs_jsonld(array $crumbs): array
{
    $items = [];
    foreach (array_values($crumbs) as $i => $c) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => isset($c['url']) ? url(ltrim(substr($c['url'], strlen(base_path())), '/')) : canonical_url()];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function organization_jsonld(): array
{
    $same = array_values(array_filter([
        setting('social_instagram'), setting('social_facebook'), setting('social_tiktok'),
        setting('social_youtube'), setting('social_pinterest'), setting('social_x'),
    ]));
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => setting('brand_name', setting('site_name', 'Beglet')),
        'url' => url(),
        'logo' => abs_media_url(setting('logo_path', 'assets/img/brand/logo-dark.svg')),
    ];
    if ($same) {
        $org['sameAs'] = $same;
    }
    if (setting('contact_phone') || setting('support_email')) {
        $org['contactPoint'] = array_filter([
            '@type' => 'ContactPoint', 'contactType' => 'customer service',
            'telephone' => setting('contact_phone'), 'email' => setting('support_email'),
        ]);
    }
    return $org;
}

function website_jsonld(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => setting('site_name', 'Beglet'),
        'url' => url(),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('search') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function product_jsonld(array $p, array $images, array $rating, array $variants): array
{
    $pricing = unit_pricing($p);
    $availabilityMap = [
        'in_stock' => 'https://schema.org/InStock', 'low_stock' => 'https://schema.org/LimitedAvailability',
        'out_of_stock' => 'https://schema.org/OutOfStock', 'preorder' => 'https://schema.org/PreOrder',
    ];
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $p['name'],
        'sku' => $p['sku'],
        'description' => excerpt($p['meta_description'] ?: ($p['short_description'] ?: $p['description']), 500),
        'image' => array_map(fn($i) => abs_media_url($i['file_path']), array_slice($images, 0, 6)),
        'brand' => ['@type' => 'Brand', 'name' => setting('brand_name', 'Beglet')],
        'category' => $p['category_name'],
        'url' => url('product/' . $p['slug']),
        'offers' => [
            '@type' => 'Offer',
            'url' => url('product/' . $p['slug']),
            'priceCurrency' => currency_code(),
            'price' => number_format($pricing['price'], 2, '.', ''),
            'availability' => $availabilityMap[$p['availability']] ?? 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@type' => 'Organization', 'name' => setting('brand_name', 'Beglet')],
        ],
    ];
    if ($p['material']) {
        $data['material'] = $p['material'];
    }
    $colors = array_values(array_unique(array_filter(array_column($variants, 'color_name'))));
    if ($colors) {
        $data['color'] = implode(', ', $colors);
    }
    if ($p['weight_grams']) {
        $data['weight'] = ['@type' => 'QuantitativeValue', 'value' => (int) $p['weight_grams'], 'unitCode' => 'GRM'];
    }
    if ($rating['count'] > 0) {
        $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $rating['average'], 'reviewCount' => $rating['count']];
    }
    return $data;
}

/** Look up an active redirect for a path and perform it. */
function apply_redirects(string $path): void
{
    $variants = ['/' . $path, '/' . $path . '/'];
    $r = db_one('SELECT * FROM redirects WHERE is_active = 1 AND source_path IN (?, ?) LIMIT 1', $variants);
    if ($r) {
        db_exec('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]);
        $target = preg_match('#^https?://#', $r['target_path']) ? $r['target_path'] : url(ltrim($r['target_path'], '/'));
        redirect($target, in_array((int) $r['status_code'], [301, 302, 307, 308], true) ? (int) $r['status_code'] : 301);
    }
}

/** Create a 301 automatically when a slug changes (and collapse chains). */
function add_slug_redirect(string $prefix, string $oldSlug, string $newSlug): void
{
    if ($oldSlug === '' || $oldSlug === $newSlug) {
        return;
    }
    $from = '/' . trim($prefix, '/') . '/' . $oldSlug;
    $to = '/' . trim($prefix, '/') . '/' . $newSlug;
    db_exec('DELETE FROM redirects WHERE source_path = ?', [$to]);
    db_exec('UPDATE redirects SET target_path = ? WHERE target_path = ?', [$to, $from]);
    db_exec(
        'INSERT INTO redirects (source_path, target_path, status_code, is_auto) VALUES (?, ?, 301, 1)
         ON DUPLICATE KEY UPDATE target_path = VALUES(target_path), is_active = 1',
        [$from, $to]
    );
}

// ---- Sitemap / robots / ads.txt ------------------------------------------------

function sitemap_entries(): array
{
    $e = [['loc' => url(), 'lastmod' => null, 'priority' => '1.0']];
    $e[] = ['loc' => url('shop'), 'lastmod' => null, 'priority' => '0.9'];
    foreach (db_all('SELECT slug, updated_at FROM categories WHERE is_active = 1 ORDER BY sort_order') as $c) {
        $e[] = ['loc' => url('category/' . $c['slug']), 'lastmod' => $c['updated_at'], 'priority' => '0.8'];
    }
    foreach (db_all('SELECT slug, updated_at FROM collections WHERE is_active = 1') as $c) {
        $e[] = ['loc' => url('collection/' . $c['slug']), 'lastmod' => $c['updated_at'], 'priority' => '0.7'];
    }
    foreach (db_all("SELECT p.slug, p.updated_at, p.canonical_url FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'published' AND c.is_active = 1 ORDER BY p.id") as $p) {
        // Products canonicalised elsewhere are excluded to avoid duplicate URLs.
        if ($p['canonical_url'] && rtrim($p['canonical_url'], '/') !== url('product/' . $p['slug'])) {
            continue;
        }
        $e[] = ['loc' => url('product/' . $p['slug']), 'lastmod' => $p['updated_at'], 'priority' => '0.8'];
    }
    foreach (db_all('SELECT slug, updated_at FROM pages WHERE is_published = 1 AND noindex = 0') as $pg) {
        $e[] = ['loc' => url($pg['slug']), 'lastmod' => $pg['updated_at'], 'priority' => '0.5'];
    }
    $e[] = ['loc' => url('contact'), 'lastmod' => null, 'priority' => '0.4'];
    return $e;
}

function render_sitemap(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (sitemap_entries() as $u) {
        $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
        if ($u['lastmod']) {
            $xml .= '<lastmod>' . date('Y-m-d', strtotime($u['lastmod'])) . '</lastmod>';
        }
        $xml .= '<priority>' . $u['priority'] . '</priority></url>' . "\n";
    }
    return $xml . '</urlset>' . "\n";
}

function default_robots_txt(): string
{
    $bp = base_path();
    return "User-agent: *\n"
        . "Disallow: $bp/admin/\n"
        . "Disallow: $bp/cart\n"
        . "Disallow: $bp/checkout\n"
        . "Disallow: $bp/account\n"
        . "Disallow: $bp/order/\n"
        . "Disallow: $bp/api/\n"
        . "Disallow: $bp/payment/\n"
        . "Disallow: $bp/wishlist\n"
        . "Disallow: $bp/*?*sort=\n"
        . "Allow: $bp/assets/\n"
        . "Allow: $bp/uploads/\n";
}

function render_robots_txt(): string
{
    if (!setting_bool('seo_indexing_enabled', true)) {
        return "User-agent: *\nDisallow: /\n";
    }
    $body = trim((string) setting('robots_txt', '')) ?: default_robots_txt();
    if (stripos($body, 'sitemap:') === false) {
        $body = rtrim($body) . "\n\nSitemap: " . url('sitemap.xml');
    }
    return rtrim($body) . "\n";
}

/** Warn about rules that would hurt indexing. */
function robots_txt_warnings(string $txt): array
{
    $w = [];
    $agentAll = false;
    foreach (preg_split('/\R/', $txt) as $line) {
        $line = trim(preg_replace('/#.*/', '', $line));
        if (preg_match('/^user-agent:\s*\*$/i', $line)) {
            $agentAll = true;
        } elseif (preg_match('/^user-agent:/i', $line)) {
            $agentAll = false;
        }
        if (!$agentAll || !preg_match('/^disallow:\s*(\S*)/i', $line, $m)) {
            continue;
        }
        $rule = $m[1];
        $bp = base_path();
        if ($rule === '/' || $rule === $bp . '/') {
            $w[] = 'This blocks your ENTIRE site from search engines.';
        }
        foreach (['/assets', '/uploads', '/product', '/category', '/shop'] as $essential) {
            if ($rule !== '' && strpos($bp . $essential . '/', rtrim($rule, '*')) === 0 && $rule !== '/') {
                $w[] = "The rule \"Disallow: $rule\" blocks $essential — search engines need these pages/assets.";
            }
        }
    }
    return array_values(array_unique($w));
}

/** Validate ads.txt lines: domain, publisher id, DIRECT|RESELLER[, cert id] — or variables/comments. */
function ads_txt_errors(string $txt): array
{
    $errors = [];
    foreach (preg_split('/\R/', $txt) as $n => $line) {
        $clean = trim(preg_replace('/#.*/', '', $line));
        if ($clean === '' || preg_match('/^(contact|subdomain|ownerdomain|managerdomain|inventorypartnerdomain)\s*=/i', $clean)) {
            continue;
        }
        $parts = array_map('trim', explode(',', $clean));
        if (count($parts) < 3 || !preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/i', $parts[0]) || $parts[1] === '' || !in_array(strtoupper($parts[2]), ['DIRECT', 'RESELLER'], true)) {
            $errors[] = 'Line ' . ($n + 1) . ' is not a valid ads.txt record: ' . $clean;
        }
    }
    return $errors;
}

/** Pagination rel links for listing pages. */
function pagination_meta(array $pg): void
{
    if ($pg['page'] > 1) {
        meta_set(['prev' => url(current_path(), $pg['page'] > 2 ? ['page' => $pg['page'] - 1] : [])]);
    }
    if ($pg['page'] < $pg['pages']) {
        meta_set(['next' => url(current_path(), ['page' => $pg['page'] + 1])]);
    }
}
