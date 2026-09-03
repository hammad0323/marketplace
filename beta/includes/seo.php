<?php
/**
 * Prints <title>, meta tags, Open Graph/Twitter tags and JSON-LD.
 * A page sets $seoEntityType/$seoEntityId (or $pageTitle/$metaDescription
 * as a fallback) before requiring header.php.
 */
$seo = isset($seoEntityType) ? get_seo_meta($seoEntityType, $seoEntityId ?? null) : null;

$title = $seo['meta_title'] ?? ($pageTitle ?? site_name());
$description = $seo['meta_description'] ?? ($metaDescription ?? get_setting('site_description'));
$robots = $seo['robots'] ?? 'index,follow';
$ogImage = $seo['og_image'] ?? asset_url('images/og-default.png');
$canonical = $seo['canonical_url'] ?? base_url(ltrim($_SERVER['REQUEST_URI'] ?? '', '/'));
?>
<title><?= clean($title) ?> | <?= clean(site_name()) ?></title>
<meta name="description" content="<?= clean($description) ?>">
<meta name="robots" content="<?= clean($robots) ?>">
<link rel="canonical" href="<?= clean($canonical) ?>">
<meta property="og:title" content="<?= clean($seo['og_title'] ?? $title) ?>">
<meta property="og:description" content="<?= clean($seo['og_description'] ?? $description) ?>">
<meta property="og:image" content="<?= clean($ogImage) ?>">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= clean($seo['twitter_title'] ?? $title) ?>">
<meta name="twitter:description" content="<?= clean($seo['twitter_description'] ?? $description) ?>">
<?php if (!empty($seo['faq_json'])):
    $faqs = json_decode($seo['faq_json'], true);
    if (!empty($faqs)):
        echo render_schema([
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question', 'name' => $f['question'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer'] ?? '']
            ], $faqs)
        ]);
    endif;
endif;
