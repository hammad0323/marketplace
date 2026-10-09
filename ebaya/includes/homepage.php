<?php
/**
 * Homepage builder. Each section's editable fields are declared here; the
 * admin form, validation and storefront defaults all come from this schema.
 * Settings are stored as JSON in homepage_sections.settings (published) and
 * draft_settings (preview). Only presentation config lives in that JSON.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function hp_appearance_fields(array $defaults = []): array
{
    return [
        'bg_color' => ['Background colour', 'color', $defaults['bg_color'] ?? '#F8F5EF', 'Appearance'],
        'text_color' => ['Text colour', 'color', $defaults['text_color'] ?? '#332820', 'Appearance'],
        'pad_top' => ['Space above (px)', 'number', $defaults['pad_top'] ?? 110, 'Appearance'],
        'pad_bottom' => ['Space below (px)', 'number', $defaults['pad_bottom'] ?? 110, 'Appearance'],
        'width' => ['Content width', 'select', $defaults['width'] ?? 'container', 'Appearance', ['container' => 'Standard', 'wide' => 'Wide', 'full' => 'Full width']],
        'align' => ['Heading alignment', 'select', $defaults['align'] ?? 'center', 'Appearance', ['center' => 'Centre', 'left' => 'Left']],
        'animation' => ['Reveal animation', 'select', 'inherit', 'Appearance', ['inherit' => 'Theme default', 'fade-up' => 'Fade up', 'fade' => 'Fade', 'zoom' => 'Soft zoom', 'none' => 'None']],
    ];
}

/** section_key => [label, fields[key => [label, type, default, group, options?]], item_type|null] */
function homepage_schema(): array
{
    return [
        'hero' => ['Hero banner carousel', [
            'autoplay' => ['Autoplay', 'toggle', 1, 'Carousel'],
            'duration' => ['Slide duration (ms)', 'number', 6000, 'Carousel'],
            'transition' => ['Transition', 'select', 'fade', 'Carousel', ['fade' => 'Cross-fade', 'slide' => 'Slide', 'zoom' => 'Fade with slow zoom']],
            'show_arrows' => ['Show arrows', 'toggle', 1, 'Carousel'],
            'show_dots' => ['Show pagination', 'toggle', 1, 'Carousel'],
            'scroll_cue' => ['Show "scroll" cue', 'toggle', 1, 'Carousel'],
        ], null],
        'categories' => ['Shop by category', array_merge([
            'eyebrow' => ['Small heading', 'text', 'The Collections', 'Content'],
            'heading' => ['Heading', 'text', 'Shop by Category', 'Content'],
            'subheading' => ['Subheading', 'textarea', 'From quiet everyday elegance to occasion pieces finished entirely by hand.', 'Content'],
            'source' => ['Which categories', 'select', 'home_flag', 'Content', ['home_flag' => 'Categories marked "Show on homepage"', 'manual' => 'Hand-picked below', 'top' => 'All top-level categories']],
            'show_children' => ['Show subcategory links on cards', 'toggle', 1, 'Content'],
            'layout' => ['Layout', 'select', 'mosaic', 'Content', ['mosaic' => 'Editorial mosaic', 'grid' => 'Even grid', 'carousel' => 'Carousel']],
            'limit' => ['Maximum shown', 'number', 6, 'Content'],
            'cta_text' => ['Button text', 'text', 'View all abayas', 'Content'],
            'cta_url' => ['Button link', 'url', '/shop', 'Content'],
        ], hp_appearance_fields()), 'category'],
        'new_arrivals' => ['New arrivals', array_merge([
            'eyebrow' => ['Small heading', 'text', 'Just In', 'Content'],
            'heading' => ['Heading', 'text', 'New Arrivals', 'Content'],
            'subheading' => ['Subheading', 'textarea', 'The latest pieces from our atelier.', 'Content'],
            'source' => ['Products', 'select', 'latest', 'Content', ['latest' => 'Latest published automatically', 'flag' => 'Products marked "New arrival"', 'manual' => 'Hand-picked below']],
            'limit' => ['Number of products', 'number', 10, 'Content'],
            'cta_text' => ['Button text', 'text', 'Shop new arrivals', 'Content'],
            'cta_url' => ['Button link', 'url', '/new-arrivals', 'Content'],
        ], hp_appearance_fields(['bg_color' => '#FFFFFF'])), 'product'],
        'handcrafted' => ['Handcrafted with love', array_merge([
            'eyebrow' => ['Small heading', 'text', 'Handcrafted with Love', 'Content'],
            'heading' => ['Heading', 'text', 'Every Detail Tells a Story', 'Content'],
            'body' => ['Text', 'textarea', "Look closely and you will find the work of patient hands — floral motifs embroidered stitch by stitch, crochet flowers shaped one petal at a time, cuffs and necklines finished with quiet care.\n\nThese are details made to be noticed up close, and to be worn for years.", 'Content'],
            'cta_text' => ['Button text', 'text', 'Explore the Handcrafted Collection', 'Content'],
            'cta_url' => ['Button link', 'url', '/collections/handcrafted', 'Content'],
            'image_1' => ['Detail image 1', 'image', 'assets/img/sample/detail-crochet.svg', 'Images'],
            'caption_1' => ['Caption 1', 'text', 'Handmade crochet flowers', 'Images'],
            'image_2' => ['Detail image 2', 'image', 'assets/img/sample/detail-embroidery.svg', 'Images'],
            'caption_2' => ['Caption 2', 'text', 'Hand-embroidered florals', 'Images'],
            'image_3' => ['Detail image 3', 'image', 'assets/img/sample/detail-cuff.svg', 'Images'],
            'caption_3' => ['Caption 3', 'text', 'Carefully stitched cuffs', 'Images'],
            'image_4' => ['Detail image 4', 'image', 'assets/img/sample/detail-neckline.svg', 'Images'],
            'caption_4' => ['Caption 4', 'text', 'Embellished necklines', 'Images'],
            'image_5' => ['Detail image 5', 'image', 'assets/img/sample/detail-thread.svg', 'Images'],
            'caption_5' => ['Caption 5', 'text', 'Delicate threadwork', 'Images'],
            'image_6' => ['Detail image 6', 'image', 'assets/img/sample/detail-sleeve.svg', 'Images'],
            'caption_6' => ['Caption 6', 'text', 'Decorative sleeve details', 'Images'],
        ], hp_appearance_fields(['bg_color' => '#E7D8C4'])), null],
        'best_sellers' => ['Best sellers', array_merge([
            'eyebrow' => ['Small heading', 'text', 'Most Loved', 'Content'],
            'heading' => ['Heading', 'text', 'Best Sellers', 'Content'],
            'subheading' => ['Subheading', 'textarea', 'The pieces our customers return to again and again.', 'Content'],
            'source' => ['Products', 'select', 'auto', 'Content', ['auto' => 'Ranked by units sold (completed orders), topped up with "Best seller" products', 'flag' => 'Products marked "Best seller"', 'manual' => 'Hand-picked below']],
            'days' => ['Sales window in days (0 = all time)', 'number', 0, 'Content'],
            'limit' => ['Number of products', 'number', 8, 'Content'],
            'cta_text' => ['Button text', 'text', 'Shop best sellers', 'Content'],
            'cta_url' => ['Button link', 'url', '/best-sellers', 'Content'],
        ], hp_appearance_fields(['bg_color' => '#F8F5EF'])), 'product'],
        'craft_collections' => ['Shop by craft or detail', array_merge([
            'eyebrow' => ['Small heading', 'text', 'Shop by Craft', 'Content'],
            'heading' => ['Heading', 'text', 'Defined by the Details', 'Content'],
            'subheading' => ['Subheading', 'textarea', 'Find your piece by the handwork that speaks to you.', 'Content'],
            'source' => ['Collections', 'select', 'home_flag', 'Content', ['home_flag' => 'Collections marked "Show on homepage"', 'manual' => 'Hand-picked below']],
            'layout' => ['Layout', 'select', 'grid', 'Content', ['grid' => 'Grid', 'carousel' => 'Carousel']],
            'limit' => ['Maximum shown', 'number', 6, 'Content'],
        ], hp_appearance_fields(['bg_color' => '#FFFFFF'])), 'collection'],
        'story' => ['The Ebaya story', array_merge([
            'eyebrow' => ['Small heading', 'text', 'The Ebaya Story', 'Content'],
            'heading' => ['Heading', 'text', 'Where Modesty Meets the Artistry of the Hand', 'Content'],
            'intro' => ['Brand introduction', 'textarea', 'Ebaya began with a simple belief: that modest dressing can be as expressive as it is graceful. Our abayas pair clean, contemporary silhouettes with the kind of detail that can only come from the hand.', 'Content'],
            'philosophy_heading' => ['Craft heading', 'text', 'Our Craft', 'Content'],
            'philosophy' => ['Craftsmanship philosophy', 'textarea', 'We favour slow, considered work — embroidery, crochet and finishing done with patience, so that each piece carries the character of the hands that made it.', 'Content'],
            'inspiration_heading' => ['Inspiration heading', 'text', 'Our Inspiration', 'Content'],
            'inspiration' => ['Modest-fashion inspiration', 'textarea', 'We design for women who move between everyday life and life’s celebrations, and want pieces that honour both tradition and the way they live now.', 'Content'],
            'image' => ['Featured image', 'image', 'assets/img/sample/story-main.svg', 'Media'],
            'video_url' => ['Featured video (MP4 URL or YouTube link, optional)', 'url', '', 'Media'],
            'image_2' => ['Supporting image 1', 'image', 'assets/img/sample/story-detail.svg', 'Media'],
            'image_3' => ['Supporting image 2', 'image', '', 'Media'],
            'layout' => ['Image position', 'select', 'left', 'Media', ['left' => 'Image left', 'right' => 'Image right']],
            'cta_text' => ['Button text', 'text', 'Discover Ebaya', 'Content'],
            'cta_url' => ['Button link', 'url', '/about-ebaya', 'Content'],
        ], hp_appearance_fields(['bg_color' => '#F8F5EF'])), null],
        'testimonials_newsletter' => ['Testimonials & newsletter', array_merge([
            'show_testimonials' => ['Show testimonials', 'toggle', 1, 'Testimonials'],
            't_eyebrow' => ['Testimonials small heading', 'text', 'Kind Words', 'Testimonials'],
            't_heading' => ['Testimonials heading', 'text', 'From Our Customers', 'Testimonials'],
            'show_ratings' => ['Show star ratings', 'toggle', 1, 'Testimonials'],
            'show_newsletter' => ['Show newsletter', 'toggle', 1, 'Newsletter'],
            'n_heading' => ['Newsletter heading', 'text', 'Join the Ebaya Circle', 'Newsletter'],
            'n_text' => ['Newsletter text', 'textarea', 'Be the first to see new collections, limited pieces and atelier stories.', 'Newsletter'],
            'n_consent' => ['Consent text', 'text', 'I agree to receive emails from Ebaya. I can unsubscribe at any time.', 'Newsletter'],
            'n_bg_color' => ['Newsletter background', 'color', '#354638', 'Newsletter'],
            'n_text_color' => ['Newsletter text colour', 'color', '#F8F5EF', 'Newsletter'],
            'n_image' => ['Newsletter background image (optional)', 'image', '', 'Newsletter'],
        ], hp_appearance_fields(['bg_color' => '#FFFFFF'])), null],
    ];
}

function homepage_sections(): array
{
    $preview = is_preview();
    $schema = homepage_schema();
    $rows = db_all('SELECT * FROM homepage_sections ORDER BY sort_order, id');
    $out = [];
    foreach ($rows as $r) {
        if (!isset($schema[$r['section_key']])) continue;
        $json = ($preview && $r['draft_settings'] !== null) ? $r['draft_settings'] : $r['settings'];
        $r['s'] = homepage_settings_merge($r['section_key'], json_decode((string)$json, true) ?: []);
        $out[] = $r;
    }
    return $out;
}

function homepage_settings_merge(string $key, array $saved): array
{
    $s = [];
    foreach (homepage_schema()[$key][1] as $k => $f) {
        $s[$k] = array_key_exists($k, $saved) ? $saved[$k] : $f[2];
    }
    return $s;
}

/** Validate posted values against the schema. */
function homepage_settings_from_post(string $key, array $current): array
{
    $out = [];
    foreach (homepage_schema()[$key][1] as $k => $f) {
        [$label, $type, $default] = $f;
        $v = $_POST['s'][$k] ?? null;
        switch ($type) {
            case 'toggle':
                $out[$k] = !empty($v) ? 1 : 0;
                break;
            case 'number':
                $out[$k] = is_numeric($v) ? max(0, min(20000, (int)$v)) : $default;
                break;
            case 'color':
                $out[$k] = is_string($v) && v_hex($v) ? strtoupper($v) : $default;
                break;
            case 'select':
                $out[$k] = is_string($v) && array_key_exists($v, $f[4]) ? $v : $default;
                break;
            case 'url':
                $out[$k] = is_string($v) ? clean_url($v) : '';
                break;
            case 'image':
                $field = 'img_' . $k;
                $new = upload_optional($field, 'content');
                if ($new) $out[$k] = $new;
                elseif (!empty($_POST['remove'][$k])) $out[$k] = '';
                else $out[$k] = $current[$k] ?? $default;
                break;
            case 'textarea':
                $out[$k] = is_string($v) ? mb_substr(trim($v), 0, 4000) : '';
                break;
            default:
                $out[$k] = is_string($v) ? mb_substr(trim(strip_tags($v)), 0, 300) : '';
        }
    }
    return $out;
}

function homepage_section_item_ids(int $sectionId, string $type): array
{
    return array_map('intval', db_col('SELECT item_id FROM homepage_section_items WHERE section_id = ? AND item_type = ? ORDER BY sort_order', [$sectionId, $type]));
}

/** Inline style + data attributes for a section wrapper. */
function hp_section_attrs(array $s, string $class = ''): string
{
    $style = '';
    if (!empty($s['bg_color'])) $style .= 'background-color:' . $s['bg_color'] . ';';
    if (!empty($s['text_color'])) $style .= '--sec-text:' . $s['text_color'] . ';color:' . $s['text_color'] . ';';
    if (isset($s['pad_top'])) $style .= '--sec-pt:' . (int)$s['pad_top'] . 'px;';
    if (isset($s['pad_bottom'])) $style .= '--sec-pb:' . (int)$s['pad_bottom'] . 'px;';
    $anim = ($s['animation'] ?? 'inherit') === 'inherit' ? setting('anim_style', 'fade-up') : $s['animation'];
    return 'class="hp-section ' . e($class) . '" style="' . e($style) . '" data-anim="' . e($anim) . '"';
}

function hp_container_class(array $s): string
{
    return ['wide' => 'container-wide', 'full' => 'container-fluid px-0'][$s['width'] ?? 'container'] ?? 'container-eb';
}

function hp_heading(array $s, string $eyebrowKey = 'eyebrow', string $headingKey = 'heading', string $subKey = 'subheading'): string
{
    $align = ($s['align'] ?? 'center') === 'left' ? 'text-start' : 'text-center mx-auto';
    $h = '<div class="section-head ' . $align . '" data-reveal>';
    if (!empty($s[$eyebrowKey])) $h .= '<span class="eyebrow">' . e($s[$eyebrowKey]) . '</span>';
    if (!empty($s[$headingKey])) $h .= '<h2 class="section-title">' . e($s[$headingKey]) . '</h2>';
    if (!empty($s[$subKey])) $h .= '<p class="section-sub">' . nl2br(e($s[$subKey])) . '</p>';
    return $h . '</div>';
}
