<?php
/**
 * seo.php — the SEO engine: meta tag rendering, schema.org generation,
 * breadcrumbs, and the real-time SEO score used by the Admin Tool Builder.
 *
 * Nothing here is hardcoded per-page — every public template calls
 * generate_meta_tags()/generate_schema()/generate_breadcrumbs() with
 * data pulled from the `tools` / `seo_settings` / `tool_content` tables.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

function get_seo_settings(string $entityType, int $entityId): ?array
{
    return tp_query_one(
        'SELECT * FROM seo_settings WHERE entity_type = ? AND entity_id = ?',
        'si',
        [$entityType, $entityId]
    );
}

function save_seo_settings(string $entityType, int $entityId, array $fields): void
{
    $existing = get_seo_settings($entityType, $entityId);
    $columns = [
        'seo_title', 'meta_description', 'focus_keyword', 'secondary_keywords',
        'canonical_url', 'robots', 'og_title', 'og_description', 'og_image',
        'twitter_title', 'twitter_description', 'twitter_image', 'schema_type',
        'breadcrumb_title', 'image_alt_text',
    ];
    $values = [];
    foreach ($columns as $col) {
        $values[$col] = $fields[$col] ?? ($existing[$col] ?? null);
    }
    // seo_settings.robots is NOT NULL DEFAULT 'index,follow' — every other
    // column is nullable, so this is the only one that needs a fallback
    // when a caller doesn't pass it and there's no existing row yet.
    if ($values['robots'] === null || $values['robots'] === '') {
        $values['robots'] = 'index,follow';
    }

    if ($existing) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", $columns));
        tp_execute(
            "UPDATE seo_settings SET {$set} WHERE entity_type = ? AND entity_id = ?",
            str_repeat('s', count($columns)) . 'si',
            [...array_values($values), $entityType, $entityId]
        );
    } else {
        $cols = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        tp_execute(
            "INSERT INTO seo_settings (entity_type, entity_id, {$cols}) VALUES (?, ?, {$placeholders})",
            'si' . str_repeat('s', count($columns)),
            [$entityType, $entityId, ...array_values($values)]
        );
    }
}

/**
 * Render the full <head> meta block for any page. Falls back sensibly
 * when the admin hasn't filled in a field (e.g. OG title -> SEO title
 * -> the page's own name).
 */
function generate_meta_tags(array $seo, string $fallbackTitle, string $fallbackDescription, string $pageUrl): string
{
    $title = $seo['seo_title'] ?? $fallbackTitle;
    $description = $seo['meta_description'] ?? $fallbackDescription;
    $canonical = tp_to_absolute($seo['canonical_url'] ?? $pageUrl);
    $robots = $seo['robots'] ?? 'index,follow';
    $ogTitle = $seo['og_title'] ?? $title;
    $ogDescription = $seo['og_description'] ?? $description;
    $ogImage = tp_to_absolute($seo['og_image'] ?? tp_asset('images/og-default.jpg'));
    $twitterTitle = $seo['twitter_title'] ?? $ogTitle;
    $twitterDescription = $seo['twitter_description'] ?? $ogDescription;
    $twitterImage = tp_to_absolute($seo['twitter_image'] ?? $ogImage);
    $siteName = tp_setting('site_name');

    $html = '';
    $html .= '<title>' . e($title) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . e($description) . '">' . "\n";
    $html .= '<link rel="canonical" href="' . e($canonical) . '">' . "\n";
    $html .= '<meta name="robots" content="' . e($robots) . '">' . "\n";
    $html .= '<meta property="og:type" content="website">' . "\n";
    $html .= '<meta property="og:site_name" content="' . e($siteName) . '">' . "\n";
    $html .= '<meta property="og:title" content="' . e($ogTitle) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . e($ogDescription) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . e($ogImage) . '">' . "\n";
    $html .= '<meta property="og:url" content="' . e($canonical) . '">' . "\n";
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . e($twitterTitle) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . e($twitterDescription) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . e($twitterImage) . '">' . "\n";

    $gsc = tp_setting('gsc_verification');
    if ($gsc) {
        $html .= '<meta name="google-site-verification" content="' . e($gsc) . '">' . "\n";
    }

    return $html;
}

/**
 * Build one or more JSON-LD <script> blocks. $type selects the shape;
 * $data supplies the fields. Returns ready-to-echo HTML.
 */
function generate_schema(string $type, array $data): string
{
    $schema = null;

    switch ($type) {
        case 'WebApplication':
        case 'SoftwareApplication':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => $type,
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'url' => tp_to_absolute($data['url'] ?? ''),
                'applicationCategory' => $data['category'] ?? 'UtilitiesApplication',
                'operatingSystem' => 'Any (Web Browser)',
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            ];
            if (!empty($data['image'])) {
                $schema['image'] = tp_to_absolute($data['image']);
            }
            break;

        case 'HowTo':
            $steps = [];
            foreach ($data['steps'] ?? [] as $i => $step) {
                $steps[] = ['@type' => 'HowToStep', 'position' => $i + 1, 'text' => $step];
            }
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'HowTo',
                'name' => $data['name'] ?? '',
                'step' => $steps,
            ];
            break;

        case 'FAQPage':
            $entities = [];
            foreach ($data['faqs'] ?? [] as $faq) {
                $entities[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq['answer'])],
                ];
            }
            if (!$entities) {
                return '';
            }
            $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
            break;

        case 'BreadcrumbList':
            $items = [];
            foreach ($data['items'] ?? [] as $i => $item) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['label'],
                    'item' => tp_to_absolute($item['url'] ?? ''),
                ];
            }
            $schema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
            break;

        case 'WebSite':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => tp_setting('site_name'),
                'url' => tp_absolute_url(),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => tp_absolute_url('search?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ];
            break;

        case 'Organization':
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => tp_setting('site_name'),
                'url' => tp_absolute_url(),
                'logo' => tp_to_absolute(tp_setting('logo') ?: tp_asset('images/logo.png')),
            ];
            break;
    }

    if (!$schema) {
        return '';
    }

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/**
 * $items = [['label' => 'Home', 'url' => ...], ['label' => 'Finance', 'url' => ...], ['label' => 'Loan EMI Calculator', 'url' => null]]
 * Returns [html, schema] — HTML <nav> plus the matching BreadcrumbList JSON-LD.
 */
function generate_breadcrumbs(array $items): array
{
    $html = '<nav class="breadcrumb-nav" aria-label="Breadcrumb"><ol>';
    foreach ($items as $i => $item) {
        $isLast = $i === array_key_last($items);
        $html .= '<li>';
        if (!$isLast && !empty($item['url'])) {
            $html .= '<a href="' . e($item['url']) . '">' . e($item['label']) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . e($item['label']) . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></nav>';

    $schema = generate_schema('BreadcrumbList', ['items' => array_map(
        fn($i) => ['label' => $i['label'], 'url' => tp_to_absolute($i['url'] ?? tp_request_path_relative())],
        $items
    )]);

    return ['html' => $html, 'schema' => $schema];
}

/**
 * calculate_seo_score() — real-time score used by the Admin Tool Builder.
 * $context is a normalized array (see tp_seo_context_for_tool() below):
 *   title, url, slug, meta_description, focus_keyword, h1, content_text,
 *   has_faq, has_examples, has_formula, has_how_to, related_count,
 *   image, image_alt, canonical, robots, schema_type, og_title,
 *   twitter_title
 *
 * Returns ['score' => int 0-100, 'grade' => string, 'checks' => [ [group, label, pass, message] ]]
 */
function calculate_seo_score(array $context): array
{
    $checks = [];
    $keyword = trim(strtolower($context['focus_keyword'] ?? ''));
    $contains = fn($haystack) => $keyword !== '' && str_contains(strtolower((string) $haystack), $keyword);

    // --- Basic SEO ---
    $titleLen = strlen($context['title'] ?? '');
    $checks[] = ['group' => 'Basic SEO', 'label' => 'SEO title exists', 'pass' => $titleLen > 0];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'SEO title length (50-60 chars)', 'pass' => $titleLen >= 40 && $titleLen <= 65];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'Focus keyword in title', 'pass' => $keyword === '' ? false : $contains($context['title'] ?? '')];
    $descLen = strlen($context['meta_description'] ?? '');
    $checks[] = ['group' => 'Basic SEO', 'label' => 'Meta description exists', 'pass' => $descLen > 0];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'Meta description length (120-160 chars)', 'pass' => $descLen >= 110 && $descLen <= 165];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'Keyword in meta description', 'pass' => $contains($context['meta_description'] ?? '')];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'URL slug exists', 'pass' => !empty($context['slug'])];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'URL length is reasonable (< 60 chars)', 'pass' => strlen($context['slug'] ?? '') < 60];
    $checks[] = ['group' => 'Basic SEO', 'label' => 'Keyword in URL', 'pass' => $contains(str_replace('-', ' ', $context['slug'] ?? ''))];

    // --- Content ---
    $contentLen = strlen(strip_tags($context['content_text'] ?? ''));
    $checks[] = ['group' => 'Content', 'label' => 'Description/introduction exists', 'pass' => $contentLen > 0];
    $checks[] = ['group' => 'Content', 'label' => 'Content length (300+ characters)', 'pass' => $contentLen >= 300];
    $checks[] = ['group' => 'Content', 'label' => 'Keyword appears in content', 'pass' => $contains($context['content_text'] ?? '')];
    $checks[] = ['group' => 'Content', 'label' => 'Keyword in H1 / tool name', 'pass' => $contains($context['h1'] ?? $context['title'] ?? '')];
    $checks[] = ['group' => 'Content', 'label' => 'Internal links (related tools)', 'pass' => ($context['related_count'] ?? 0) >= 2, 'hint' => 'Add 2+ related tools'];
    $checks[] = ['group' => 'Content', 'label' => 'How-to-use section present', 'pass' => !empty($context['has_how_to'])];

    // --- Images ---
    $checks[] = ['group' => 'Images', 'label' => 'Featured image set', 'pass' => !empty($context['image'])];
    $checks[] = ['group' => 'Images', 'label' => 'Image alt text set', 'pass' => !empty($context['image_alt'])];
    $checks[] = ['group' => 'Images', 'label' => 'Keyword in image alt text', 'pass' => $contains($context['image_alt'] ?? '')];

    // --- Technical SEO ---
    $checks[] = ['group' => 'Technical SEO', 'label' => 'Canonical URL set', 'pass' => !empty($context['canonical'])];
    $checks[] = ['group' => 'Technical SEO', 'label' => 'Robots meta set', 'pass' => !empty($context['robots'])];
    $checks[] = ['group' => 'Technical SEO', 'label' => 'Schema type selected', 'pass' => !empty($context['schema_type'])];
    $checks[] = ['group' => 'Technical SEO', 'label' => 'Open Graph title set', 'pass' => !empty($context['og_title'])];
    $checks[] = ['group' => 'Technical SEO', 'label' => 'Twitter card title set', 'pass' => !empty($context['twitter_title'])];

    // --- Content Quality ---
    $checks[] = ['group' => 'Content Quality', 'label' => 'Formula documented', 'pass' => !empty($context['has_formula'])];
    $checks[] = ['group' => 'Content Quality', 'label' => 'Example calculation added', 'pass' => !empty($context['has_examples'])];
    $checks[] = ['group' => 'Content Quality', 'label' => 'FAQ section added', 'pass' => !empty($context['has_faq']), 'hint' => 'Add a FAQ section'];

    $passed = count(array_filter($checks, fn($c) => $c['pass']));
    $total = count($checks);
    $score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

    if ($score >= 86) {
        $grade = 'Excellent';
    } elseif ($score >= 71) {
        $grade = 'Good';
    } elseif ($score >= 51) {
        $grade = 'Average';
    } elseif ($score >= 31) {
        $grade = 'Needs Improvement';
    } else {
        $grade = 'Poor';
    }

    return ['score' => $score, 'grade' => $grade, 'checks' => $checks];
}

/** Build the normalized context calculate_seo_score() expects, for a tool row. */
function tp_seo_context_for_tool(array $tool, array $seo, ?array $content, array $faqs, array $examples, array $formulas, int $relatedCount): array
{
    $contentText = implode(' ', array_filter([
        $tool['short_description'] ?? '',
        $tool['description'] ?? '',
        $content['introduction'] ?? '',
        $content['how_to_use'] ?? '',
    ]));

    return [
        'title' => $seo['seo_title'] ?? $tool['name'],
        'slug' => $tool['slug'],
        'meta_description' => $seo['meta_description'] ?? $tool['short_description'] ?? '',
        'focus_keyword' => $seo['focus_keyword'] ?? '',
        'h1' => $tool['name'],
        'content_text' => $contentText,
        'has_faq' => count($faqs) > 0,
        'has_examples' => count($examples) > 0,
        'has_formula' => count($formulas) > 0 || !empty($content['formula']),
        'has_how_to' => !empty($content['how_to_use']),
        'related_count' => $relatedCount,
        'image' => $tool['featured_image'] ?? '',
        'image_alt' => $seo['image_alt_text'] ?? '',
        'canonical' => $seo['canonical_url'] ?? '',
        'robots' => $seo['robots'] ?? 'index,follow',
        'schema_type' => $seo['schema_type'] ?? '',
        'og_title' => $seo['og_title'] ?? '',
        'twitter_title' => $seo['twitter_title'] ?? '',
    ];
}
