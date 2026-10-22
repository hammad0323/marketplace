<?php
/**
 * Homepage section registry. Each section type declares the fields the admin can edit;
 * the admin editor and the validator are generated from these definitions, and the
 * storefront renders includes/sections/<type>.php.
 */

function section_common_fields(): array
{
    return [
        'bg_color' => ['type' => 'color', 'label' => 'Background colour', 'default' => ''],
        'text_color' => ['type' => 'color', 'label' => 'Text colour', 'default' => ''],
        'padding_top' => ['type' => 'number', 'label' => 'Space above (px, blank = theme default)', 'default' => ''],
        'padding_bottom' => ['type' => 'number', 'label' => 'Space below (px, blank = theme default)', 'default' => ''],
        'width' => ['type' => 'select', 'label' => 'Content width', 'options' => ['container' => 'Standard', 'wide' => 'Wide', 'full' => 'Full width'], 'default' => 'container'],
        'animation' => ['type' => 'select', 'label' => 'Entrance animation', 'options' => ['fade-up' => 'Fade up', 'fade' => 'Fade', 'zoom' => 'Soft zoom', 'none' => 'None'], 'default' => 'fade-up'],
    ];
}

function section_types(): array
{
    $heading = [
        'eyebrow' => ['type' => 'text', 'label' => 'Small heading (eyebrow)'],
        'title' => ['type' => 'text', 'label' => 'Heading'],
        'subtitle' => ['type' => 'textarea', 'label' => 'Subheading'],
    ];
    $button = [
        'button_text' => ['type' => 'text', 'label' => 'Button text'],
        'button_url' => ['type' => 'url', 'label' => 'Button link'],
    ];
    return [
        'hero' => ['label' => 'Hero Banner Carousel', 'fields' => [
            'autoplay' => ['type' => 'checkbox', 'label' => 'Autoplay', 'default' => '1'],
            'interval' => ['type' => 'number', 'label' => 'Slide duration (milliseconds)', 'default' => '6000'],
            'transition' => ['type' => 'select', 'label' => 'Transition', 'options' => ['fade' => 'Fade', 'slide' => 'Slide', 'zoom' => 'Fade + Ken Burns zoom'], 'default' => 'zoom'],
            'show_arrows' => ['type' => 'checkbox', 'label' => 'Show arrows', 'default' => '1'],
            'show_dots' => ['type' => 'checkbox', 'label' => 'Show dots', 'default' => '1'],
            'parallax' => ['type' => 'checkbox', 'label' => 'Parallax on scroll', 'default' => '1'],
        ], 'note' => 'Slides are managed under Homepage » Hero Slides.'],
        'usp' => ['label' => 'Service Highlights Bar', 'fields' => [
            'items' => ['type' => 'textarea', 'label' => 'One per line: icon | title | text (icons: bootstrap-icons names e.g. truck, arrow-repeat, cash-coin, shield-check)'],
        ]],
        'categories' => ['label' => 'Shop by Category', 'fields' => $heading + [
            'source' => ['type' => 'select', 'label' => 'Categories to show', 'options' => ['home' => 'Categories marked "Show on homepage"', 'manual' => 'Selected below'], 'default' => 'home'],
            'category_ids' => ['type' => 'categories', 'label' => 'Selected categories (incl. subcategories)'],
            'limit' => ['type' => 'number', 'label' => 'Maximum categories', 'default' => '8'],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['editorial' => 'Editorial mosaic', 'grid' => 'Even grid', 'carousel' => 'Carousel'], 'default' => 'editorial'],
        ] + $button],
        'new_arrivals' => ['label' => 'New Arrivals Carousel', 'fields' => $heading + [
            'mode' => ['type' => 'select', 'label' => 'Products', 'options' => ['latest' => 'Latest products automatically', 'manual' => 'Selected products'], 'default' => 'latest'],
            'product_ids' => ['type' => 'products', 'label' => 'Selected products'],
            'limit' => ['type' => 'number', 'label' => 'Number of products', 'default' => '10'],
            'autoplay' => ['type' => 'checkbox', 'label' => 'Autoplay carousel', 'default' => '0'],
        ] + $button],
        'editorial' => ['label' => 'Editorial Feature', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'text' => ['type' => 'html', 'label' => 'Text'],
            'image' => ['type' => 'image', 'label' => 'Main image'],
            'image_mobile' => ['type' => 'image', 'label' => 'Mobile image (optional)'],
            'image_secondary' => ['type' => 'image', 'label' => 'Detail image (optional)'],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['image-left' => 'Image left', 'image-right' => 'Image right', 'overlay' => 'Full-bleed with text overlay'], 'default' => 'image-left'],
            'text_align' => ['type' => 'select', 'label' => 'Text alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'default' => 'left'],
            'parallax' => ['type' => 'checkbox', 'label' => 'Parallax image', 'default' => '1'],
        ] + $button],
        'best_sellers' => ['label' => 'Best Sellers Carousel', 'fields' => $heading + [
            'mode' => ['type' => 'select', 'label' => 'Ranking', 'options' => ['units' => 'Units sold (all active orders)', 'completed' => 'Units sold (delivered orders only)', 'flag' => 'Products marked "Best seller"', 'manual' => 'Selected products'], 'default' => 'units'],
            'product_ids' => ['type' => 'products', 'label' => 'Selected products'],
            'limit' => ['type' => 'number', 'label' => 'Number of products', 'default' => '10'],
        ] + $button],
        'collections' => ['label' => 'Shop by Style / Collections', 'fields' => $heading + [
            'collection_ids' => ['type' => 'collections', 'label' => 'Collections (blank = those marked "Show on homepage")'],
            'limit' => ['type' => 'number', 'label' => 'Maximum collections', 'default' => '6'],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['mosaic' => 'Mosaic', 'grid' => 'Grid', 'carousel' => 'Carousel'], 'default' => 'mosaic'],
        ] + $button],
        'promo_banner' => ['label' => 'Full-width Promo Banner', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'text' => ['type' => 'textarea', 'label' => 'Text'],
            'image' => ['type' => 'image', 'label' => 'Desktop image'],
            'image_mobile' => ['type' => 'image', 'label' => 'Mobile image'],
            'height' => ['type' => 'number', 'label' => 'Height (vh, 30–100)', 'default' => '70'],
            'overlay_opacity' => ['type' => 'number', 'label' => 'Overlay opacity (0–90)', 'default' => '35'],
            'text_align' => ['type' => 'select', 'label' => 'Text alignment', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'default' => 'center'],
            'parallax' => ['type' => 'checkbox', 'label' => 'Parallax', 'default' => '1'],
        ] + $button],
        'story' => ['label' => 'Brand Story', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'text' => ['type' => 'html', 'label' => 'Story text'],
            'image' => ['type' => 'image', 'label' => 'Main image'],
            'image_secondary' => ['type' => 'image', 'label' => 'Supporting image'],
            'video_url' => ['type' => 'url', 'label' => 'Video URL (YouTube/Vimeo or .mp4, optional — replaces main image)'],
            'pillars' => ['type' => 'textarea', 'label' => 'Brand pillars, one per line: title | text'],
            'layout' => ['type' => 'select', 'label' => 'Layout', 'options' => ['image-left' => 'Image left', 'image-right' => 'Image right'], 'default' => 'image-right'],
        ] + $button],
        'testimonials' => ['label' => 'Customer Reviews', 'fields' => $heading + [
            'limit' => ['type' => 'number', 'label' => 'Number of reviews', 'default' => '6'],
        ], 'note' => 'Shows genuine approved product reviews marked "Feature on homepage" in Reviews. Hidden automatically when there are none.'],
        'newsletter' => ['label' => 'Newsletter Signup', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'text' => ['type' => 'textarea', 'label' => 'Text'],
            'button_text' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Subscribe'],
            'consent_text' => ['type' => 'text', 'label' => 'Consent checkbox text'],
            'image' => ['type' => 'image', 'label' => 'Background image (optional)'],
        ]],
        'instagram' => ['label' => 'Instagram Gallery', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'handle' => ['type' => 'text', 'label' => 'Handle (e.g. @urban_collection11)'],
            'profile_url' => ['type' => 'url', 'label' => 'Profile URL'],
            'image_1' => ['type' => 'image', 'label' => 'Image 1'], 'image_2' => ['type' => 'image', 'label' => 'Image 2'],
            'image_3' => ['type' => 'image', 'label' => 'Image 3'], 'image_4' => ['type' => 'image', 'label' => 'Image 4'],
            'image_5' => ['type' => 'image', 'label' => 'Image 5'], 'image_6' => ['type' => 'image', 'label' => 'Image 6'],
        ]],
        'rich_text' => ['label' => 'Text Block', 'fields' => [
            'eyebrow' => ['type' => 'text', 'label' => 'Small heading'],
            'title' => ['type' => 'text', 'label' => 'Heading'],
            'text' => ['type' => 'html', 'label' => 'Content'],
            'text_align' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left' => 'Left', 'center' => 'Center'], 'default' => 'center'],
        ] + $button],
    ];
}

function section_fields(string $type): array
{
    $types = section_types();
    return isset($types[$type]) ? $types[$type]['fields'] + section_common_fields() : [];
}

/** Settings with defaults filled in. */
function section_settings(array $section): array
{
    $saved = json_decode((string) $section['settings'], true) ?: [];
    $out = [];
    foreach (section_fields($section['section_type']) as $key => $f) {
        $out[$key] = $saved[$key] ?? ($f['default'] ?? (in_array($f['type'], ['products', 'collections', 'categories'], true) ? [] : ''));
    }
    return $out;
}

/**
 * Validates admin input for a section against its field definitions.
 * Image fields accept an upload ("<key>_file"), a pasted URL, or keep the existing value.
 */
function section_sanitize(string $type, array $in, array $existing): array
{
    $out = [];
    foreach (section_fields($type) as $key => $f) {
        $v = $in[$key] ?? null;
        switch ($f['type']) {
            case 'checkbox':
                $out[$key] = !empty($v) ? '1' : '0';
                break;
            case 'number':
                $out[$key] = ($v === null || $v === '') ? '' : (string) (int) $v;
                break;
            case 'color':
                $out[$key] = is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : '';
                break;
            case 'select':
                $out[$key] = is_string($v) && isset($f['options'][$v]) ? $v : ($f['default'] ?? array_key_first($f['options']));
                break;
            case 'html':
                $out[$key] = sanitize_html(is_string($v) ? $v : '');
                break;
            case 'url':
                $v = trim((string) $v);
                $out[$key] = ($v === '' || preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $v) || preg_match('#^[a-z0-9\-/]+$#i', $v)) ? $v : '';
                break;
            case 'products':
            case 'collections':
            case 'categories':
                $out[$key] = array_values(array_unique(array_filter(array_map('intval', is_array($v) ? $v : explode(',', (string) $v)))));
                break;
            case 'image':
                $uploaded = upload_image($key . '_file');
                if ($uploaded) {
                    $out[$key] = $uploaded;
                } elseif (!empty($in[$key . '_remove'])) {
                    $out[$key] = '';
                } else {
                    $url = trim((string) $v);
                    $out[$key] = ($url === '' || preg_match('#^https?://#i', $url) || preg_match('#^[a-z0-9_\-/\.]+$#i', $url)) ? $url : ($existing[$key] ?? '');
                }
                break;
            default:
                $out[$key] = mb_substr(trim(strip_tags((string) $v)), 0, $f['type'] === 'textarea' ? 2000 : 255);
        }
    }
    return $out;
}

/** Inline style + classes shared by every section wrapper. */
function section_wrapper_attrs(array $s, string $type, int $id): string
{
    $style = [];
    if (!empty($s['bg_color'])) {
        $style[] = 'background-color:' . safe_color($s['bg_color']);
    }
    if (!empty($s['text_color'])) {
        $style[] = 'color:' . safe_color($s['text_color']) . ';--uc-section-text:' . safe_color($s['text_color']);
    }
    if ($s['padding_top'] !== '' && $s['padding_top'] !== null) {
        $style[] = 'padding-top:' . max(0, min(300, (int) $s['padding_top'])) . 'px';
    }
    if ($s['padding_bottom'] !== '' && $s['padding_bottom'] !== null) {
        $style[] = 'padding-bottom:' . max(0, min(300, (int) $s['padding_bottom'])) . 'px';
    }
    return 'id="section-' . $id . '" class="uc-section uc-section--' . e($type) . '"' . ($style ? ' style="' . e(implode(';', $style)) . '"' : '');
}

function section_container_class(array $s): string
{
    return ['full' => 'container-fluid px-0', 'wide' => 'uc-container-wide'][$s['width'] ?? ''] ?? 'uc-container';
}

/** data-reveal attribute for scroll animations. */
function reveal(array $s, int $delay = 0): string
{
    $anim = $s['animation'] ?? 'fade-up';
    if ($anim === 'none' || !setting_bool('theme_animations', true)) {
        return '';
    }
    return ' data-reveal="' . e($anim) . '"' . ($delay ? ' style="--reveal-delay:' . $delay . 'ms"' : '');
}

function homepage_sections(bool $preview = false): array
{
    $statuses = $preview ? "('published','draft')" : "('published')";
    return db_all("SELECT * FROM homepage_sections WHERE status IN $statuses ORDER BY sort_order, id");
}

function render_home_sections(bool $preview = false): void
{
    $types = section_types();
    foreach (homepage_sections($preview) as $section) {
        $type = $section['section_type'];
        if (!isset($types[$type]) || !is_file(UC_ROOT . '/includes/sections/' . $type . '.php')) {
            continue;
        }
        $s = section_settings($section);
        if ($preview && $section['status'] === 'draft') {
            echo '<div class="uc-preview-flag">Draft section: ' . e($section['admin_label']) . '</div>';
        }
        partial('sections/' . $type, ['s' => $s, 'section' => $section, 'sid' => (int) $section['id']]);
    }
}

/** Section heading block used by most sections. */
function section_heading(array $s, string $align = 'center', bool $withButton = false): string
{
    if (empty($s['eyebrow']) && empty($s['title']) && empty($s['subtitle'])) {
        return '';
    }
    $html = '<header class="uc-section-head text-' . e($align) . '"' . reveal($s) . '>';
    if (!empty($s['eyebrow'])) {
        $html .= '<p class="uc-eyebrow">' . e($s['eyebrow']) . '</p>';
    }
    if (!empty($s['title'])) {
        $html .= '<h2 class="uc-section-title">' . e($s['title']) . '</h2>';
    }
    if (!empty($s['subtitle'])) {
        $html .= '<p class="uc-section-sub">' . e($s['subtitle']) . '</p>';
    }
    if ($withButton && !empty($s['button_text'])) {
        $html .= '<a class="uc-link-arrow" href="' . e(url($s['button_url'] ?: 'shop')) . '">' . e($s['button_text']) . ' <i class="bi bi-arrow-right"></i></a>';
    }
    return $html . '</header>';
}
