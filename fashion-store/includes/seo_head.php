<?php
/**
 * Central SEO head renderer. Pages may set any of these BEFORE requiring
 * includes/header.php to customise their SEO output:
 *
 *   $pageTitle        Full <title> text (also used for og:title)
 *   $pageDescription  Meta description (also used for og:description)
 *   $seoKeywords      Comma separated keywords (optional, low SEO value but kept per spec)
 *   $seoCanonical     Absolute canonical URL (defaults to the current request URL)
 *   $seoImage         Absolute or relative image URL for og:image / twitter:image
 *   $seoType          og:type (defaults to 'website', product pages use 'product')
 *   $seoNoindex       true to emit <meta name="robots" content="noindex,follow">
 *   $structuredData   array of PHP arrays, each json_encoded as its own <script type="application/ld+json">
 */

function current_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}

function organization_schema() {
    $logo = get_setting('site_logo');
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => get_setting('store_name'),
        'url' => BASE_URL . '/',
        'email' => get_setting('store_email'),
        'telephone' => get_setting('store_phone'),
    ];
    if ($logo) {
        $data['logo'] = BASE_URL . '/' . $logo;
    }
    $socials = [];
    $res = mysqli_query($GLOBALS['mysqli'], "SELECT url FROM social_links WHERE status = 'active'");
    while ($row = mysqli_fetch_assoc($res)) $socials[] = $row['url'];
    if ($socials) $data['sameAs'] = $socials;
    return $data;
}

function breadcrumb_schema(array $crumbs) {
    $items = [];
    foreach ($crumbs as $i => $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url'],
        ];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

function product_schema($product, $images, $avgRating = null) {
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'sku' => $product['sku'],
        'description' => $product['short_description'] ?: strip_tags((string)$product['description']),
        'image' => array_map(fn($i) => BASE_URL . '/' . $i, $images),
        'offers' => [
            '@type' => 'Offer',
            'url' => product_url($product['slug']),
            'priceCurrency' => 'PKR',
            'price' => (string)($product['sale_price'] ?: $product['regular_price']),
            'availability' => $product['stock_status'] === 'out_of_stock'
                ? 'https://schema.org/OutOfStock'
                : 'https://schema.org/InStock',
        ],
    ];
    if ($avgRating && $avgRating['cnt'] > 0) {
        $data['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float)$avgRating['avg_r'], 1),
            'reviewCount' => (int)$avgRating['cnt'],
        ];
    }
    return $data;
}

function render_seo_head() {
    global $pageTitle, $pageDescription, $seoKeywords, $seoCanonical, $seoImage, $seoType, $seoNoindex, $structuredData;

    $title = $pageTitle ?? get_setting('seo_default_title', get_setting('store_name'));
    $description = $pageDescription ?? get_setting('seo_default_description');
    $canonical = $seoCanonical ?? current_url();
    $image = $seoImage ?? (get_setting('site_og_image') ? BASE_URL . '/' . get_setting('site_og_image') : null);
    $type = $seoType ?? 'website';
    $siteName = get_setting('store_name');

    echo "<title>" . e($title) . "</title>\n";
    if ($description) echo '<meta name="description" content="' . e($description) . "\">\n";
    if (!empty($seoKeywords)) echo '<meta name="keywords" content="' . e($seoKeywords) . "\">\n";
    echo '<meta name="robots" content="' . ($seoNoindex ? 'noindex,follow' : 'index,follow') . "\">\n";
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";

    echo '<meta property="og:site_name" content="' . e($siteName) . "\">\n";
    echo '<meta property="og:type" content="' . e($type) . "\">\n";
    echo '<meta property="og:title" content="' . e($title) . "\">\n";
    if ($description) echo '<meta property="og:description" content="' . e($description) . "\">\n";
    echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    if ($image) echo '<meta property="og:image" content="' . e($image) . "\">\n";

    echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . "\">\n";
    echo '<meta name="twitter:title" content="' . e($title) . "\">\n";
    if ($description) echo '<meta name="twitter:description" content="' . e($description) . "\">\n";
    if ($image) echo '<meta name="twitter:image" content="' . e($image) . "\">\n";
    $twitterHandle = get_setting('social_twitter_handle');
    if ($twitterHandle) echo '<meta name="twitter:site" content="' . e($twitterHandle) . "\">\n";

    $verify = get_setting('google_site_verification');
    if ($verify) echo '<meta name="google-site-verification" content="' . e($verify) . "\">\n";
    $bingVerify = get_setting('bing_site_verification');
    if ($bingVerify) echo '<meta name="msvalidate.01" content="' . e($bingVerify) . "\">\n";

    $blocks = [organization_schema()];
    if (!empty($structuredData)) {
        foreach ($structuredData as $block) $blocks[] = $block;
    }
    foreach ($blocks as $block) {
        echo '<script type="application/ld+json">' . json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}
