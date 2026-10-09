<?php
/** SEO: meta tags, Open Graph, JSON-LD, breadcrumbs, sitemap, robots, redirects. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$GLOBALS['_seo'] = ['title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'type' => 'website', 'noindex' => false, 'schema' => [], 'breadcrumbs' => [], 'og_title' => null, 'og_description' => null];

function seo_set(array $data): void
{
    $GLOBALS['_seo'] = array_merge($GLOBALS['_seo'], $data);
}

function seo_schema(array $schema): void
{
    $GLOBALS['_seo']['schema'][] = $schema;
}

/** $crumbs: [[label, url|null], ...] — Home is added automatically. */
function seo_breadcrumbs(array $crumbs): void
{
    $GLOBALS['_seo']['breadcrumbs'] = array_merge([['Home', url()]], $crumbs);
    $items = [];
    foreach ($GLOBALS['_seo']['breadcrumbs'] as $i => [$label, $u]) {
        $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label];
        if ($u) $item['item'] = abs_url(ltrim(substr($u, strlen(BASE_PATH)), '/'));
        $items[] = $item;
    }
    seo_schema(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
}

function seo_title(): string
{
    $t = $GLOBALS['_seo']['title'];
    $site = setting('site_name', 'Ebaya');
    if (!$t) return setting('seo_home_title', $site);
    return str_contains($t, $site) ? $t : $t . setting('seo_title_suffix', ' | ' . $site);
}

function seo_head(): string
{
    $s = $GLOBALS['_seo'];
    $desc = str_limit($s['description'] ?: setting('seo_default_description'), 300);
    $canonical = $s['canonical'] ?: abs_url(ltrim(substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), strlen(BASE_PATH)), '/'));
    $image = $s['image'] ?: setting('seo_default_og_image') ?: 'assets/img/sample/hero-1.svg';
    $image = preg_match('#^https?://#', $image) ? $image : abs_url($image);
    $title = seo_title();
    $noindex = $s['noindex'] || setting('seo_noindex_site') || is_preview();
    $h = '<title>' . e($title) . "</title>\n";
    $h .= '<meta name="description" content="' . e($desc) . "\">\n";
    $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
    $h .= '<meta name="robots" content="' . ($noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large') . "\">\n";
    $h .= '<meta property="og:site_name" content="' . e(setting('site_name', 'Ebaya')) . "\">\n";
    $h .= '<meta property="og:type" content="' . e($s['type']) . "\">\n";
    $h .= '<meta property="og:title" content="' . e($s['og_title'] ?: $title) . "\">\n";
    $h .= '<meta property="og:description" content="' . e($s['og_description'] ?: $desc) . "\">\n";
    $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
    $h .= '<meta property="og:image" content="' . e($image) . "\">\n";
    $h .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ($v = setting('google_site_verification')) $h .= '<meta name="google-site-verification" content="' . e($v) . "\">\n";
    if ($v = setting('bing_site_verification')) $h .= '<meta name="msvalidate.01" content="' . e($v) . "\">\n";
    $schemas = $s['schema'];
    $schemas[] = [
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => setting('site_name', 'Ebaya'), 'url' => abs_url(),
        'logo' => setting('logo') ? abs_url(setting('logo')) : null,
        'sameAs' => array_values(array_filter([setting('social_instagram'), setting('social_facebook'), setting('social_tiktok'), setting('social_pinterest'), setting('social_youtube')])),
    ];
    foreach ($schemas as $sc) {
        $h .= '<script type="application/ld+json">' . json_encode(array_filter($sc, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
    return $h;
}

/** Analytics snippets — only IDs matching the expected format are output. */
function analytics_head(): string
{
    $h = '';
    $ga = setting('ga4_id');
    if ($ga && preg_match('/^G-[A-Z0-9]+$/', $ga)) {
        $h .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ga) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . e($ga) . '");</script>' . "\n";
    }
    $gtm = setting('gtm_id');
    if ($gtm && preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
        $h .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . e($gtm) . "');</script>\n";
    }
    $px = setting('meta_pixel_id');
    if ($px && preg_match('/^[0-9]{6,20}$/', $px)) {
        $h .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . e($px) . "');fbq('track','PageView');</script>\n";
    }
    return $h;
}

function product_schema(array $p, array $images, array $matrix): array
{
    $prices = array_column($matrix['variants'], 'price') ?: [product_price($p)];
    $available = !$p['track_inventory'] || array_filter($matrix['variants'], fn($v) => $v['available']);
    $schema = [
        '@context' => 'https://schema.org', '@type' => 'Product',
        'name' => $p['name'], 'sku' => $p['sku'],
        'description' => str_limit($p['short_description'] ?: strip_tags((string)$p['description']), 500),
        'image' => array_map(fn($i) => abs_url($i['path']), array_slice($images, 0, 6)),
        'brand' => ['@type' => 'Brand', 'name' => setting('site_name', 'Ebaya')],
        'offers' => [
            '@type' => 'AggregateOffer', 'priceCurrency' => setting('currency_code', 'PKR'),
            'lowPrice' => min($prices), 'highPrice' => max($prices), 'offerCount' => max(1, count($matrix['variants'])),
            'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => abs_url('product/' . $p['slug']),
        ],
    ];
    if ((int)$p['rating_count'] > 0) {
        $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float)$p['rating_avg'], 1), 'reviewCount' => (int)$p['rating_count']];
    }
    return $schema;
}

function sitemap_xml(): string
{
    $urls = [[abs_url(), date('Y-m-d'), '1.0'], [abs_url('shop'), date('Y-m-d'), '0.9'], [abs_url('collections'), date('Y-m-d'), '0.7']];
    foreach (db_all("SELECT slug, updated_at FROM categories WHERE status = 'active' AND noindex = 0") as $r) $urls[] = [abs_url('category/' . $r['slug']), substr($r['updated_at'], 0, 10), '0.8'];
    foreach (db_all("SELECT slug, updated_at FROM collections WHERE status = 'active' AND noindex = 0") as $r) $urls[] = [abs_url('collections/' . $r['slug']), substr($r['updated_at'], 0, 10), '0.7'];
    foreach (db_all("SELECT slug, updated_at FROM products WHERE status = 'published' AND noindex = 0") as $r) $urls[] = [abs_url('product/' . $r['slug']), substr($r['updated_at'], 0, 10), '0.8'];
    foreach (db_all("SELECT slug, updated_at FROM pages WHERE status = 'published' AND noindex = 0") as $r) $urls[] = [abs_url($r['slug']), substr($r['updated_at'], 0, 10), '0.5'];
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $mod, $pri]) {
        $x .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc><lastmod>' . $mod . '</lastmod><priority>' . $pri . "</priority></url>\n";
    }
    return $x . '</urlset>';
}

function robots_txt(): string
{
    if (setting('seo_noindex_site')) return "User-agent: *\nDisallow: /\n";
    $p = BASE_PATH;
    $t = "User-agent: *\nDisallow: $p/admin/\nDisallow: $p/cart\nDisallow: $p/checkout\nDisallow: $p/account\nDisallow: $p/order/\nDisallow: $p/payment/\nDisallow: $p/ajax/\nDisallow: $p/search\nDisallow: $p/*?*sort=\n";
    $extra = trim((string)setting('robots_txt_extra'));
    if ($extra !== '') $t .= $extra . "\n";
    return $t . "\nSitemap: " . abs_url('sitemap.xml') . "\n";
}

/** Look up a manual or automatic redirect for the current path. */
function redirect_lookup(string $path): ?array
{
    $path = '/' . trim($path, '/');
    $r = db_one('SELECT * FROM redirects WHERE from_path = ?', [$path]);
    if ($r) db_exec('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [(int)$r['id']]);
    return $r;
}

/** Called when a slug changes so old links keep working. */
function redirect_add_auto(string $from, string $to): void
{
    if ($from === $to) return;
    db_exec('DELETE FROM redirects WHERE from_path = ?', [$to]); // avoid loops
    db_exec('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$to, $from]); // collapse chains
    db_exec('INSERT INTO redirects (from_path, to_path, status_code, is_auto) VALUES (?, ?, 301, 1) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)', [$from, $to]);
}
