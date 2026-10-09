<?php
/**
 * Homepage builder: section schemas (drive both the admin form and validation),
 * loading of published/draft settings, and common section styling.
 */

function section_common_fields(): array
{
    return [
        'bg_color' => ['label' => 'Background colour', 'type' => 'color', 'default' => ''],
        'text_theme' => ['label' => 'Text colour scheme', 'type' => 'select', 'options' => ['dark' => 'Dark text (light backgrounds)', 'light' => 'Light text (dark backgrounds)'], 'default' => 'dark'],
        'padding_top' => ['label' => 'Padding top (px)', 'type' => 'number', 'default' => '', 'help' => 'Blank = global section spacing'],
        'padding_bottom' => ['label' => 'Padding bottom (px)', 'type' => 'number', 'default' => ''],
        'content_width' => ['label' => 'Content width', 'type' => 'select', 'options' => ['container' => 'Standard container', 'narrow' => 'Narrow', 'wide' => 'Wide', 'full' => 'Full width'], 'default' => 'container'],
        'animation' => ['label' => 'Reveal animation', 'type' => 'select', 'options' => ['fade-up' => 'Fade up', 'fade' => 'Fade', 'zoom' => 'Soft zoom', 'none' => 'None'], 'default' => 'fade-up'],
    ];
}

function section_schemas(): array
{
    $cta = [
        'cta_label' => ['label' => 'Button label', 'type' => 'text', 'default' => 'View all'],
        'cta_url' => ['label' => 'Button link', 'type' => 'url', 'default' => '/shop'],
    ];
    return [
        'hero' => [
            'label' => 'Hero carousel',
            'fields' => [
                'autoplay' => ['label' => 'Autoplay', 'type' => 'bool', 'default' => '1'],
                'interval' => ['label' => 'Slide duration (ms)', 'type' => 'number', 'default' => '6500'],
                'transition' => ['label' => 'Transition', 'type' => 'select', 'options' => ['fade' => 'Cross-fade', 'slide' => 'Slide'], 'default' => 'fade'],
                'transition_speed' => ['label' => 'Transition speed (ms)', 'type' => 'number', 'default' => '1200'],
                'show_arrows' => ['label' => 'Show arrows', 'type' => 'bool', 'default' => '1'],
                'show_dots' => ['label' => 'Show dots', 'type' => 'bool', 'default' => '1'],
                'ken_burns' => ['label' => 'Slow zoom on images', 'type' => 'bool', 'default' => '1'],
            ],
            'common' => false,
        ],
        'categories' => [
            'label' => 'Shop by category',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Collections'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Shop by Category'],
                'subtitle' => ['label' => 'Description', 'type' => 'textarea', 'default' => ''],
                'source' => ['label' => 'Categories to show', 'type' => 'select', 'options' => ['flagged' => 'Categories marked "show on homepage"', 'selected' => 'Selected below'], 'default' => 'flagged'],
                'category_ids' => ['label' => 'Selected categories / subcategories', 'type' => 'categories', 'default' => []],
                'limit' => ['label' => 'Maximum to show', 'type' => 'number', 'default' => '6'],
                'layout' => ['label' => 'Layout', 'type' => 'select', 'options' => ['grid' => 'Even grid', 'mosaic' => 'Editorial mosaic'], 'default' => 'mosaic'],
            ],
        ],
        'new_arrivals' => [
            'label' => 'New arrivals',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Just in'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'New Arrivals'],
                'subtitle' => ['label' => 'Subheading', 'type' => 'textarea', 'default' => ''],
                'source' => ['label' => 'Products', 'type' => 'select', 'options' => ['flagged' => 'Marked as new arrival', 'latest' => 'Most recently added', 'manual' => 'Hand-picked below'], 'default' => 'flagged'],
                'product_ids' => ['label' => 'Hand-picked products (in order)', 'type' => 'products', 'default' => []],
                'count' => ['label' => 'Number of products', 'type' => 'number', 'default' => '8'],
            ] + $cta,
        ],
        'best_sellers' => [
            'label' => 'Best sellers',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Most loved'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Best Sellers'],
                'subtitle' => ['label' => 'Description', 'type' => 'textarea', 'default' => ''],
                'source' => ['label' => 'Products', 'type' => 'select', 'options' => ['auto' => 'Automatic — by units sold (tops up with "best seller" flag)', 'flagged' => 'Marked as best seller', 'manual' => 'Hand-picked below'], 'default' => 'auto'],
                'product_ids' => ['label' => 'Hand-picked products (in order)', 'type' => 'products', 'default' => []],
                'count' => ['label' => 'Number of products', 'type' => 'number', 'default' => '8'],
            ] + $cta,
        ],
        'brand_story' => [
            'label' => 'Brand story',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Crafted to last'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Made with patience. Built for years.'],
                'body' => ['label' => 'Story', 'type' => 'textarea', 'default' => ''],
                'image' => ['label' => 'Main image', 'type' => 'image', 'default' => ''],
                'image_alt' => ['label' => 'Main image alt text', 'type' => 'text', 'default' => ''],
                'secondary_image' => ['label' => 'Secondary image (optional)', 'type' => 'image', 'default' => ''],
                'secondary_alt' => ['label' => 'Secondary image alt text', 'type' => 'text', 'default' => ''],
                'image_position' => ['label' => 'Image position', 'type' => 'select', 'options' => ['left' => 'Left', 'right' => 'Right'], 'default' => 'left'],
                'parallax' => ['label' => 'Subtle parallax', 'type' => 'bool', 'default' => '1'],
                'button_label' => ['label' => 'Button label', 'type' => 'text', 'default' => 'Our story'],
                'button_url' => ['label' => 'Button link', 'type' => 'url', 'default' => '/about-us'],
            ],
        ],
        'featured_collection' => [
            'label' => 'Featured collection',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'The collection'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => ''],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'default' => ''],
                'source_type' => ['label' => 'Feature a', 'type' => 'select', 'options' => ['collection' => 'Collection', 'category' => 'Category'], 'default' => 'collection'],
                'collection_id' => ['label' => 'Collection', 'type' => 'collection', 'default' => ''],
                'category_id' => ['label' => 'Category', 'type' => 'category', 'default' => ''],
                'image' => ['label' => 'Feature image', 'type' => 'image', 'default' => ''],
                'image_alt' => ['label' => 'Image alt text', 'type' => 'text', 'default' => ''],
                'layout' => ['label' => 'Layout', 'type' => 'select', 'options' => ['split_left' => 'Image left + products', 'split_right' => 'Image right + products', 'banner' => 'Full-bleed banner'], 'default' => 'split_left'],
                'product_count' => ['label' => 'Products to show', 'type' => 'number', 'default' => '4'],
                'button_label' => ['label' => 'Button label', 'type' => 'text', 'default' => 'Explore the collection'],
                'button_url' => ['label' => 'Button link (blank = collection page)', 'type' => 'url', 'default' => ''],
            ],
        ],
        'benefits' => [
            'label' => 'Craftsmanship & benefits',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'The Beglet standard'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Considered in every detail'],
                'subtitle' => ['label' => 'Description', 'type' => 'textarea', 'default' => ''],
                'items' => ['label' => 'Benefits', 'type' => 'repeater', 'default' => [], 'max' => 6, 'subfields' => [
                    'icon' => ['label' => 'Bootstrap icon name', 'type' => 'text', 'help' => 'e.g. gem, scissors, gift, headset — see icons.getbootstrap.com'],
                    'title' => ['label' => 'Title', 'type' => 'text'],
                    'text' => ['label' => 'Text', 'type' => 'textarea'],
                ]],
            ],
        ],
        'testimonials' => [
            'label' => 'Testimonials',
            'fields' => [
                'eyebrow' => ['label' => 'Eyebrow', 'type' => 'text', 'default' => 'Kind words'],
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'From our customers'],
                'show_rating' => ['label' => 'Show star ratings', 'type' => 'bool', 'default' => '1'],
                'limit' => ['label' => 'Maximum testimonials', 'type' => 'number', 'default' => '6'],
            ],
        ],
        'newsletter' => [
            'label' => 'Newsletter',
            'fields' => [
                'title' => ['label' => 'Heading', 'type' => 'text', 'default' => 'Join the Beglet circle'],
                'text' => ['label' => 'Text', 'type' => 'textarea', 'default' => 'New collections, care guides and private offers — never more than twice a month.'],
                'consent_text' => ['label' => 'Consent text', 'type' => 'text', 'default' => 'I agree to receive emails from Beglet. Unsubscribe at any time.'],
                'button_label' => ['label' => 'Button label', 'type' => 'text', 'default' => 'Subscribe'],
                'image' => ['label' => 'Background image (optional)', 'type' => 'image', 'default' => ''],
            ],
        ],
    ];
}

function section_defaults(string $type): array
{
    $schema = section_schemas()[$type] ?? ['fields' => []];
    $fields = $schema['fields'] + (($schema['common'] ?? true) ? section_common_fields() : []);
    $out = [];
    foreach ($fields as $k => $f) {
        $out[$k] = $f['default'] ?? '';
    }
    return $out;
}

function homepage_preview_mode(): bool
{
    return !empty($_GET['preview']) && current_admin() && can('content.homepage');
}

/** Sections to render, honouring draft values in preview mode. */
function homepage_sections(): array
{
    $preview = homepage_preview_mode();
    $rows = db_all('SELECT * FROM homepage_sections ORDER BY sort_order, id');
    $out = [];
    foreach ($rows as $r) {
        $enabled = $preview && $r['draft_enabled'] !== null ? (int) $r['draft_enabled'] : (int) $r['is_enabled'];
        $sort = $preview && $r['draft_sort'] !== null ? (int) $r['draft_sort'] : (int) $r['sort_order'];
        $json = $preview && $r['draft_settings'] ? $r['draft_settings'] : $r['settings'];
        if (!$enabled) {
            continue;
        }
        $settings = array_merge(section_defaults($r['section_type']), json_decode((string) $json, true) ?: []);
        $out[] = ['key' => $r['section_key'], 'type' => $r['section_type'], 'sort' => $sort, 's' => $settings];
    }
    usort($out, fn($a, $b) => $a['sort'] <=> $b['sort']);
    return $out;
}

/** Inline style + classes shared by every section wrapper. */
function section_attrs(array $s, string $extraClass = ''): string
{
    $style = '';
    if (!empty($s['bg_color']) && valid_hex($s['bg_color'])) {
        $style .= 'background-color:' . $s['bg_color'] . ';';
    }
    if ($s['padding_top'] !== '' && is_numeric($s['padding_top'])) {
        $style .= 'padding-top:' . max(0, min(300, (int) $s['padding_top'])) . 'px;';
    }
    if ($s['padding_bottom'] !== '' && is_numeric($s['padding_bottom'])) {
        $style .= 'padding-bottom:' . max(0, min(300, (int) $s['padding_bottom'])) . 'px;';
    }
    $classes = trim('section ' . $extraClass . (($s['text_theme'] ?? 'dark') === 'light' ? ' section--light' : ''));
    return 'class="' . e($classes) . '"' . ($style ? ' style="' . e($style) . '"' : '');
}

function section_container_class(array $s): string
{
    return ['narrow' => 'container container--narrow', 'wide' => 'container container--wide', 'full' => 'container-fluid px-0'][$s['content_width'] ?? 'container'] ?? 'container';
}

function reveal_attr(array $s, int $delay = 0): string
{
    $a = $s['animation'] ?? 'fade-up';
    if ($a === 'none' || !setting_bool('theme_animations', true)) {
        return '';
    }
    return ' data-reveal="' . e($a) . '"' . ($delay ? ' style="--reveal-delay:' . (int) $delay . 'ms"' : '');
}

function active_slides(): array
{
    return db_all('SELECT * FROM banner_slides WHERE is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY sort_order, id');
}

function active_testimonials(int $limit): array
{
    $sample = setting_bool('show_sample_content', false) ? '' : ' AND is_sample = 0';
    return db_all('SELECT * FROM testimonials WHERE is_active = 1' . $sample . ' ORDER BY sort_order, id LIMIT ' . (int) $limit);
}

function announcement_messages(): array
{
    if (!setting_bool('announcement_enabled', true)) {
        return [];
    }
    return array_values(array_filter(setting_json('announcement_messages', []), fn($m) => trim($m['text'] ?? '') !== ''));
}

function homepage_has_hero(): bool
{
    static $has = null;
    if ($has === null) {
        $has = false;
        foreach (homepage_sections() as $s) {
            if ($s['type'] === 'hero') {
                $has = (bool) active_slides();
                break;
            }
        }
        $has = $has && (($GLOBALS['route_path'] ?? null) === '');
    }
    return $has;
}
