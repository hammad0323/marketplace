<?php
/**
 * SEO: titles, meta, canonical, Open Graph, structured data, analytics, breadcrumbs.
 * Pages call seo_set([...]) before including the header.
 */

$GLOBALS['UC_SEO'] = [
    'title' => null, 'description' => null, 'canonical' => null, 'image' => null, 'type' => 'website',
    'noindex' => false, 'og_title' => null, 'og_description' => null, 'schema' => [], 'breadcrumbs' => [],
];

function seo_set(array $data): void
{
    $GLOBALS['UC_SEO'] = array_merge($GLOBALS['UC_SEO'], $data);
}

function seo_get(string $key)
{
    return $GLOBALS['UC_SEO'][$key] ?? null;
}

function seo_add_schema(array $schema): void
{
    $GLOBALS['UC_SEO']['schema'][] = $schema;
}

function current_url_no_query(): string
{
    $p = parse_url(SITE_URL);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    return $origin . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}

function seo_title(): string
{
    $t = seo_get('title');
    $site = setting('seo_site_title', store_name());
    if (!$t) {
        return $site . ' — ' . setting('seo_tagline', "Premium Men's Fashion");
    }
    $tpl = setting('seo_title_template', '{title} | {site}');
    return str_replace(['{title}', '{site}'], [$t, $site], $tpl);
}

function seo_head(): void
{
    $title = seo_title();
    $desc = str_limit(seo_get('description') ?: setting('seo_default_description', ''), 300);
    $canonical = seo_get('canonical') ?: current_url_no_query();
    $image = seo_get('image') ? img_url(seo_get('image')) : img_url(setting('seo_og_image', 'assets/images/brand/og-default.jpg'));
    $noindex = seo_get('noindex') || setting_bool('seo_noindex_site');

    echo '<title>' . e($title) . "</title>\n";
    if ($desc) {
        echo '<meta name="description" content="' . e($desc) . "\">\n";
    }
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";
    echo '<meta name="robots" content="' . ($noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large') . "\">\n";
    echo '<meta property="og:site_name" content="' . e(store_name()) . "\">\n";
    echo '<meta property="og:type" content="' . e(seo_get('type')) . "\">\n";
    echo '<meta property="og:title" content="' . e(seo_get('og_title') ?: $title) . "\">\n";
    echo '<meta property="og:description" content="' . e(seo_get('og_description') ?: $desc) . "\">\n";
    echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    echo '<meta property="og:image" content="' . e($image) . "\">\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ($v = setting('seo_google_verification')) {
        echo '<meta name="google-site-verification" content="' . e($v) . "\">\n";
    }
    if ($v = setting('seo_bing_verification')) {
        echo '<meta name="msvalidate.01" content="' . e($v) . "\">\n";
    }
    if ($v = setting('seo_facebook_domain_verification')) {
        echo '<meta name="facebook-domain-verification" content="' . e($v) . "\">\n";
    }

    $schemas = seo_get('schema');
    $schemas[] = [
        '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => store_name(), 'url' => url(),
        'logo' => url('assets/images/brand/logo-round.webp'),
        'sameAs' => array_values(array_filter([setting('social_instagram'), setting('social_facebook'), setting('social_tiktok'), setting('social_youtube')])),
    ];
    if (seo_get('is_home')) {
        $schemas[] = [
            '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => store_name(), 'url' => url(),
            'potentialAction' => ['@type' => 'SearchAction', 'target' => url('search') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
        ];
    }
    if ($crumbs = seo_get('breadcrumbs')) {
        $items = [];
        foreach (array_values($crumbs) as $i => $c) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?? $canonical];
        }
        $schemas[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    foreach ($schemas as $s) {
        echo '<script type="application/ld+json">' . json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
}

/** Analytics snippets. IDs are validated so admin input cannot inject script. */
function analytics_head(): void
{
    if (current_admin() && setting_bool('analytics_exclude_admins', true)) {
        return;
    }
    $ga = setting('analytics_ga4_id');
    if ($ga && preg_match('/^G-[A-Z0-9]{4,15}$/', $ga)) {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ga) . '"></script>'
            . '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . ejs($ga) . ');</script>' . "\n";
    }
    $gtm = setting('analytics_gtm_id');
    if ($gtm && preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm)) {
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer'," . ejs($gtm) . ");</script>\n";
    }
    $px = setting('analytics_fb_pixel');
    if ($px && preg_match('/^\d{6,20}$/', $px)) {
        echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init'," . ejs($px) . ");fbq('track','PageView');</script>\n";
    }
}

function breadcrumbs_html(array $crumbs): string
{
    if (!$crumbs) {
        return '';
    }
    $html = '<nav aria-label="Breadcrumb" class="uc-breadcrumbs"><ol>';
    $last = count($crumbs) - 1;
    foreach (array_values($crumbs) as $i => $c) {
        $html .= $i === $last || empty($c[1])
            ? '<li aria-current="page">' . e($c[0]) . '</li>'
            : '<li><a href="' . e($c[1]) . '">' . e($c[0]) . '</a></li>';
    }
    return $html . '</ol></nav>';
}

/** Adds a redirect from an old path to a new one (used when slugs change). */
function redirect_add(string $from, string $to, int $code = 301): void
{
    $from = '/' . ltrim(parse_url($from, PHP_URL_PATH) ?? $from, '/');
    $to = '/' . ltrim($to, '/');
    if ($from === $to) {
        return;
    }
    // Avoid chains: anything that pointed at $from now points at $to.
    db_exec('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$to, $from]);
    db_exec('DELETE FROM redirects WHERE from_path = ?', [$to]);
    db_exec(
        'INSERT INTO redirects (from_path, to_path, status_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), status_code = VALUES(status_code), is_active = 1',
        [$from, $to, $code]
    );
}
