<?php
/**
 * Seed generator for the Digital Signage Platform.
 *
 * Usage (from repository root):
 *     php signage-platform/backend/database/tools/generate_seed.php
 *
 * Produces:
 *     backend/database/seed.sql
 *     backend/uploads/images/samples/*.jpg      (sample media, requires GD)
 *     backend/uploads/thumbnails/samples/*.jpg
 *
 * The output is deterministic except for bcrypt salts, so re-running it only
 * changes the password hash lines. seed.sql is committed, so running this
 * script is only necessary when templates / widget defaults change.
 */

declare(strict_types=1);

$ROOT    = dirname(__DIR__, 2);                 // backend/
$OUT_SQL = $ROOT . '/database/seed.sql';
$UPLOADS = $ROOT . '/uploads';

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------
function q($v): string
{
    if ($v === null) return 'NULL';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_int($v) || is_float($v)) return (string)$v;
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string)$v) . "'";
}

function j($v): string
{
    return q(json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/** Deterministic UUID-v4-shaped id derived from a name. */
function uid(string $name): string
{
    $h = md5('signage:' . $name);
    return sprintf('%s-%s-4%s-%s%s-%s',
        substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3),
        dechex((hexdec($h[16]) & 0x3) | 0x8), substr($h, 17, 3), substr($h, 20, 12));
}

function insert(string $table, array $rows): string
{
    if (!$rows) return '';
    $cols = array_keys($rows[0]);
    $out  = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES\n";
    $vals = [];
    foreach ($rows as $r) {
        $vals[] = '(' . implode(', ', array_map(fn($c) => $r[$c] instanceof Raw ? $r[$c]->sql : q($r[$c]), $cols)) . ')';
    }
    return $out . implode(",\n", $vals) . ";\n\n";
}

final class Raw { public function __construct(public string $sql) {} }
function raw(string $s): Raw { return new Raw($s); }
function jr($v): Raw { return raw(j($v)); }

// ---------------------------------------------------------------------------
// 1. Widget registry (default configs are the single source of truth that the
//    Flutter editor + player renderers read).
// ---------------------------------------------------------------------------
$FONT = 'Poppins';
$WIDGETS = [
    // key => [name, category, icon, availability, requires_network, premium, w, h, description, config]
    'image' => ['Image', 'media', 'image', 'stable', 0, 0, 0.40, 0.40, 'Single image from the media library.', [
        'media_id' => null, 'fit' => 'cover', 'alignment' => 'center', 'border_radius' => 0, 'opacity' => 1.0,
        'border_width' => 0, 'border_color' => '#FFFFFF', 'background_color' => null,
        'shadow_color' => null, 'shadow_blur' => 30, 'shadow_offset_x' => 0, 'shadow_offset_y' => 18,
        'animation' => 'none', 'animation_duration_ms' => 600, 'placeholder' => 'Image']],
    'image_carousel' => ['Image Carousel', 'media', 'view_carousel', 'stable', 0, 0, 0.50, 0.50, 'Rotating images inside one zone.', [
        'items' => [], 'fit' => 'cover', 'default_duration' => 5, 'transition' => 'fade', 'transition_ms' => 800,
        'loop' => true, 'pause_ms' => 0, 'shuffle' => false, 'border_radius' => 0]],
    'video' => ['Video', 'media', 'smart_display', 'stable', 0, 0, 0.50, 0.50, 'MP4 (H.264/AAC) video or video sequence.', [
        'items' => [], 'autoplay' => true, 'loop' => true, 'muted' => true, 'start_sec' => 0, 'end_sec' => null,
        'fit' => 'cover', 'border_radius' => 0]],
    'logo' => ['Logo', 'media', 'verified', 'stable', 0, 0, 0.15, 0.12, 'Business logo.', [
        'media_id' => null, 'fit' => 'contain', 'alignment' => 'center', 'opacity' => 1.0, 'placeholder' => 'Logo']],
    'background_image' => ['Background Image', 'media', 'wallpaper', 'stable', 0, 0, 1.0, 1.0, 'Full-zone decorative image with blur/overlay.', [
        'media_id' => null, 'fit' => 'cover', 'blur' => 0, 'overlay_color' => '#000000', 'overlay_opacity' => 0.0]],
    'pdf_viewer' => ['PDF Viewer', 'media', 'picture_as_pdf', 'planned', 0, 0, 0.50, 0.70, 'Cycles through PDF pages.', [
        'media_id' => null, 'page_duration' => 10, 'fit' => 'contain', 'background_color' => '#FFFFFF']],
    'presentation' => ['Presentation', 'media', 'slideshow', 'planned', 0, 1, 0.60, 0.60, 'PowerPoint deck (server-side conversion to images required).', [
        'media_id' => null, 'slide_duration' => 10]],
    'youtube' => ['YouTube', 'embed', 'smart_display', 'planned', 1, 1, 0.60, 0.60, 'YouTube embed where playback rights and device support permit.', [
        'video_id' => '', 'muted' => true, 'loop' => true]],

    'heading' => ['Heading', 'text', 'title', 'stable', 0, 0, 0.80, 0.15, 'Large headline text.', [
        'text' => 'Your headline', 'font_family' => $FONT, 'font_size' => 96, 'font_weight' => 700, 'italic' => false,
        'color' => '#FFFFFF', 'align' => 'center', 'vertical_align' => 'center', 'background_color' => null,
        'letter_spacing' => 0, 'line_height' => 1.1, 'text_effect' => 'none', 'effect_color' => '#00000066',
        'effect_blur' => 12, 'effect_offset_y' => 4,
        'padding' => 0, 'border_radius' => 0, 'opacity' => 1.0, 'animation' => 'fade_in', 'animation_duration_ms' => 800]],
    'text' => ['Text', 'text', 'notes', 'stable', 0, 0, 0.60, 0.20, 'Paragraph / rich text.', [
        'text' => 'Add your text here', 'spans' => null, 'font_family' => $FONT, 'font_size' => 40, 'font_weight' => 400,
        'italic' => false, 'underline' => false, 'color' => '#FFFFFF', 'background_color' => null, 'align' => 'left',
        'vertical_align' => 'top', 'line_height' => 1.4, 'letter_spacing' => 0, 'text_direction' => 'ltr',
        'padding' => 0, 'border_radius' => 0, 'opacity' => 1.0, 'animation' => 'none', 'animation_duration_ms' => 600]],
    'ticker' => ['Scrolling Ticker', 'text', 'text_rotation_none', 'stable', 0, 0, 1.0, 0.08, 'Continuously scrolling messages.', [
        'messages' => ['Welcome!'], 'direction' => 'left', 'speed' => 120, 'font_family' => $FONT, 'font_size' => 42,
        'font_weight' => 600, 'color' => '#FFFFFF', 'background_color' => '#008C86', 'separator' => '•', 'gap' => 80, 'loop' => true]],
    'announcement' => ['Announcement Board', 'text', 'campaign', 'stable', 0, 0, 0.50, 0.50, 'List of rotating notices.', [
        'title' => 'Announcements', 'items' => [['title' => 'Notice', 'body' => 'Details go here']], 'rotate_seconds' => 8,
        'icon' => 'campaign', 'font_family' => $FONT, 'title_color' => '#FFFFFF', 'color' => '#E5F5F3',
        'accent_color' => '#008C86', 'background_color' => '#202124CC', 'border_radius' => 24]],
    'quote' => ['Quote of the Day', 'text', 'format_quote', 'stable', 0, 0, 0.60, 0.30, 'Rotating quotes with author.', [
        'quotes' => [['text' => 'Simplicity is the ultimate sophistication.', 'author' => 'Leonardo da Vinci']],
        'mode' => 'rotate', 'rotate_seconds' => 15, 'font_family' => 'Playfair Display', 'font_size' => 56,
        'color' => '#FFFFFF', 'accent_color' => '#008C86', 'align' => 'center']],
    'daily_message' => ['Daily Motivation', 'text', 'self_improvement', 'stable', 0, 0, 0.60, 0.20, 'Different message each day.', [
        'messages' => ['Every day is a fresh start.', 'Small steps, big results.', 'Make today count.'],
        'mode' => 'daily', 'font_family' => $FONT, 'font_size' => 48, 'font_weight' => 600, 'color' => '#FFFFFF', 'align' => 'center']],
    'emergency' => ['Emergency Banner', 'system', 'warning', 'stable', 0, 0, 1.0, 1.0, 'High-visibility alert.', [
        'title' => 'Attention', 'message' => 'Please follow staff instructions.', 'background_color' => '#B00020',
        'color' => '#FFFFFF', 'flash' => true, 'font_family' => $FONT]],

    'clock' => ['Clock', 'time', 'schedule', 'stable', 0, 0, 0.30, 0.20, 'Digital or analog clock with date.', [
        'style' => 'digital', 'design' => 'minimal', 'format' => '24h', 'show_seconds' => false, 'show_date' => true,
        'show_day' => true, 'date_format' => 'EEEE, d MMMM yyyy', 'timezone' => 'device', 'font_family' => $FONT,
        'font_size' => 120, 'font_weight' => 600, 'color' => '#FFFFFF', 'accent_color' => '#008C86', 'align' => 'center']],
    'world_clock' => ['World Clock', 'time', 'public', 'stable', 0, 0, 0.60, 0.20, 'Several time zones side by side.', [
        'zones' => [['label' => 'London', 'timezone' => 'Europe/London'], ['label' => 'Dubai', 'timezone' => 'Asia/Dubai'], ['label' => 'New York', 'timezone' => 'America/New_York']],
        'format' => '24h', 'font_family' => $FONT, 'color' => '#FFFFFF', 'accent_color' => '#008C86']],
    'countdown' => ['Countdown Timer', 'time', 'hourglass_bottom', 'stable', 0, 0, 0.60, 0.25, 'Counts down to a date/time.', [
        'target' => '2026-12-31T23:59:59', 'timezone' => 'device', 'label' => 'Starts in', 'show_days' => true,
        'show_seconds' => true, 'finished_text' => "It's time!", 'font_family' => $FONT, 'font_size' => 96,
        'color' => '#FFFFFF', 'accent_color' => '#008C86']],
    'countup' => ['Count-up Timer', 'time', 'timer', 'stable', 0, 0, 0.50, 0.20, 'Elapsed time since a date.', [
        'start' => '2026-01-01T00:00:00', 'timezone' => 'device', 'label' => 'Days without incident', 'unit' => 'days',
        'font_family' => $FONT, 'font_size' => 96, 'color' => '#FFFFFF']],
    'calendar' => ['Calendar', 'time', 'calendar_month', 'stable', 0, 0, 0.40, 0.50, 'Month view with optional events.', [
        'view' => 'month', 'first_day_of_week' => 1, 'events' => [], 'font_family' => $FONT, 'color' => '#FFFFFF',
        'highlight_color' => '#008C86', 'background_color' => '#202124CC', 'border_radius' => 24]],
    'daily_schedule' => ['Daily Schedule', 'time', 'event_note', 'stable', 0, 0, 0.45, 0.60, 'Timetable that highlights the current slot.', [
        'title' => "Today's Schedule", 'items' => [['start' => '09:00', 'end' => '10:00', 'title' => 'Session', 'subtitle' => '']],
        'highlight_current' => true, 'font_family' => $FONT, 'color' => '#FFFFFF', 'accent_color' => '#008C86',
        'background_color' => '#202124CC', 'border_radius' => 24]],

    'weather' => ['Weather', 'data', 'partly_cloudy_day', 'beta', 1, 0, 0.35, 0.30, 'Current weather + forecast via configurable provider.', [
        'provider' => 'open_meteo', 'location_name' => 'Dubai', 'latitude' => 25.2048, 'longitude' => 55.2708,
        'units' => 'metric', 'show_forecast' => true, 'forecast_days' => 3, 'show_humidity' => true, 'show_wind' => true,
        'refresh_minutes' => 30, 'font_family' => $FONT, 'color' => '#FFFFFF', 'accent_color' => '#008C86',
        'background_color' => null, 'border_radius' => 24]],
    'rss_feed' => ['RSS News Feed', 'data', 'rss_feed', 'beta', 1, 0, 0.60, 0.40, 'Headlines from an RSS/Atom feed.', [
        'url' => '', 'max_items' => 10, 'rotate_seconds' => 8, 'show_image' => true, 'show_source' => true,
        'refresh_minutes' => 15, 'font_family' => $FONT, 'color' => '#FFFFFF', 'accent_color' => '#008C86',
        'background_color' => '#202124CC', 'border_radius' => 24]],
    'live_data' => ['Live Data', 'data', 'monitoring', 'beta', 1, 1, 0.30, 0.20, 'Value from a JSON endpoint.', [
        'url' => '', 'json_path' => '$.value', 'refresh_seconds' => 60, 'label' => 'Live value', 'prefix' => '',
        'suffix' => '', 'font_family' => $FONT, 'font_size' => 96, 'color' => '#FFFFFF']],
    'battery_indicator' => ['Battery Indicator', 'system', 'battery_full', 'planned', 0, 0, 0.12, 0.06, 'Device battery level (where supported).', [
        'show_percent' => true, 'color' => '#FFFFFF', 'low_color' => '#E53935']],
    'qr_code' => ['QR Code', 'data', 'qr_code_2', 'stable', 0, 0, 0.18, 0.30, 'QR code for any URL or text.', [
        'data' => 'https://example.com', 'foreground' => '#202124', 'background' => '#FFFFFF', 'label' => 'Scan me',
        'label_color' => '#FFFFFF', 'error_correction' => 'M', 'padding' => 16, 'border_radius' => 16]],
    'social_feed' => ['Social Media Feed', 'embed', 'share', 'planned', 1, 1, 0.40, 0.60, 'Posts from connected social accounts.', [
        'network' => 'instagram', 'account' => '', 'max_items' => 6, 'rotate_seconds' => 10]],

    'menu_board' => ['Menu Board', 'business', 'restaurant_menu', 'stable', 0, 0, 0.45, 0.80, 'Restaurant / cafe menu with sections.', [
        'title' => 'Menu', 'currency' => '$', 'columns' => 1,
        'sections' => [['name' => 'Mains', 'items' => [['name' => 'Dish', 'description' => '', 'price' => '9.90', 'tags' => []]]]],
        'font_family' => $FONT, 'title_color' => '#FFFFFF', 'section_color' => '#008C86', 'item_color' => '#FFFFFF',
        'description_color' => '#B8BEC6', 'price_color' => '#FFFFFF', 'background_color' => null, 'border_radius' => 0]],
    'price_list' => ['Price List', 'business', 'sell', 'stable', 0, 0, 0.40, 0.60, 'Simple service / product prices.', [
        'title' => 'Prices', 'currency' => '$', 'items' => [['name' => 'Service', 'price' => '25', 'note' => '']],
        'font_family' => $FONT, 'title_color' => '#FFFFFF', 'item_color' => '#FFFFFF', 'price_color' => '#008C86',
        'background_color' => '#202124CC', 'border_radius' => 24]],
    'product_showcase' => ['Product Showcase', 'business', 'shopping_bag', 'stable', 0, 0, 0.50, 0.70, 'Rotating product cards with price + badge.', [
        'items' => [['media_id' => null, 'name' => 'Product', 'description' => '', 'price' => '49.00', 'badge' => 'NEW']],
        'currency' => '$', 'rotate_seconds' => 8, 'layout' => 'card', 'font_family' => $FONT, 'color' => '#FFFFFF',
        'accent_color' => '#008C86', 'background_color' => '#202124CC', 'border_radius' => 24]],
    'contact_info' => ['Contact Information', 'business', 'contact_phone', 'stable', 0, 0, 0.40, 0.35, 'Phone, email, website and address.', [
        'title' => 'Contact us', 'phone' => '+1 555 0100', 'email' => 'hello@example.com', 'website' => 'www.example.com',
        'address' => '123 Main Street', 'show_icons' => true, 'font_family' => $FONT, 'color' => '#FFFFFF',
        'accent_color' => '#008C86', 'background_color' => null]],
    'opening_hours' => ['Opening Hours', 'business', 'storefront', 'stable', 0, 0, 0.35, 0.50, 'Weekly business hours, today highlighted.', [
        'title' => 'Opening Hours', 'days' => array_map(fn($d) => ['day' => $d, 'open' => '09:00', 'close' => '18:00', 'closed' => $d === 7],
            [1, 2, 3, 4, 5, 6, 7]), 'highlight_today' => true, 'closed_text' => 'Closed', 'format' => '24h',
        'font_family' => $FONT, 'color' => '#FFFFFF', 'accent_color' => '#008C86', 'background_color' => '#202124CC', 'border_radius' => 24]],
    'location_info' => ['Location', 'business', 'location_on', 'stable', 0, 0, 0.40, 0.20, 'Address / directions block.', [
        'title' => 'Find us', 'address' => '123 Main Street', 'directions' => '', 'font_family' => $FONT,
        'color' => '#FFFFFF', 'accent_color' => '#008C86']],

    'webpage' => ['Website / URL', 'embed', 'language', 'beta', 1, 1, 0.60, 0.60, 'Live web page (WebView).', [
        'url' => 'https://example.com', 'refresh_minutes' => 0, 'zoom' => 1.0, 'allow_interaction' => false]],
    'custom_html' => ['Custom HTML', 'embed', 'code', 'beta', 0, 1, 0.60, 0.60, 'Sandboxed HTML5 snippet.', [
        'html' => '<div style="color:#fff;font:48px sans-serif">Hello</div>', 'css' => '', 'allow_scripts' => false]],

    'shape' => ['Shape / Panel', 'layout', 'crop_square', 'stable', 0, 0, 0.30, 0.30, 'Rectangle, circle or line for layout panels.', [
        'shape' => 'rectangle', 'fill_color' => '#202124', 'gradient' => null, 'border_color' => null,
        'border_width' => 0, 'border_radius' => 24, 'opacity' => 1.0, 'shadow' => false,
        'shadow_color' => '#00000055', 'shadow_blur' => 30, 'shadow_offset_x' => 0, 'shadow_offset_y' => 12]],
];

/** Builds a widget for a layout document. Overrides are merged into the registry default. */
function w(string $type, string $name, float $x, float $y, float $wd, float $ht, int $z, array $cfg = [], array $extra = []): array
{
    global $WIDGETS;
    if (!isset($WIDGETS[$type])) throw new RuntimeException("Unknown widget {$type}");
    return array_merge([
        'widget_type'   => $type,
        'widget_name'   => $name,
        'position_x'    => round($x, 6),
        'position_y'    => round($y, 6),
        'width'         => round($wd, 6),
        'height'        => round($ht, 6),
        'rotation'      => 0,
        'z_index'       => $z,
        'is_locked'     => false,
        'is_visible'    => true,
        'configuration' => array_replace($WIDGETS[$type][9], $cfg),
    ], $extra);
}

function bg_color(string $c): array { return ['type' => 'color', 'value' => $c, 'media_id' => null, 'config' => ['opacity' => 1.0]]; }
function bg_grad(string $a, string $b, int $angle = 135): array
{
    return ['type' => 'gradient', 'value' => null, 'media_id' => null,
        'config' => ['gradient' => ['type' => 'linear', 'angle' => $angle, 'colors' => [$a, $b], 'stops' => [0.0, 1.0]], 'opacity' => 1.0]];
}
function layout(string $orientation, array $bg, array $widgets): array
{
    [$w, $h] = match ($orientation) { 'portrait' => [1080, 1920], 'square' => [1080, 1080], default => [1920, 1080] };
    return ['schema_version' => 1, 'canvas' => ['orientation' => $orientation, 'width' => $w, 'height' => $h],
        'background' => $bg, 'widgets' => $widgets];
}

// ---------------------------------------------------------------------------
// 2. Template categories + 24 templates
// ---------------------------------------------------------------------------
$CATEGORIES = [
    ['business',   'Business & Corporate',     'Lobby welcome boards, KPIs and company information', 'business'],
    ['restaurant', 'Restaurant & Cafe',        'Menus, specials and promotions for food service',    'restaurant'],
    ['retail',     'Retail & Shopping',        'Product promotions and in-store offers',             'shopping_bag'],
    ['healthcare', 'Hospital & Healthcare',    'Patient information, timings and wayfinding',        'local_hospital'],
    ['education',  'Education & Schools',      'Notices, timetables and campus news',                'school'],
    ['hospitality','Hotel & Hospitality',      'Guest welcome, events and local information',        'hotel'],
    ['events',     'Events & Announcements',   'Countdowns, posters and event agendas',              'celebration'],
    ['community',  'Religious & Community',    'Prayer times, community notices and quotes',         'groups'],
    ['salon',      'Salon & Beauty',           'Service menus and price lists',                      'spa'],
    ['fitness',    'Gym & Fitness',            'Class schedules and motivation',                     'fitness_center'],
    ['real_estate','Real Estate',              'Property listings and showcases',                    'real_estate_agent'],
    ['minimal',    'Minimal & Creative',       'Clean, typographic and full-bleed layouts',          'auto_awesome'],
    ['news',       'News & Information',       'Headlines, weather and clocks',                      'newspaper'],
    ['menu_boards','Digital Menu Boards',      'Multi-column menu boards for counters',              'menu_book'],
];
$catId = [];
foreach ($CATEGORIES as $i => $c) $catId[$c[0]] = $i + 1;

$T = []; // template_key => [category, name, description, orientation, premium, tags, layout]
$TEAL = '#008C86'; $DTEAL = '#006B66'; $LTEAL = '#E5F5F3'; $CHAR = '#202124'; $DARK = '#17191C'; $GREY = '#858B93';

$T['corp_welcome_l'] = ['business', 'Corporate Welcome', 'Logo, welcome headline, clock and news ticker.', 'landscape', 0, 'welcome,lobby,office',
    layout('landscape', bg_grad($DTEAL, $DARK), [
        w('logo', 'Logo', 0.04, 0.06, 0.14, 0.12, 1),
        w('clock', 'Clock', 0.70, 0.05, 0.26, 0.16, 2, ['font_size' => 72, 'align' => 'right', 'date_format' => 'EEE, d MMM']),
        w('heading', 'Welcome', 0.08, 0.32, 0.84, 0.18, 3, ['text' => 'Welcome to Our Office', 'font_size' => 110]),
        w('text', 'Subtitle', 0.15, 0.52, 0.70, 0.10, 4, ['text' => 'Please check in at reception', 'align' => 'center', 'color' => $LTEAL, 'font_size' => 44]),
        w('ticker', 'Ticker', 0.0, 0.92, 1.0, 0.08, 5, ['messages' => ['Visitors must wear a badge', 'Wi-Fi: Guest / welcome2026', 'Have a great day!']]),
    ])];
$T['corp_info_split_l'] = ['business', 'Company Info Split', 'Image left, company story and contact details right.', 'landscape', 0, 'split,about,company',
    layout('landscape', bg_color($DARK), [
        w('image', 'Photo', 0.0, 0.0, 0.5, 1.0, 1, ['placeholder' => 'Office photo']),
        w('heading', 'Title', 0.55, 0.10, 0.40, 0.14, 2, ['text' => 'About Us', 'align' => 'left', 'font_size' => 88]),
        w('text', 'Story', 0.55, 0.27, 0.40, 0.30, 3, ['text' => 'We help local businesses grow with smart, beautiful technology. Founded in 2012, trusted by 4,000+ customers.', 'font_size' => 36, 'color' => '#D5D9DD']),
        w('contact_info', 'Contact', 0.55, 0.62, 0.40, 0.30, 4),
    ])];
$T['corp_kpi_three_col_l'] = ['business', 'Three-Column Highlights', 'Three cards for KPIs, values or services.', 'landscape', 0, 'three-column,kpi,cards',
    layout('landscape', bg_color($DARK), array_merge(
        [w('heading', 'Title', 0.05, 0.06, 0.90, 0.14, 1, ['text' => 'This Quarter at a Glance', 'font_size' => 80])],
        ...array_map(fn($i, $kpi) => [
            w('shape', "Card {$i}", 0.05 + $i * 0.31, 0.28, 0.28, 0.58, 2, ['fill_color' => $CHAR, 'border_radius' => 32]),
            w('heading', "Value {$i}", 0.07 + $i * 0.31, 0.36, 0.24, 0.18, 3, ['text' => $kpi[0], 'color' => $TEAL, 'font_size' => 120]),
            w('text', "Label {$i}", 0.07 + $i * 0.31, 0.58, 0.24, 0.20, 4, ['text' => $kpi[1], 'align' => 'center', 'font_size' => 36]),
        ], [0, 1, 2], [['98%', 'Customer satisfaction'], ['+24%', 'Revenue growth'], ['1.2k', 'New customers']])
    ))];
$T['restaurant_menu_l'] = ['restaurant', 'Restaurant Menu + Photo', 'Two-section menu with hero food photo.', 'landscape', 0, 'menu,food,restaurant',
    layout('landscape', bg_color($DARK), [
        w('menu_board', 'Menu', 0.04, 0.06, 0.52, 0.88, 1, ['title' => "Today's Menu", 'columns' => 2, 'sections' => [
            ['name' => 'Starters', 'items' => [['name' => 'Tomato Soup', 'description' => 'Basil, cream', 'price' => '6.50', 'tags' => ['V']], ['name' => 'Caesar Salad', 'description' => 'Parmesan, croutons', 'price' => '8.00', 'tags' => []]]],
            ['name' => 'Mains', 'items' => [['name' => 'Grilled Salmon', 'description' => 'Lemon butter', 'price' => '18.90', 'tags' => ['GF']], ['name' => 'Beef Burger', 'description' => 'Cheddar, fries', 'price' => '14.50', 'tags' => []], ['name' => 'Mushroom Risotto', 'description' => 'Truffle oil', 'price' => '13.00', 'tags' => ['V']]]],
        ]]),
        w('image', 'Food photo', 0.60, 0.06, 0.36, 0.70, 2, ['border_radius' => 32, 'placeholder' => 'Signature dish']),
        w('heading', 'Special', 0.60, 0.80, 0.36, 0.12, 3, ['text' => 'Chef\'s Special 15% off', 'font_size' => 48, 'background_color' => $TEAL, 'border_radius' => 24]),
    ])];
$T['cafe_menu_p'] = ['restaurant', 'Cafe Menu Portrait', 'Coffee menu with header photo for portrait screens.', 'portrait', 0, 'cafe,coffee,menu,portrait',
    layout('portrait', bg_grad($CHAR, $DARK, 180), [
        w('image', 'Header photo', 0.0, 0.0, 1.0, 0.30, 1, ['placeholder' => 'Coffee photo']),
        w('heading', 'Title', 0.06, 0.32, 0.88, 0.07, 2, ['text' => 'Coffee & More', 'font_size' => 96]),
        w('menu_board', 'Menu', 0.08, 0.41, 0.84, 0.50, 3, ['title' => '', 'sections' => [
            ['name' => 'Hot', 'items' => [['name' => 'Espresso', 'description' => '', 'price' => '2.80', 'tags' => []], ['name' => 'Flat White', 'description' => '', 'price' => '3.90', 'tags' => []], ['name' => 'Cappuccino', 'description' => '', 'price' => '3.80', 'tags' => []]]],
            ['name' => 'Cold', 'items' => [['name' => 'Iced Latte', 'description' => '', 'price' => '4.20', 'tags' => []], ['name' => 'Cold Brew', 'description' => '', 'price' => '4.50', 'tags' => []]]],
        ]]),
        w('ticker', 'Ticker', 0.0, 0.95, 1.0, 0.05, 4, ['messages' => ['Oat milk available', 'Ask about our loyalty card']]),
    ])];
$T['menu_three_col_l'] = ['menu_boards', 'Three-Column Menu Board', 'Counter menu board with three sections.', 'landscape', 1, 'menu,board,three-column,qsr',
    layout('landscape', bg_color($DARK), array_merge(
        [w('heading', 'Title', 0.04, 0.03, 0.92, 0.10, 1, ['text' => 'ORDER HERE', 'font_size' => 72, 'letter_spacing' => 8])],
        array_map(fn($i, $sec) => w('menu_board', "Column {$i}", 0.03 + $i * 0.325, 0.16, 0.30, 0.80, 2 + $i, ['title' => $sec[0], 'title_color' => $TEAL,
            'sections' => [['name' => '', 'items' => array_map(fn($it) => ['name' => $it[0], 'description' => '', 'price' => $it[1], 'tags' => []], $sec[1])]]]),
            [0, 1, 2], [
                ['Burgers', [['Classic', '8.50'], ['Double Cheese', '10.90'], ['Chicken Crunch', '9.20'], ['Veggie', '8.00']]],
                ['Sides', [['Fries', '3.00'], ['Onion Rings', '3.50'], ['Salad', '4.00'], ['Nuggets', '4.50']]],
                ['Drinks', [['Soda', '2.00'], ['Milkshake', '4.50'], ['Lemonade', '3.00'], ['Water', '1.50']]],
            ])
    ))];
$T['menu_specials_p'] = ['menu_boards', 'Daily Specials Portrait', 'Price list of daily specials with QR to full menu.', 'portrait', 0, 'specials,menu,portrait,qr',
    layout('portrait', bg_grad($DTEAL, $DARK, 180), [
        w('heading', 'Title', 0.06, 0.05, 0.88, 0.08, 1, ['text' => "Today's Specials", 'font_size' => 100]),
        w('price_list', 'Specials', 0.06, 0.16, 0.88, 0.56, 2, ['title' => '', 'items' => [['name' => 'Lamb Tagine', 'price' => '16', 'note' => 'with couscous'], ['name' => 'Fish of the Day', 'price' => '19', 'note' => 'market price'], ['name' => 'Pasta Primavera', 'price' => '12', 'note' => 'vegetarian']]]),
        w('qr_code', 'Menu QR', 0.32, 0.76, 0.36, 0.18, 3, ['data' => 'https://example.com/menu', 'label' => 'Full menu']),
    ])];
$T['retail_promo_video_l'] = ['retail', 'Video Promotion', 'Full-screen video with promotional text overlay.', 'landscape', 0, 'video,promo,sale',
    layout('landscape', bg_color('#000000'), [
        w('video', 'Promo video', 0.0, 0.0, 1.0, 1.0, 1),
        w('shape', 'Overlay', 0.0, 0.62, 1.0, 0.38, 2, ['fill_color' => '#00000099', 'border_radius' => 0]),
        w('heading', 'Offer', 0.05, 0.66, 0.60, 0.16, 3, ['text' => 'Summer Sale', 'align' => 'left', 'font_size' => 110]),
        w('text', 'Detail', 0.05, 0.83, 0.60, 0.10, 4, ['text' => 'Up to 50% off selected items — this week only', 'font_size' => 40]),
        w('heading', 'Badge', 0.72, 0.68, 0.22, 0.24, 5, ['text' => '-50%', 'background_color' => $TEAL, 'border_radius' => 200, 'font_size' => 110]),
    ])];
$T['retail_product_p'] = ['retail', 'Product Showcase Portrait', 'Rotating product cards with prices.', 'portrait', 0, 'product,showcase,portrait',
    layout('portrait', bg_color($DARK), [
        w('logo', 'Logo', 0.35, 0.03, 0.30, 0.06, 1),
        w('product_showcase', 'Products', 0.06, 0.12, 0.88, 0.70, 2, ['items' => [
            ['media_id' => null, 'name' => 'Wireless Headphones', 'description' => '40h battery, ANC', 'price' => '129.00', 'badge' => 'BESTSELLER'],
            ['media_id' => null, 'name' => 'Smart Watch', 'description' => 'Health + GPS', 'price' => '199.00', 'badge' => 'NEW'],
        ]]),
        w('ticker', 'Ticker', 0.0, 0.92, 1.0, 0.05, 3, ['messages' => ['Free delivery over $50', 'Ask our staff for a demo']]),
    ])];
$T['retail_image_ticker_l'] = ['retail', 'Image with Ticker', 'Full-bleed image with scrolling offers.', 'landscape', 0, 'image,ticker,offers',
    layout('landscape', bg_color($DARK), [
        w('image', 'Hero image', 0.0, 0.0, 1.0, 0.91, 1, ['placeholder' => 'Promotion image']),
        w('ticker', 'Ticker', 0.0, 0.91, 1.0, 0.09, 2, ['messages' => ['New arrivals every Monday', 'Members get 10% off', 'Open until 10 PM']]),
    ])];
$T['health_info_l'] = ['healthcare', 'Clinic Information', 'Doctor timings, clock and patient notices.', 'landscape', 0, 'clinic,hospital,timings',
    layout('landscape', bg_grad('#F5F6F7', '#E5F5F3'), [
        w('heading', 'Title', 0.04, 0.05, 0.60, 0.12, 1, ['text' => 'Outpatient Clinic', 'align' => 'left', 'color' => $CHAR, 'font_size' => 80]),
        w('clock', 'Clock', 0.68, 0.04, 0.28, 0.15, 2, ['font_size' => 70, 'color' => $CHAR, 'align' => 'right']),
        w('daily_schedule', 'Doctors', 0.04, 0.22, 0.55, 0.64, 3, ['title' => 'Doctors on Duty', 'background_color' => '#FFFFFF', 'color' => $CHAR, 'items' => [
            ['start' => '08:00', 'end' => '12:00', 'title' => 'Dr. Amina Khan', 'subtitle' => 'General Medicine · Room 4'],
            ['start' => '12:00', 'end' => '16:00', 'title' => 'Dr. Lucas Meyer', 'subtitle' => 'Pediatrics · Room 2'],
            ['start' => '16:00', 'end' => '20:00', 'title' => 'Dr. Sofia Rossi', 'subtitle' => 'Cardiology · Room 7']]]),
        w('announcement', 'Notices', 0.62, 0.22, 0.34, 0.64, 4, ['title' => 'Patient Notices', 'background_color' => $TEAL, 'items' => [
            ['title' => 'Flu vaccines', 'body' => 'Available at the pharmacy desk'], ['title' => 'Quiet please', 'body' => 'Keep phones on silent']]]),
        w('ticker', 'Ticker', 0.0, 0.91, 1.0, 0.09, 5, ['messages' => ['In an emergency call 112', 'Please sanitize your hands']]),
    ])];
$T['health_wayfinding_p'] = ['healthcare', 'Wayfinding Portrait', 'Directions board for lobbies and corridors.', 'portrait', 0, 'wayfinding,directions,hospital',
    layout('portrait', bg_color($DARK), [
        w('heading', 'Title', 0.06, 0.04, 0.88, 0.08, 1, ['text' => 'Directory', 'font_size' => 100]),
        w('price_list', 'Departments', 0.06, 0.15, 0.88, 0.60, 2, ['title' => '', 'currency' => '', 'items' => [
            ['name' => 'Emergency', 'price' => 'G', 'note' => 'Ground floor'], ['name' => 'Radiology', 'price' => '1', 'note' => 'Level 1'],
            ['name' => 'Maternity', 'price' => '2', 'note' => 'Level 2'], ['name' => 'Cafeteria', 'price' => '3', 'note' => 'Level 3']]]),
        w('qr_code', 'Map QR', 0.30, 0.79, 0.40, 0.17, 3, ['data' => 'https://example.com/map', 'label' => 'Interactive map']),
    ])];
$T['edu_notice_l'] = ['education', 'School Notice Board', 'Announcements, calendar and time.', 'landscape', 0, 'school,notice,calendar',
    layout('landscape', bg_grad($DTEAL, $CHAR), [
        w('heading', 'Title', 0.04, 0.05, 0.60, 0.12, 1, ['text' => 'Campus Notices', 'align' => 'left', 'font_size' => 80]),
        w('clock', 'Clock', 0.68, 0.04, 0.28, 0.15, 2, ['font_size' => 70, 'align' => 'right']),
        w('announcement', 'Notices', 0.04, 0.22, 0.54, 0.70, 3, ['items' => [
            ['title' => 'Science Fair', 'body' => 'Friday, Main Hall, 10:00'], ['title' => 'Library hours', 'body' => 'Open until 19:00 during exams'],
            ['title' => 'Sports Day', 'body' => 'Register with your class teacher']]]),
        w('calendar', 'Calendar', 0.62, 0.22, 0.34, 0.70, 4),
    ])];
$T['hotel_welcome_l'] = ['hospitality', 'Hotel Welcome', 'Guest welcome over photo, with weather and clock.', 'landscape', 1, 'hotel,welcome,weather',
    layout('landscape', bg_color($DARK), [
        w('background_image', 'Lobby photo', 0.0, 0.0, 1.0, 1.0, 1, ['overlay_opacity' => 0.45]),
        w('heading', 'Welcome', 0.06, 0.30, 0.60, 0.18, 2, ['text' => 'Welcome, Guests', 'align' => 'left', 'font_size' => 120, 'font_family' => 'Playfair Display']),
        w('text', 'Info', 0.06, 0.50, 0.55, 0.16, 3, ['text' => 'Breakfast 06:30 – 10:30 · Pool 07:00 – 21:00 · Spa on Level 2', 'font_size' => 38]),
        w('weather', 'Weather', 0.70, 0.08, 0.26, 0.32, 4, ['background_color' => '#00000055']),
        w('clock', 'Clock', 0.70, 0.74, 0.26, 0.18, 5, ['font_size' => 80, 'align' => 'right']),
    ])];
$T['event_countdown_l'] = ['events', 'Event Countdown', 'Big countdown with event details and RSVP QR.', 'landscape', 0, 'event,countdown,launch',
    layout('landscape', bg_grad($TEAL, $DARK, 160), [
        w('heading', 'Event', 0.05, 0.10, 0.90, 0.16, 1, ['text' => 'Grand Opening', 'font_size' => 120]),
        w('countdown', 'Countdown', 0.15, 0.32, 0.70, 0.30, 2, ['target' => '2026-12-12T18:00:00', 'label' => 'Doors open in']),
        w('text', 'Details', 0.05, 0.72, 0.65, 0.18, 3, ['text' => "12 December · 6 PM\nMain Atrium · Live music & giveaways", 'font_size' => 40]),
        w('qr_code', 'RSVP', 0.78, 0.64, 0.17, 0.30, 4, ['data' => 'https://example.com/rsvp', 'label' => 'RSVP']),
    ])];
$T['event_poster_p'] = ['events', 'Event Poster Portrait', 'Image-led event poster with date and QR.', 'portrait', 0, 'poster,event,portrait',
    layout('portrait', bg_color($DARK), [
        w('image', 'Poster image', 0.0, 0.0, 1.0, 0.50, 1, ['placeholder' => 'Event photo']),
        w('heading', 'Title', 0.06, 0.53, 0.88, 0.10, 2, ['text' => 'Jazz Night', 'font_size' => 130]),
        w('text', 'Date', 0.06, 0.64, 0.88, 0.10, 3, ['text' => "Every Friday · 8 PM\nRooftop Terrace", 'align' => 'center', 'font_size' => 48, 'color' => $LTEAL]),
        w('qr_code', 'Tickets', 0.33, 0.78, 0.34, 0.18, 4, ['data' => 'https://example.com/tickets', 'label' => 'Book a table']),
    ])];
$T['community_times_p'] = ['community', 'Community Times', 'Daily times board with clock and inspirational quote.', 'portrait', 0, 'prayer,community,times',
    layout('portrait', bg_grad($DTEAL, $DARK, 180), [
        w('clock', 'Clock', 0.06, 0.03, 0.88, 0.14, 1, ['font_size' => 150]),
        w('daily_schedule', 'Times', 0.06, 0.20, 0.88, 0.52, 2, ['title' => 'Daily Times', 'items' => [
            ['start' => '05:10', 'end' => '05:40', 'title' => 'Morning', 'subtitle' => ''], ['start' => '12:30', 'end' => '13:00', 'title' => 'Midday', 'subtitle' => ''],
            ['start' => '15:45', 'end' => '16:15', 'title' => 'Afternoon', 'subtitle' => ''], ['start' => '18:20', 'end' => '18:50', 'title' => 'Evening', 'subtitle' => ''],
            ['start' => '19:45', 'end' => '20:15', 'title' => 'Night', 'subtitle' => '']]]),
        w('quote', 'Quote', 0.06, 0.76, 0.88, 0.18, 3, ['font_size' => 44, 'quotes' => [['text' => 'Kindness is a language everyone understands.', 'author' => '']]]),
    ])];
$T['salon_services_p'] = ['salon', 'Salon Services', 'Service menu with photo for beauty salons.', 'portrait', 0, 'salon,beauty,price-list',
    layout('portrait', bg_grad('#2A2D31', $DARK, 180), [
        w('image', 'Photo', 0.0, 0.0, 1.0, 0.32, 1, ['placeholder' => 'Salon photo']),
        w('heading', 'Title', 0.06, 0.34, 0.88, 0.07, 2, ['text' => 'Our Services', 'font_family' => 'Playfair Display', 'font_size' => 96]),
        w('price_list', 'Services', 0.06, 0.43, 0.88, 0.46, 3, ['title' => '', 'items' => [
            ['name' => 'Haircut & Style', 'price' => '35', 'note' => '45 min'], ['name' => 'Colour', 'price' => '80', 'note' => 'from'],
            ['name' => 'Manicure', 'price' => '25', 'note' => ''], ['name' => 'Facial', 'price' => '60', 'note' => '60 min']]]),
        w('contact_info', 'Book', 0.06, 0.90, 0.88, 0.08, 4, ['title' => '', 'email' => '', 'address' => '', 'website' => '', 'phone' => 'Book: +1 555 0144']),
    ])];
$T['gym_classes_l'] = ['fitness', 'Gym Class Schedule', 'Class timetable with motivation and clock.', 'landscape', 0, 'gym,fitness,classes',
    layout('landscape', bg_color($DARK), [
        w('heading', 'Title', 0.04, 0.05, 0.55, 0.12, 1, ['text' => 'TODAY\'S CLASSES', 'align' => 'left', 'font_size' => 80, 'letter_spacing' => 4]),
        w('clock', 'Clock', 0.68, 0.04, 0.28, 0.15, 2, ['font_size' => 72, 'align' => 'right', 'color' => $TEAL]),
        w('daily_schedule', 'Classes', 0.04, 0.22, 0.56, 0.72, 3, ['title' => '', 'items' => [
            ['start' => '07:00', 'end' => '08:00', 'title' => 'HIIT', 'subtitle' => 'Studio A · Coach Mia'], ['start' => '12:00', 'end' => '12:45', 'title' => 'Spin', 'subtitle' => 'Studio B · Coach Leo'],
            ['start' => '18:00', 'end' => '19:00', 'title' => 'Yoga Flow', 'subtitle' => 'Studio A · Coach Ana'], ['start' => '19:15', 'end' => '20:00', 'title' => 'Boxing', 'subtitle' => 'Ring · Coach Sam']]]),
        w('shape', 'Panel', 0.63, 0.22, 0.33, 0.72, 4, ['fill_color' => $TEAL, 'border_radius' => 32]),
        w('daily_message', 'Motivation', 0.65, 0.30, 0.29, 0.56, 5, ['font_size' => 56, 'font_weight' => 700]),
    ])];
$T['real_estate_listing_l'] = ['real_estate', 'Property Listing', 'Photo carousel with price, features and agent contact.', 'landscape', 1, 'property,listing,carousel',
    layout('landscape', bg_color($DARK), [
        w('image_carousel', 'Photos', 0.0, 0.0, 0.60, 1.0, 1),
        w('heading', 'Price', 0.64, 0.08, 0.32, 0.12, 2, ['text' => '$ 685,000', 'align' => 'left', 'color' => $TEAL, 'font_size' => 88]),
        w('heading', 'Title', 0.64, 0.21, 0.32, 0.10, 3, ['text' => 'Modern 3-Bed Villa', 'align' => 'left', 'font_size' => 52]),
        w('text', 'Features', 0.64, 0.33, 0.32, 0.26, 4, ['text' => "• 3 bedrooms · 2 baths\n• 210 m² · private garden\n• 5 min to city centre", 'font_size' => 34]),
        w('contact_info', 'Agent', 0.64, 0.62, 0.20, 0.32, 5, ['title' => 'Your agent', 'website' => '', 'address' => '']),
        w('qr_code', 'Tour QR', 0.85, 0.66, 0.12, 0.24, 6, ['data' => 'https://example.com/tour', 'label' => '3D tour', 'padding' => 8]),
    ])];
$T['minimal_clock_l'] = ['minimal', 'Minimal Clock', 'Large typographic clock with date.', 'landscape', 0, 'clock,minimal,date',
    layout('landscape', bg_color($DARK), [
        w('clock', 'Clock', 0.10, 0.25, 0.80, 0.50, 1, ['font_size' => 300, 'font_weight' => 300, 'date_format' => 'EEEE · d MMMM']),
    ])];
$T['minimal_analog_s'] = ['minimal', 'Analog Clock Square', 'Analog clock for square displays.', 'square', 0, 'analog,clock,square',
    layout('square', bg_grad($CHAR, $DARK), [
        w('clock', 'Analog clock', 0.15, 0.10, 0.70, 0.70, 1, ['style' => 'analog', 'show_date' => false]),
        w('text', 'Date', 0.10, 0.82, 0.80, 0.10, 2, ['text' => 'Good day', 'align' => 'center', 'color' => $GREY]),
    ])];
$T['minimal_fullscreen_image_l'] = ['minimal', 'Full-screen Image', 'One edge-to-edge image.', 'landscape', 0, 'image,fullscreen,poster',
    layout('landscape', bg_color('#000000'), [
        w('image', 'Image', 0.0, 0.0, 1.0, 1.0, 1, ['placeholder' => 'Full-screen image']),
    ])];
$T['minimal_quote_p'] = ['minimal', 'Typographic Quote', 'Elegant quote on soft gradient.', 'portrait', 0, 'quote,typography',
    layout('portrait', bg_grad($LTEAL, '#FFFFFF', 180), [
        w('quote', 'Quote', 0.08, 0.30, 0.84, 0.40, 1, ['color' => $CHAR, 'font_size' => 80]),
    ])];
$T['news_dashboard_l'] = ['news', 'News Dashboard', 'Headlines, weather, clock and ticker.', 'landscape', 1, 'news,rss,weather',
    layout('landscape', bg_color($DARK), [
        w('rss_feed', 'Headlines', 0.03, 0.05, 0.62, 0.82, 1, ['url' => 'https://feeds.bbci.co.uk/news/rss.xml']),
        w('clock', 'Clock', 0.68, 0.05, 0.29, 0.20, 2, ['font_size' => 90]),
        w('weather', 'Weather', 0.68, 0.30, 0.29, 0.57, 3, ['background_color' => $CHAR]),
        w('ticker', 'Ticker', 0.0, 0.91, 1.0, 0.09, 4, ['messages' => ['Stay informed', 'Updated every 15 minutes']]),
    ])];
$T['weather_clock_p'] = ['news', 'Weather & Clock Portrait', 'Clock, date and forecast stacked.', 'portrait', 0, 'weather,clock,portrait',
    layout('portrait', bg_grad($TEAL, $DARK, 180), [
        w('clock', 'Clock', 0.06, 0.08, 0.88, 0.25, 1, ['font_size' => 200]),
        w('weather', 'Weather', 0.06, 0.40, 0.88, 0.40, 2, ['forecast_days' => 5]),
        w('world_clock', 'World clock', 0.06, 0.84, 0.88, 0.12, 3),
    ])];

// ---------------------------------------------------------------------------
// Professional food menu templates (modelled on real menu-board walls).
// Images are shared (global) media generated by tools/artwork.php; admins can
// replace them with real photos in the web template editor.
// ---------------------------------------------------------------------------
const M_CONCRETE = 6, M_SALAD = 7, M_PASTA = 8, M_FRUIT = 9, M_STEAK = 10, M_BURGER = 11, M_COFFEE = 12,
      M_NOODLES = 13, M_TOMATO = 14, M_BASIL = 15, M_KIWI = 16, M_SLATE = 17, M_BURST = 18, M_WOOD = 19;
$bgImg = fn(int $m, array $cfg = []) => ['type' => 'image', 'value' => (string)$m, 'media_id' => $m, 'config' => array_merge(['fit' => 'cover', 'position' => 'center', 'opacity' => 1.0, 'blur' => 0, 'overlay_color' => '#000000', 'overlay_opacity' => 0.0], $cfg)];
$dish = fn(string $name, int $m, float $x, float $y, float $wd, float $ht, int $z, float $rot = 0) => w('image', $name, $x, $y, $wd, $ht, $z,
    ['media_id' => $m, 'fit' => 'contain', 'shadow_color' => '#00000040', 'shadow_blur' => 40, 'shadow_offset_y' => 24, 'animation' => 'zoom_in'], ['rotation' => $rot]);
$deco = fn(string $name, int $m, float $x, float $y, float $wd, float $ht, int $z, float $rot = 0) => w('image', $name, $x, $y, $wd, $ht, $z, ['media_id' => $m, 'fit' => 'contain', 'shadow_color' => '#00000030', 'shadow_blur' => 16, 'shadow_offset_y' => 8], ['rotation' => $rot]);
// Round price badge = circle shape + price text on top (16:9 canvas → width .1406 = height .25 for a circle)
$badge = fn(string $price, string $color, float $x, float $y, int $z, float $d = 0.14) => [
    w('shape', "Price badge", $x, $y, $d, $d * 16 / 9, $z, ['shape' => 'circle', 'fill_color' => $color, 'shadow' => true, 'shadow_color' => '#00000055', 'shadow_blur' => 28, 'shadow_offset_y' => 14]),
    w('heading', "Price", $x, $y, $d, $d * 16 / 9, $z + 1, ['text' => $price, 'font_family' => 'Poppins', 'font_size' => 96, 'font_weight' => 800, 'color' => '#FFFFFF', 'animation' => 'zoom_in'], ['rotation' => -8]),
];
$hand = ['font_family' => 'Amatic SC', 'font_weight' => 700, 'color' => '#222222', 'align' => 'left', 'animation' => 'fade_in'];
$small = ['font_family' => 'Poppins', 'font_size' => 30, 'color' => '#444444', 'line_height' => 1.35, 'align' => 'left'];

$T['menu_fresh_feature_l'] = ['restaurant', 'Fresh Menu · Feature Dish', 'Hero dish with handwritten title, description and round price badge.', 'landscape', 0, 'menu,food,salad,price,badge,restaurant',
    layout('landscape', $bgImg(M_CONCRETE), array_merge([
        w('heading', 'Brand', 0.06, 0.07, 0.16, 0.13, 1, $hand + ['text' => "ORGANIC\nFOOD", 'font_size' => 54, 'align' => 'center', 'line_height' => 0.9]),
        w('heading', 'Dish name', 0.06, 0.25, 0.36, 0.3, 2, $hand + ['text' => "SUMMER\nBREEZE", 'font_size' => 170, 'line_height' => 0.92]),
        w('text', 'Description', 0.06, 0.57, 0.27, 0.13, 3, $small + ['text' => "Lettuce, Parmesan cheese,\nCrisp croutons, Caesar salad"]),
        w('text', 'Weight', 0.06, 0.72, 0.1, 0.06, 4, $small + ['text' => '300g', 'font_weight' => 700]),
        $deco('Tomato', M_TOMATO, 0.34, 0.03, 0.07, 0.125, 5),
        $deco('Basil', M_BASIL, 0.86, 0.68, 0.1, 0.18, 5, 20),
        $dish('Dish photo', M_SALAD, 0.43, 0.04, 0.5, 0.89, 6),
    ], $badge('9.25', '#F07B1F', 0.36, 0.6, 8))),
];
$T['menu_fresh_pricelist_l'] = ['menu_boards', 'Fresh Menu · Price List', 'Three menu sections with green headings, red prices and food accents.', 'landscape', 0, 'menu,price list,drinks,salads,restaurant,board',
    layout('landscape', $bgImg(M_CONCRETE), [
        $dish('Pasta', M_PASTA, -0.04, -0.12, 0.2, 0.36, 1),
        $deco('Kiwi', M_KIWI, 0.47, 0.02, 0.06, 0.11, 2, 10),
        $deco('Basil', M_BASIL, 0.42, 0.78, 0.08, 0.14, 2, -30),
        $dish('Fruit bowl', M_FRUIT, 0.84, 0.66, 0.18, 0.32, 3),
        w('menu_board', 'Second courses', 0.05, 0.3, 0.3, 0.66, 4, ['title' => '', 'currency' => '', 'font_family' => 'Poppins', 'section_color' => '#3BA935', 'item_color' => '#222222', 'description_color' => '#666666', 'price_color' => '#E2462B',
            'sections' => [['name' => 'Second courses', 'items' => [
                ['name' => 'Aila Puttanesca', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Cabbage Steak', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Marinated Summer Squash', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []]]]]]),
        w('menu_board', 'Salads', 0.38, 0.12, 0.27, 0.84, 5, ['title' => '', 'currency' => '', 'font_family' => 'Poppins', 'section_color' => '#3BA935', 'item_color' => '#222222', 'description_color' => '#666666', 'price_color' => '#E2462B',
            'sections' => [['name' => 'Salads', 'items' => [
                ['name' => 'Caesar', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Summer Breeze', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Tuna Vegetable', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Leopold', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []],
                ['name' => 'Portobello', 'description' => 'Lettuce, Parmesan cheese, croutons', 'price' => '12.55', 'tags' => []]]]]]),
        w('menu_board', 'Drinks', 0.68, 0.12, 0.27, 0.54, 6, ['title' => '', 'currency' => '', 'font_family' => 'Poppins', 'section_color' => '#3BA935', 'item_color' => '#222222', 'description_color' => '#666666', 'price_color' => '#E2462B',
            'sections' => [['name' => 'Drinks', 'items' => [
                ['name' => 'Ice tea', 'description' => '', 'price' => '12.55', 'tags' => []], ['name' => 'Espresso', 'description' => '', 'price' => '12.55', 'tags' => []],
                ['name' => 'Fruit juices assorted', 'description' => '', 'price' => '12.55', 'tags' => []], ['name' => 'Mojito', 'description' => '', 'price' => '12.55', 'tags' => []],
                ['name' => 'Cola Zero', 'description' => '', 'price' => '12.55', 'tags' => []]]]]]),
    ]),
];
$T['menu_fresh_duo_l'] = ['restaurant', 'Fresh Menu · Two Dishes', 'Two featured dishes with names, details and colour price badges.', 'landscape', 0, 'menu,pasta,salad,badge,restaurant',
    layout('landscape', $bgImg(M_CONCRETE), array_merge([
        w('heading', 'Dish 1 name', 0.05, 0.08, 0.3, 0.2, 1, $hand + ['text' => "SALAD\nSLICED MIX", 'font_size' => 96, 'align' => 'center', 'line_height' => 0.92]),
        w('text', 'Dish 1 details', 0.05, 0.29, 0.3, 0.12, 2, $small + ['text' => "Lettuce, Parmesan cheese,\nCrisp croutons, Caesar salad", 'align' => 'center']),
        w('text', 'Dish 1 weight', 0.05, 0.42, 0.3, 0.06, 3, $small + ['text' => '300g', 'font_weight' => 700, 'align' => 'center']),
        $dish('Dish 1 photo', M_SALAD, 0.24, 0.4, 0.34, 0.6, 4),
        $dish('Dish 2 photo', M_PASTA, 0.6, 0.02, 0.32, 0.57, 5),
        w('heading', 'Dish 2 name', 0.64, 0.6, 0.32, 0.2, 6, $hand + ['text' => "BOLOGNESE\nPASTA", 'font_size' => 96, 'align' => 'center', 'line_height' => 0.92]),
        w('text', 'Dish 2 details', 0.64, 0.8, 0.32, 0.1, 7, $small + ['text' => "Lettuce, Parmesan cheese,\nCrisp croutons, Caesar salad", 'align' => 'center']),
        w('text', 'Dish 2 weight', 0.64, 0.9, 0.32, 0.06, 8, $small + ['text' => '300g', 'font_weight' => 700, 'align' => 'center']),
        $deco('Tomato', M_TOMATO, 0.58, 0.62, 0.05, 0.09, 9), $deco('Basil', M_BASIL, 0.51, 0.18, 0.08, 0.14, 9, 40),
    ], $badge('9.25', '#F07B1F', 0.22, 0.52, 10, 0.11), $badge('9.25', '#2DB842', 0.86, 0.38, 12, 0.11))),
];
$T['menu_combo_deal_p'] = ['restaurant', 'Combo Deal Poster', 'Bold portrait promo with price banner and burger.', 'portrait', 0, 'combo,burger,fast food,promo,portrait,price',
    layout('portrait', $bgImg(M_BURST), [
        w('shape', 'Banner', -0.05, 0.06, 0.75, 0.1, 1, ['fill_color' => '#D62828', 'border_radius' => 0, 'shadow' => true, 'shadow_color' => '#00000055', 'shadow_blur' => 20, 'shadow_offset_y' => 10], ['rotation' => -6]),
        w('heading', 'Banner text', 0.0, 0.065, 0.66, 0.09, 2, ['text' => 'COMBO', 'font_family' => 'Bebas Neue', 'font_size' => 150, 'color' => '#FFFFFF', 'letter_spacing' => 6], ['rotation' => -6]),
        w('heading', 'Price', 0.08, 0.18, 0.84, 0.13, 3, ['text' => '$3.29', 'font_family' => 'Bebas Neue', 'font_size' => 260, 'color' => '#1B1B1B', 'text_effect' => 'shadow', 'effect_color' => '#FFFFFF99', 'effect_blur' => 0, 'effect_offset_y' => 6]),
        $dish('Burger', M_BURGER, 0.03, 0.33, 0.94, 0.53, 4),
        w('shape', 'Footer', 0.0, 0.88, 1.0, 0.12, 5, ['fill_color' => '#1B1B1B', 'border_radius' => 0]),
        w('heading', 'Footer text', 0.05, 0.89, 0.9, 0.1, 6, ['text' => 'BURGER + FRIES + DRINK', 'font_family' => 'Bebas Neue', 'font_size' => 96, 'color' => '#FFC928', 'letter_spacing' => 4]),
    ]),
];
$T['menu_steakhouse_l'] = ['restaurant', 'Steakhouse Dark Menu', 'Dark slate menu with hero steak, gold prices and specials.', 'landscape', 1, 'steak,grill,dark,menu,premium',
    layout('landscape', $bgImg(M_SLATE), array_merge([
        $dish('Steak photo', M_STEAK, 0.0, 0.08, 0.5, 0.89, 1, -6),
        w('heading', 'Title', 0.52, 0.06, 0.44, 0.16, 2, ['text' => 'THE GRILL', 'font_family' => 'Bebas Neue', 'font_size' => 150, 'color' => '#FFFFFF', 'letter_spacing' => 10, 'align' => 'left']),
        w('shape', 'Rule', 0.525, 0.22, 0.1, 0.008, 3, ['fill_color' => '#C9A24B', 'border_radius' => 4]),
        w('menu_board', 'Menu', 0.52, 0.27, 0.44, 0.68, 4, ['title' => '', 'currency' => '$', 'font_family' => 'Oswald', 'section_color' => '#C9A24B', 'item_color' => '#FFFFFF', 'description_color' => '#B0B4BA', 'price_color' => '#C9A24B',
            'sections' => [['name' => 'From the grill', 'items' => [
                ['name' => 'Ribeye 300g', 'description' => 'Fries, green salad, pepper sauce', 'price' => '24.90', 'tags' => []],
                ['name' => 'Sirloin 250g', 'description' => 'Grilled vegetables, garlic butter', 'price' => '21.50', 'tags' => []],
                ['name' => 'Smash Burger', 'description' => 'Cheddar, pickles, house sauce', 'price' => '14.00', 'tags' => []],
                ['name' => 'Half Chicken', 'description' => 'Lemon, herbs, roasted potatoes', 'price' => '16.50', 'tags' => []]]]]]),
    ], $badge('NEW', '#C9A24B', 0.4, 0.1, 6, 0.1))),
];
$T['menu_cafe_wood_l'] = ['restaurant', 'Café Coffee Board', 'Warm wood background, coffee cup and drinks price list.', 'landscape', 0, 'cafe,coffee,drinks,wood,menu',
    layout('landscape', $bgImg(M_WOOD), [
        w('shape', 'Card', 0.05, 0.08, 0.5, 0.84, 1, ['fill_color' => '#FFF8EEF0', 'border_radius' => 36, 'shadow' => true, 'shadow_color' => '#00000066', 'shadow_blur' => 40, 'shadow_offset_y' => 18]),
        w('heading', 'Title', 0.08, 0.12, 0.44, 0.14, 2, ['text' => 'Coffee & Co.', 'font_family' => 'Pacifico', 'font_size' => 110, 'color' => '#5D3A25', 'align' => 'left']),
        w('price_list', 'Drinks', 0.08, 0.28, 0.44, 0.6, 3, ['title' => '', 'currency' => '$', 'font_family' => 'Poppins', 'item_color' => '#3E2A1E', 'price_color' => '#B5651D', 'background_color' => null,
            'items' => [['name' => 'Espresso', 'price' => '2.80', 'note' => 'single / double'], ['name' => 'Flat White', 'price' => '3.90', 'note' => ''], ['name' => 'Cappuccino', 'price' => '3.80', 'note' => ''], ['name' => 'Iced Latte', 'price' => '4.20', 'note' => 'oat milk available']]]),
        $dish('Coffee', M_COFFEE, 0.58, 0.12, 0.4, 0.71, 4),
        w('heading', 'Tagline', 0.58, 0.82, 0.4, 0.1, 5, ['text' => 'Freshly roasted every morning', 'font_family' => 'Caveat', 'font_size' => 64, 'color' => '#FFF3E0']),
    ]),
];
$T['menu_asian_kitchen_l'] = ['restaurant', 'Asian Kitchen Board', 'Clean white board with noodle bowl and two-column prices.', 'landscape', 0, 'asian,noodles,ramen,menu,white',
    layout('landscape', bg_color('#FFFFFF'), [
        w('shape', 'Accent', 0.0, 0.0, 0.42, 1.0, 1, ['fill_color' => '#B71C1C', 'border_radius' => 0]),
        $dish('Noodles', M_NOODLES, 0.1, 0.12, 0.42, 0.75, 2),
        w('heading', 'Title', 0.56, 0.08, 0.4, 0.14, 3, ['text' => 'NOODLE BAR', 'font_family' => 'Oswald', 'font_size' => 110, 'font_weight' => 700, 'color' => '#B71C1C', 'letter_spacing' => 6, 'align' => 'left']),
        w('menu_board', 'Menu', 0.56, 0.25, 0.4, 0.7, 4, ['title' => '', 'currency' => '¥', 'font_family' => 'Poppins', 'section_color' => '#B71C1C', 'item_color' => '#222222', 'description_color' => '#777777', 'price_color' => '#222222',
            'sections' => [['name' => 'Bowls', 'items' => [
                ['name' => 'Shrimp Ramen', 'description' => 'Egg, scallions, sesame', 'price' => '58', 'tags' => []],
                ['name' => 'Spicy Beef Noodles', 'description' => 'Chili oil, bok choy', 'price' => '62', 'tags' => ['HOT']],
                ['name' => 'Veggie Udon', 'description' => 'Mushrooms, tofu', 'price' => '48', 'tags' => ['V']]]]]]),
    ]),
];

// ---------------------------------------------------------------------------
// 3. Sample media (GD-generated so the seed works offline)
// ---------------------------------------------------------------------------
$SAMPLES = [
    // file, original name, w, h, colorA, colorB, label, global?
    ['promo-banner.jpg', 'Summer promo banner.jpg', 1920, 1080, [0, 140, 134], [23, 25, 28], 'SUMMER SALE', false],
    ['product-headphones.jpg', 'Headphones product shot.jpg', 1080, 1080, [32, 33, 36], [0, 107, 102], 'PRODUCT', false],
    ['office-lobby.jpg', 'Office lobby.jpg', 1920, 1080, [133, 139, 147], [32, 33, 36], 'OUR OFFICE', false],
    ['bg-teal-waves.jpg', 'Teal waves background.jpg', 1920, 1080, [0, 107, 102], [229, 245, 243], '', true],
    ['bg-charcoal.jpg', 'Charcoal background.jpg', 1920, 1080, [23, 25, 28], [32, 33, 36], '', true],
];
$mediaRows = [];
@mkdir("$UPLOADS/images/samples", 0775, true);
@mkdir("$UPLOADS/thumbnails/samples", 0775, true);
foreach ($SAMPLES as $i => [$file, $orig, $iw, $ih, $ca, $cb, $label, $global]) {
    $path = "images/samples/{$file}";
    $tpath = "thumbnails/samples/{$file}";
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor($iw, $ih);
        for ($y = 0; $y < $ih; $y++) {
            $t = $y / max(1, $ih - 1);
            $col = imagecolorallocate($im, (int)($ca[0] + ($cb[0] - $ca[0]) * $t), (int)($ca[1] + ($cb[1] - $ca[1]) * $t), (int)($ca[2] + ($cb[2] - $ca[2]) * $t));
            imageline($im, 0, $y, $iw, $y, $col);
        }
        $white = imagecolorallocatealpha($im, 255, 255, 255, 90);
        for ($k = 0; $k < 6; $k++) imagefilledellipse($im, (int)($iw * (0.15 + 0.15 * $k)), (int)($ih * 0.5), (int)($ih * 0.6), (int)($ih * 0.6), $white);
        if ($label !== '') {
            $fg = imagecolorallocate($im, 255, 255, 255);
            $scale = 6; $fw = imagefontwidth(5) * strlen($label); $fh = imagefontheight(5);
            $tmp = imagecreatetruecolor($fw, $fh); imagesavealpha($tmp, true);
            $tr = imagecolorallocatealpha($tmp, 0, 0, 0, 127); imagefill($tmp, 0, 0, $tr);
            imagestring($tmp, 5, 0, 0, $label, $fg);
            imagecopyresized($im, $tmp, (int)(($iw - $fw * $scale) / 2), (int)(($ih - $fh * $scale) / 2), 0, 0, $fw * $scale, $fh * $scale, $fw, $fh);
        }
        imagejpeg($im, "$UPLOADS/$path", 82);
        $tw = 320; $th = (int)round($ih * $tw / $iw);
        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $tw, $th, $iw, $ih);
        imagejpeg($thumb, "$UPLOADS/$tpath", 75);
    }
    $exists = is_file("$UPLOADS/$path");
    $mediaRows[] = [
        'media_id' => $i + 1, 'user_id' => $global ? null : 3, 'folder_id' => $global ? null : 1,
        'file_name' => $file, 'original_name' => $orig, 'file_path' => $path, 'thumbnail_path' => $exists ? $tpath : null,
        'file_type' => 'image', 'mime_type' => 'image/jpeg', 'file_size' => $exists ? filesize("$UPLOADS/$path") : 0,
        'width' => $iw, 'height' => $ih, 'duration_ms' => null,
        'checksum_sha256' => $exists ? hash_file('sha256', "$UPLOADS/$path") : null,
        'storage_driver' => 'local', 'is_global' => $global ? 1 : 0,
    ];
}

// Artwork for the professional menu templates (shared/global media 6..19).
require __DIR__ . '/artwork.php';
$ART = [
    M_CONCRETE => ['bg-concrete-light.jpg', 'Light concrete background.jpg', fn($p) => art_texture(1920, 1080, '#e9e7e3', 14, 5, $p), 1920, 1080, 'image/jpeg'],
    M_SALAD => ['dish-salad.png', 'Salad bowl (cut-out).png', 'art_salad', 800, 800, 'image/png'],
    M_PASTA => ['dish-pasta.png', 'Pasta bowl (cut-out).png', 'art_pasta', 800, 800, 'image/png'],
    M_FRUIT => ['dish-fruit.png', 'Fruit bowl (cut-out).png', 'art_fruit', 800, 800, 'image/png'],
    M_STEAK => ['dish-steak.png', 'Steak plate (cut-out).png', 'art_steak', 800, 800, 'image/png'],
    M_BURGER => ['dish-burger.png', 'Burger (cut-out).png', 'art_burger', 800, 800, 'image/png'],
    M_COFFEE => ['dish-coffee.png', 'Coffee cup (cut-out).png', 'art_coffee', 800, 800, 'image/png'],
    M_NOODLES => ['dish-noodles.png', 'Noodle bowl (cut-out).png', 'art_noodles', 800, 800, 'image/png'],
    M_TOMATO => ['deco-tomato.png', 'Tomato decoration.png', 'art_deco_tomato', 300, 300, 'image/png'],
    M_BASIL => ['deco-basil.png', 'Basil leaves decoration.png', 'art_deco_basil', 400, 400, 'image/png'],
    M_KIWI => ['deco-kiwi.png', 'Kiwi slice decoration.png', 'art_deco_kiwi', 300, 300, 'image/png'],
    M_SLATE => ['bg-slate-dark.jpg', 'Dark slate background.jpg', fn($p) => art_texture(1920, 1080, '#1f2124', 10, 9, $p, false), 1920, 1080, 'image/jpeg'],
    M_BURST => ['bg-yellow-burst.jpg', 'Yellow burst background.jpg', fn($p) => art_burst(1080, 1920, $p), 1080, 1920, 'image/jpeg'],
    M_WOOD => ['bg-wood-warm.jpg', 'Warm wood background.jpg', fn($p) => art_wood(1920, 1080, $p), 1920, 1080, 'image/jpeg'],
];
@mkdir("$UPLOADS/images/templates", 0775, true);
@mkdir("$UPLOADS/thumbnails/templates", 0775, true);
foreach ($ART as $id => [$file, $orig, $fn, $iw, $ih, $mime]) {
    $path = "images/templates/{$file}";
    $tpath = "thumbnails/templates/" . pathinfo($file, PATHINFO_FILENAME) . '.png';
    $fn("$UPLOADS/$path");
    $src = $mime === 'image/png' ? imagecreatefrompng("$UPLOADS/$path") : imagecreatefromjpeg("$UPLOADS/$path");
    $tw = 320; $th = (int)round($ih * $tw / $iw);
    $thumb = art_canvas($tw, $th); imagealphablending($thumb, false);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $th, $iw, $ih);
    imagepng($thumb, "$UPLOADS/$tpath", 6);
    $mediaRows[] = [
        'media_id' => $id, 'user_id' => null, 'folder_id' => null, 'file_name' => $file, 'original_name' => $orig, 'file_path' => $path,
        'thumbnail_path' => $tpath, 'file_type' => 'image', 'mime_type' => $mime, 'file_size' => filesize("$UPLOADS/$path"),
        'width' => $iw, 'height' => $ih, 'duration_ms' => null, 'checksum_sha256' => hash_file('sha256', "$UPLOADS/$path"),
        'storage_driver' => 'local', 'is_global' => 1,
    ];
}
$mediaById = [];
foreach ($mediaRows as $m) $mediaById[$m['media_id']] = $m;

// ---------------------------------------------------------------------------
// 4. Build SQL
// ---------------------------------------------------------------------------
$sql = <<<SQL
-- =============================================================================
--  Digital Signage Platform — seed data  (GENERATED by tools/generate_seed.php)
--  Load AFTER schema.sql.
--
--  !! LOCAL DEVELOPMENT CREDENTIALS ONLY — change / delete before production !!
--     Super admin : admin@signage.local   / Admin@12345
--     Demo users  : *@demo.local          / Demo@12345
--     Demo player : device_uid 'demo-lobby-tv-0001', bearer token 'dev-device-token-lobby-tv'
--     Unpaired TV : pairing code  K7Q4ZP   (expires 2099 — dev only)
--     Display codes: STORE1 (playlist), EVENING (playlist), WELCOME, PROMO1 (screens)
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 1;   -- rows are inserted in dependency order; FKs are verified on load
START TRANSACTION;


SQL;

// roles + permissions -------------------------------------------------------
$sql .= insert('roles', [
    ['role_id' => 1, 'role_key' => 'super_admin', 'role_name' => 'Super Admin', 'description' => 'Full platform control'],
    ['role_id' => 2, 'role_key' => 'user', 'role_name' => 'User', 'description' => 'Business user managing own screens'],
]);
$PERMS = [
    // key, name, module, user-role default
    ['admin.access', 'Access admin panel', 'admin', 0], ['admin.users.manage', 'Manage users', 'admin', 0],
    ['admin.plans.manage', 'Manage plans', 'admin', 0], ['admin.templates.manage', 'Manage templates', 'admin', 0],
    ['admin.widgets.manage', 'Manage widget types', 'admin', 0], ['admin.media.manage', 'Manage all media', 'admin', 0],
    ['admin.devices.view', 'View all devices', 'admin', 0], ['admin.activity.view', 'View system activity', 'admin', 0],
    ['admin.settings.manage', 'Manage application settings', 'admin', 0],
    ['screens.view', 'View screens', 'screens', 1], ['screens.create', 'Create screens', 'screens', 1],
    ['screens.edit', 'Edit screens', 'screens', 1], ['screens.delete', 'Delete screens', 'screens', 1],
    ['screens.publish', 'Publish screens', 'screens', 1], ['screens.duplicate', 'Duplicate screens', 'screens', 1],
    ['templates.use', 'Use templates', 'templates', 1], ['templates.premium', 'Use premium templates', 'templates', 0],
    ['templates.save_own', 'Save screen as template', 'templates', 1],
    ['media.view', 'View media', 'media', 1], ['media.upload', 'Upload images & documents', 'media', 1],
    ['media.upload_video', 'Upload videos', 'media', 1], ['media.delete', 'Delete media', 'media', 1],
    ['playlists.view', 'View playlists', 'playlists', 1], ['playlists.manage', 'Create & edit playlists', 'playlists', 1],
    ['playlists.publish', 'Publish playlists', 'playlists', 1], ['schedules.manage', 'Manage schedules', 'schedules', 1],
    ['devices.view', 'View devices', 'devices', 1], ['devices.pair', 'Pair devices', 'devices', 1],
    ['devices.manage', 'Rename / assign / unpair devices', 'devices', 1], ['devices.commands', 'Send remote commands', 'devices', 1],
    ['emergency.broadcast', 'Broadcast emergency messages', 'devices', 1], ['analytics.view', 'View playback analytics', 'analytics', 1],
];
$permRows = []; $rp = [];
foreach ($PERMS as $i => [$k, $n, $mod, $userDefault]) {
    $pid = $i + 1;
    $permRows[] = ['permission_id' => $pid, 'permission_key' => $k, 'permission_name' => $n, 'module' => $mod, 'description' => null];
    $rp[] = ['role_id' => 1, 'permission_id' => $pid];
    if ($userDefault) $rp[] = ['role_id' => 2, 'permission_id' => $pid];
}
$permId = array_flip(array_column($permRows, 'permission_key'));
$sql .= insert('permissions', $permRows);
$sql .= insert('role_permissions', $rp);

// plans ---------------------------------------------------------------------
$ALL = ['*'];
$BASIC_WIDGETS = ['image', 'image_carousel', 'heading', 'text', 'ticker', 'clock', 'qr_code', 'shape', 'logo', 'background_image', 'contact_info', 'opening_hours', 'announcement'];
$plans = [
    ['free', 'Free', 'Try the platform with one display', 0, 0, 1, 1, 1, 100, 10, ['video_upload' => false, 'premium_templates' => false, 'allowed_widgets' => $BASIC_WIDGETS, 'remote_commands' => false, 'analytics' => false, 'emergency' => false], 1, 1],
    ['starter', 'Starter', 'Small shops and cafes', 9, 90, 3, 2, 2, 1024, 50, ['video_upload' => true, 'premium_templates' => false, 'allowed_widgets' => array_merge($BASIC_WIDGETS, ['video', 'menu_board', 'price_list', 'countdown', 'weather', 'daily_schedule', 'calendar', 'quote', 'daily_message']), 'remote_commands' => true, 'analytics' => false, 'emergency' => true], 2, 0],
    ['professional', 'Professional', 'Growing businesses with several screens', 29, 290, 10, 10, 5, 5120, 200, ['video_upload' => true, 'premium_templates' => true, 'allowed_widgets' => $ALL, 'remote_commands' => true, 'analytics' => true, 'emergency' => true], 3, 0],
    ['business', 'Business', 'Multi-location operations', 79, 790, 50, 50, 25, 25600, 500, ['video_upload' => true, 'premium_templates' => true, 'allowed_widgets' => $ALL, 'remote_commands' => true, 'analytics' => true, 'emergency' => true, 'locations' => true], 4, 0],
    ['enterprise', 'Enterprise', 'Unlimited scale with priority support', 0, 0, -1, -1, -1, -1, 2048, ['video_upload' => true, 'premium_templates' => true, 'allowed_widgets' => $ALL, 'remote_commands' => true, 'analytics' => true, 'emergency' => true, 'locations' => true, 'custom_branding' => true], 5, 0],
];
$planRows = [];
foreach ($plans as $i => $p) {
    $planRows[] = ['plan_id' => $i + 1, 'plan_key' => $p[0], 'plan_name' => $p[1], 'description' => $p[2], 'price_monthly' => $p[3],
        'price_yearly' => $p[4], 'currency' => 'USD', 'max_screens' => $p[5], 'max_playlists' => $p[6], 'max_devices' => $p[7],
        'max_storage_mb' => $p[8], 'max_upload_mb' => $p[9], 'features_json' => jr($p[10]), 'sort_order' => $p[11], 'is_default' => $p[12], 'status' => 'active'];
}
$sql .= insert('plans', $planRows);

// users ---------------------------------------------------------------------
$adminHash = password_hash('Admin@12345', PASSWORD_DEFAULT);
$demoHash  = password_hash('Demo@12345', PASSWORD_DEFAULT);
$users = [
    // id, role, name, email, phone, business, status, expires, plan, limits override
    [1, 1, 'Platform Admin', 'admin@signage.local', '+1 555 0100', 'Signage HQ', 'active', null, 5, null],
    [2, 2, 'Aisha Rahman (User A)', 'aisha@demo.local', '+1 555 0101', 'Aisha Cafe', 'active', '2027-12-31 23:59:59', 2, ['max_screens' => 2]],
    [3, 2, 'Bilal Hussain (User B)', 'bilal@demo.local', '+1 555 0102', 'Bilal Electronics', 'active', null, 2, ['max_screens' => 5, 'max_playlists' => 3, 'max_devices' => 3]],
    [4, 2, 'Chloe Martin (User C)', 'chloe@demo.local', '+1 555 0103', 'Martin Retail Group', 'active', null, 3, ['max_screens' => 10]],
    [5, 2, 'Daniel Okafor (User D)', 'daniel@demo.local', '+1 555 0104', 'Okafor Hotels', 'active', null, 4, ['max_screens' => -1]],
    [6, 2, 'Eva Novak', 'eva@demo.local', '+1 555 0105', 'Novak Salon', 'suspended', null, 1, null],
];
$userRows = []; $subRows = []; $limitRows = [];
foreach ($users as [$id, $role, $name, $email, $phone, $biz, $status, $exp, $plan, $lim]) {
    $userRows[] = ['user_id' => $id, 'role_id' => $role, 'full_name' => $name, 'email' => $email, 'phone' => $phone,
        'password_hash' => $role === 1 ? $adminHash : $demoHash, 'profile_image' => null, 'business_name' => $biz,
        'timezone' => 'Asia/Dubai', 'status' => $status, 'must_change_password' => 0, 'account_expires_at' => $exp,
        'last_login_at' => null, 'created_by' => $id === 1 ? null : 1];
    $subRows[] = ['user_id' => $id, 'plan_id' => $plan, 'start_date' => '2026-01-01', 'end_date' => null, 'status' => 'active', 'notes' => 'Seed subscription'];
    if ($lim) {
        $limitRows[] = array_merge(['user_id' => $id, 'max_screens' => null, 'max_playlists' => null, 'max_devices' => null,
            'max_storage_mb' => null, 'max_upload_mb' => null, 'allowed_widgets_json' => null, 'can_upload_video' => null,
            'can_use_premium_templates' => null, 'updated_by' => 1], $lim);
    }
}
$sql .= insert('users', $userRows);
$sql .= insert('user_subscriptions', $subRows);
$sql .= insert('user_limits', $limitRows);
$sql .= insert('user_permissions', [
    ['user_id' => 2, 'permission_id' => $permId['media.upload_video'] + 1, 'allowed' => 0],   // Aisha: no video uploads
    ['user_id' => 5, 'permission_id' => $permId['templates.premium'] + 1, 'allowed' => 1],    // Daniel: premium templates
]);
$sql .= insert('user_settings', [
    ['user_id' => 1, 'setting_key' => 'theme', 'setting_value' => 'dark'],
    ['user_id' => 4, 'setting_key' => 'theme', 'setting_value' => 'light'],
    ['user_id' => 4, 'setting_key' => 'brand_primary_color', 'setting_value' => '#008C86'],
]);

// categories, widget types, templates --------------------------------------
$catRows = [];
foreach ($CATEGORIES as $i => $c) $catRows[] = ['category_id' => $i + 1, 'category_key' => $c[0], 'category_name' => $c[1], 'description' => $c[2], 'icon' => $c[3], 'sort_order' => $i + 1, 'status' => 'active'];
$sql .= insert('template_categories', $catRows);

$wtRows = []; $order = 0;
foreach ($WIDGETS as $key => [$name, $cat, $icon, $avail, $net, $prem, $dw, $dh, $desc, $cfg]) {
    $wtRows[] = ['widget_type_id' => ++$order, 'widget_key' => $key, 'widget_name' => $name, 'description' => $desc, 'category' => $cat,
        'icon' => $icon, 'default_config_json' => jr($cfg), 'default_width' => $dw, 'default_height' => $dh,
        'requires_network' => $net, 'is_premium' => $prem, 'availability' => $avail, 'sort_order' => $order, 'status' => 'active'];
}
$sql .= insert('widget_types', $wtRows);

/**
 * "Animate whole screen" (same rules as the app and the web editor): staggered
 * entrances top to bottom, pulsing round badges, floating photos, swaying
 * decorations, slow zoom on full-screen photos; the scene replays every N s.
 */
function animate_layout(array &$lay, string $style, int $replay): void
{
    [$dur, $stagger] = match ($style) { 'energetic' => [700, 180], 'elegant' => [1200, 400], default => [850, 260] };
    $idx = array_keys($lay['widgets']);
    usort($idx, function ($a, $b) use ($lay) {
        $wa = $lay['widgets'][$a]; $wb = $lay['widgets'][$b];
        $dy = $wa['position_y'] - $wb['position_y'];
        return abs($dy) > 0.05 ? ($dy <=> 0) : ($wa['position_x'] <=> $wb['position_x']);
    });
    $i = 0;
    foreach ($idx as $k) {
        $w = &$lay['widgets'][$k];
        $t = $w['widget_type']; $c = &$w['configuration'];
        $full = $w['width'] >= 0.95 && $w['height'] >= 0.95;
        $isText = in_array($t, ['heading', 'text'], true);
        $isImage = in_array($t, ['image', 'logo', 'background_image'], true);
        $isRound = $t === 'shape' && ($c['shape'] ?? '') === 'circle';
        $small = $w['width'] * $w['height'] < 0.03;
        $loop = 'none';
        if ($full) { $entrance = 'fade_in'; if ($isImage) $loop = 'ken_burns'; }
        else {
            $entrance = match ($style) {
                'energetic' => $t === 'heading' ? 'bounce_in' : ($isText ? 'slide_left' : (($isImage || $t === 'shape') ? 'pop' : 'zoom_in')),
                'elegant' => $t === 'heading' ? 'wipe_right' : ($isImage ? 'blur_in' : 'fade_in'),
                default => $isText ? 'slide_up' : ($isImage ? 'zoom_in' : ($t === 'shape' ? 'pop' : 'fade_in')),
            };
            if ($isRound) $loop = $style === 'energetic' ? 'heartbeat' : ($style === 'elegant' ? 'breathe' : 'pulse');
            elseif ($isImage && $small) $loop = 'sway';
            elseif ($isImage) $loop = $style === 'energetic' ? 'bounce' : 'float';
        }
        $c = array_replace($c, ['animation' => $entrance, 'animation_duration_ms' => $full ? 1000 : $dur, 'animation_delay_ms' => $full ? 0 : 150 + $stagger * $i++,
            'animation_replay_seconds' => $replay, 'loop_animation' => $loop, 'loop_duration_ms' => 0, 'loop_intensity' => 1.0]);
        unset($w, $c);
    }
}
// The photo menu templates play like short looping videos out of the box.
foreach (['menu_fresh_feature_l' => 'smooth', 'menu_fresh_pricelist_l' => 'smooth', 'menu_fresh_duo_l' => 'smooth', 'menu_combo_deal_p' => 'energetic',
          'menu_steakhouse_l' => 'elegant', 'menu_cafe_wood_l' => 'elegant', 'menu_asian_kitchen_l' => 'smooth'] as $key => $style) {
    animate_layout($T[$key][6], $style, 15);
}

$tplRows = []; $tplId = []; $n = 0;
foreach ($T as $key => [$cat, $name, $desc, $orient, $prem, $tags, $lay]) {
    $tplId[$key] = ++$n;
    $tplRows[] = ['template_id' => $n, 'category_id' => $catId[$cat], 'template_key' => $key, 'template_name' => $name, 'description' => $desc,
        'orientation' => $orient, 'width' => $lay['canvas']['width'], 'height' => $lay['canvas']['height'], 'preview_image' => null,
        'template_json' => jr($lay), 'tags' => $tags, 'is_premium' => $prem, 'usage_count' => 0, 'created_by' => null, 'is_public' => 1,
        'sort_order' => str_starts_with($key, 'menu_') ? $n - 100 : $n, 'status' => 'active'];   // new menu designs first
}
$sql .= insert('screen_templates', $tplRows);
// Demo of per-user template access: Aisha (a cafe) only sees Restaurant & Cafe + Digital Menu Boards templates.
$sql .= insert('user_template_access', [
    ['user_id' => 2, 'category_id' => $catId['restaurant'], 'template_id' => null],
    ['user_id' => 2, 'category_id' => $catId['menu_boards'], 'template_id' => null],
]);
$sql .= insert('template_favorites', [
    ['user_id' => 3, 'template_id' => $tplId['retail_product_p']],
    ['user_id' => 3, 'template_id' => $tplId['corp_welcome_l']],
]);
$sql .= insert('user_recent_widgets', [
    ['user_id' => 3, 'widget_key' => 'heading', 'use_count' => 12], ['user_id' => 3, 'widget_key' => 'image', 'use_count' => 8],
    ['user_id' => 3, 'widget_key' => 'ticker', 'use_count' => 4],
]);

// media ---------------------------------------------------------------------
$sql .= insert('media_folders', [
    ['folder_id' => 1, 'user_id' => 3, 'folder_name' => 'Promotions', 'parent_folder_id' => null],
    ['folder_id' => 2, 'user_id' => 3, 'folder_name' => 'Summer 2026', 'parent_folder_id' => 1],
    ['folder_id' => 3, 'user_id' => 4, 'folder_name' => 'Brand assets', 'parent_folder_id' => null],
]);
$sql .= insert('media_library', $mediaRows);

// screens for Bilal (user 3): the 5-screen carousel example from the brief ----
$sql .= insert('screen_folders', [
    ['folder_id' => 1, 'user_id' => 3, 'folder_name' => 'Store front', 'color' => '#008C86', 'sort_order' => 1],
    ['folder_id' => 2, 'user_id' => 3, 'folder_name' => 'Seasonal', 'color' => '#858B93', 'sort_order' => 2],
]);

$screens = [
    // id, name, template, folder, status, duration, layout-modifier
    [1, 'Welcome Screen',             'corp_welcome_l',       1, 'published', 10, function ($l) {
        $l['widgets'][2]['configuration']['text'] = 'Welcome to Bilal Electronics';
        $l['widgets'][4]['configuration']['messages'] = ['Open daily 10:00 – 22:00', 'Free parking at the rear', 'Ask about extended warranty'];
        return $l; }],
    [2, 'Promotional Screen',         'retail_image_ticker_l', 2, 'published', 12, function ($l) {
        $l['widgets'][0]['configuration']['media_id'] = 1;
        $l['widgets'][1]['configuration']['messages'] = ['Summer sale: up to 50% off', 'Members get an extra 10%'];
        return $l; }],
    [3, 'Product Screen',             'retail_product_p',     1, 'published', 15, function ($l) {
        $l['widgets'][1]['configuration']['items'][0]['media_id'] = 2;
        return $l; }],
    [4, 'Company Information Screen', 'corp_info_split_l',    1, 'published', 10, function ($l) {
        $l['widgets'][0]['configuration']['media_id'] = 3;
        $l['widgets'][2]['configuration']['text'] = 'Your trusted electronics store since 2009 — certified repairs, genuine parts and friendly experts.';
        $l['widgets'][3]['configuration'] = array_replace($l['widgets'][3]['configuration'], ['phone' => '+971 4 555 0102', 'email' => 'hello@bilal-electronics.example', 'website' => 'bilal-electronics.example', 'address' => 'Al Wasl Road, Dubai']);
        return $l; }],
    [5, 'Contact Screen',             null,                   null, 'draft', 8, function ($l) {
        return layout('landscape', ['type' => 'image', 'value' => '4', 'media_id' => 4, 'config' => ['fit' => 'cover', 'position' => 'center', 'opacity' => 1.0, 'blur' => 4, 'overlay_color' => '#000000', 'overlay_opacity' => 0.35]], [
            w('heading', 'Title', 0.10, 0.15, 0.80, 0.15, 1, ['text' => 'Get in touch']),
            w('contact_info', 'Contact', 0.10, 0.36, 0.50, 0.45, 2, ['phone' => '+971 4 555 0102', 'email' => 'hello@bilal-electronics.example', 'website' => 'bilal-electronics.example', 'address' => 'Al Wasl Road, Dubai']),
            w('opening_hours', 'Hours', 0.64, 0.36, 0.26, 0.50, 3),
            w('qr_code', 'Map QR', 0.45, 0.70, 0.10, 0.18, 4, ['data' => 'https://maps.example.com/bilal', 'label' => 'Directions', 'padding' => 8]),
        ]); }],
];
$screenRows = []; $widgetRows = []; $assetRows = []; $pubRows = []; $screenPub = [];
$wid = 0;
foreach ($screens as [$sid, $name, $tkey, $folder, $status, $dur, $mod]) {
    $base = $tkey ? json_decode(json_encode($T[$tkey][6]), true) : layout('landscape', bg_color($DARK), []);
    $lay  = $mod($base);
    $bg   = $lay['background'];
    $widgetsOut = [];
    $mediaIds = $bg['media_id'] ? [$bg['media_id']] : [];
    foreach ($lay['widgets'] as $wi => $wdg) {
        $wid++;
        $wuid = uid("screen{$sid}-widget{$wi}");
        $cfg = $wdg['configuration'];
        $widgetRows[] = ['widget_id' => $wid, 'widget_uid' => $wuid, 'screen_id' => $sid, 'widget_type' => $wdg['widget_type'],
            'widget_name' => $wdg['widget_name'], 'position_x' => $wdg['position_x'], 'position_y' => $wdg['position_y'],
            'width' => $wdg['width'], 'height' => $wdg['height'], 'rotation' => 0, 'z_index' => $wdg['z_index'],
            'configuration_json' => jr($cfg), 'is_locked' => 0, 'is_visible' => 1, 'status' => 'active'];
        // collect media references → widget_assets
        $refs = [];
        if (!empty($cfg['media_id'])) $refs[] = $cfg['media_id'];
        foreach (($cfg['items'] ?? []) as $it) if (is_array($it) && !empty($it['media_id'])) $refs[] = $it['media_id'];
        foreach ($refs as $o => $mid) { $assetRows[] = ['widget_id' => $wid, 'media_id' => $mid, 'asset_order' => $o, 'duration' => null, 'settings_json' => null]; $mediaIds[] = $mid; }
        $widgetsOut[] = ['widget_uid' => $wuid] + $wdg;
    }
    $suid = uid("screen-{$sid}");
    $published = $status === 'published';
    $screenRows[] = ['screen_id' => $sid, 'screen_uid' => $suid, 'user_id' => 3, 'template_id' => $tkey ? $tplId[$tkey] : null,
        'folder_id' => $folder, 'screen_name' => $name, 'description' => null, 'orientation' => $lay['canvas']['orientation'],
        'width' => $lay['canvas']['width'], 'height' => $lay['canvas']['height'], 'background_type' => $bg['type'],
        'background_value' => $bg['value'], 'background_media_id' => $bg['media_id'], 'background_config_json' => jr($bg['config']),
        'default_duration' => $dur, 'thumbnail_path' => null, 'status' => $status, 'layout_version' => 1,
        'published_version' => $published ? 1 : null, 'has_unpublished_changes' => $published ? 0 : 1, 'is_favorite' => $sid === 1 ? 1 : 0,
        'expires_at' => null, 'published_at' => $published ? '2026-11-01 09:00:00' : null];
    if ($published) {
        $manifest = [];
        foreach (array_unique($mediaIds) as $mid) {
            $m = $mediaById[$mid];
            $manifest[] = ['media_id' => $mid, 'file_path' => $m['file_path'], 'mime_type' => $m['mime_type'], 'file_size' => $m['file_size'], 'checksum_sha256' => $m['checksum_sha256']];
        }
        $doc = ['schema_version' => 1, 'screen_id' => $sid, 'screen_uid' => $suid, 'version' => 1, 'screen_name' => $name,
            'canvas' => $lay['canvas'], 'background' => $bg, 'default_duration' => $dur, 'widgets' => $widgetsOut, 'media' => $manifest];
        $screenPub[$sid] = $doc;
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pubRows[] = ['screen_id' => $sid, 'version' => 1, 'published_json' => raw(q($json)), 'content_hash' => hash('sha256', $json),
            'published_by' => 3, 'published_at' => '2026-11-01 09:00:00', 'status' => 'current'];
    }
}
// Aisha (user 2) — 2 screens = her limit (tests limit enforcement)
foreach ([[6, 'Cafe Menu', 'cafe_menu_p'], [7, 'Daily Specials', 'menu_specials_p']] as [$sid, $name, $tkey]) {
    $lay = $T[$tkey][6]; $bg = $lay['background'];
    foreach ($lay['widgets'] as $wi => $wdg) {
        $wid++;
        $widgetRows[] = ['widget_id' => $wid, 'widget_uid' => uid("screen{$sid}-widget{$wi}"), 'screen_id' => $sid, 'widget_type' => $wdg['widget_type'],
            'widget_name' => $wdg['widget_name'], 'position_x' => $wdg['position_x'], 'position_y' => $wdg['position_y'], 'width' => $wdg['width'],
            'height' => $wdg['height'], 'rotation' => 0, 'z_index' => $wdg['z_index'], 'configuration_json' => jr($wdg['configuration']),
            'is_locked' => 0, 'is_visible' => 1, 'status' => 'active'];
    }
    $screenRows[] = ['screen_id' => $sid, 'screen_uid' => uid("screen-{$sid}"), 'user_id' => 2, 'template_id' => $tplId[$tkey], 'folder_id' => null,
        'screen_name' => $name, 'description' => null, 'orientation' => 'portrait', 'width' => 1080, 'height' => 1920, 'background_type' => $bg['type'],
        'background_value' => $bg['value'], 'background_media_id' => null, 'background_config_json' => jr($bg['config']), 'default_duration' => 10,
        'thumbnail_path' => null, 'status' => 'draft', 'layout_version' => 1, 'published_version' => null, 'has_unpublished_changes' => 1,
        'is_favorite' => 0, 'expires_at' => null, 'published_at' => null];
}
$sql .= insert('screens', $screenRows);
$sql .= insert('screen_widgets', $widgetRows);
$sql .= insert('widget_assets', $assetRows);
$sql .= insert('screen_publications', $pubRows);

// playlists -----------------------------------------------------------------
$playlists = [
    [1, 'Store Front Loop',   'Welcome → Promo → Product → Company → (Contact once published)', 'fade', 800, [1, 2, 3, 4, 5], [10, 12, 15, 10, 8], 'published'],
    [2, 'Evening Promotions', 'Promo-heavy evening rotation', 'slide_left', 600, [2, 3], [20, 20], 'published'],
];
$plRows = []; $itemRows = []; $plPub = []; $item = 0;
$schedules = [
    // playlist, name, type, start_time, end_time, days, priority
    [1, 'Morning promotion',   'recurring', '08:00:00', '12:00:00', [1, 2, 3, 4, 5, 6, 7], 10],
    [1, 'Afternoon promotion', 'recurring', '12:00:00', '17:00:00', [1, 2, 3, 4, 5, 6, 7], 10],
    [2, 'Evening promotion',   'recurring', '17:00:00', '23:00:00', [1, 2, 3, 4, 5, 6, 7], 20],
    [2, 'Black Friday',        'special_event', '00:00:00', '23:59:59', null, 50],
];
foreach ($playlists as [$pid, $pname, $desc, $tr, $trd, $sids, $durs, $status]) {
    $puid = uid("playlist-{$pid}");
    $plRows[] = ['playlist_id' => $pid, 'playlist_uid' => $puid, 'user_id' => 3, 'playlist_name' => $pname, 'description' => $desc,
        'loop_enabled' => 1, 'autoplay' => 1, 'shuffle' => 0, 'transition_type' => $tr, 'transition_duration' => $trd, 'default_duration' => 10,
        'priority' => 0, 'status' => $status, 'published_version' => 1, 'has_unpublished_changes' => 0, 'published_at' => '2026-11-01 09:05:00'];
    $pubItems = [];
    foreach ($sids as $o => $sid) {
        $item++;
        $itemRows[] = ['item_id' => $item, 'playlist_id' => $pid, 'screen_id' => $sid, 'user_id' => 3, 'sort_order' => $o + 1,
            'duration' => $durs[$o], 'transition_type' => 'inherit', 'is_enabled' => 1, 'valid_from' => null, 'valid_until' => null];
        if (isset($screenPub[$sid])) {   // unpublished screens are skipped in the published snapshot
            $pubItems[] = ['item_id' => $item, 'screen_id' => $sid, 'screen_version' => 1, 'sort_order' => $o + 1, 'duration' => $durs[$o],
                'transition_type' => 'inherit', 'valid_from' => null, 'valid_until' => null, 'screen' => $screenPub[$sid]];
        }
    }
    $sch = [];
    foreach ($schedules as $si => [$spid, $sname, $stype, $st, $et, $days, $prio]) {
        if ($spid !== $pid) continue;
        $sch[] = ['schedule_id' => $si + 1, 'schedule_name' => $sname, 'schedule_type' => $stype,
            'start_date' => $stype === 'special_event' ? '2026-11-27' : '2026-01-01', 'end_date' => $stype === 'special_event' ? '2026-11-27' : null,
            'start_time' => $st, 'end_time' => $et, 'days_of_week' => $days, 'timezone' => null, 'priority' => $prio];
    }
    $media = [];
    foreach ($pubItems as $pi) foreach ($pi['screen']['media'] as $m) $media[$m['media_id']] = $m;
    $doc = ['schema_version' => 1, 'playlist_id' => $pid, 'playlist_uid' => $puid, 'version' => 1, 'playlist_name' => $pname,
        'loop_enabled' => true, 'autoplay' => true, 'shuffle' => false, 'default_duration' => 10,
        'transition' => ['type' => $tr, 'duration_ms' => $trd], 'items' => $pubItems, 'schedules' => $sch, 'media' => array_values($media)];
    $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $plPub[] = ['playlist_id' => $pid, 'version' => 1, 'published_json' => raw(q($json)), 'content_hash' => hash('sha256', $json),
        'published_by' => 3, 'published_at' => '2026-11-01 09:05:00', 'status' => 'current'];
}
$sql .= insert('playlists', $plRows);
$sql .= insert('playlist_items', $itemRows);
$schRows = [];
foreach ($schedules as $si => [$spid, $sname, $stype, $st, $et, $days, $prio]) {
    $schRows[] = ['schedule_id' => $si + 1, 'playlist_id' => $spid, 'schedule_name' => $sname, 'schedule_type' => $stype,
        'start_date' => $stype === 'special_event' ? '2026-11-27' : '2026-01-01', 'end_date' => $stype === 'special_event' ? '2026-11-27' : null,
        'start_time' => $st, 'end_time' => $et, 'days_of_week_json' => $days === null ? null : jr($days), 'timezone' => null,
        'priority' => $prio, 'status' => 'active'];
}
$sql .= insert('playlist_schedules', $schRows);
$sql .= insert('playlist_publications', $plPub);
// Display codes: type one of these on a TV → "Show Display".
$sql .= insert('display_codes', [
    ['code' => 'STORE1', 'user_id' => 3, 'screen_id' => null, 'playlist_id' => 1],
    ['code' => 'EVENING', 'user_id' => 3, 'screen_id' => null, 'playlist_id' => 2],
    ['code' => 'WELCOME', 'user_id' => 3, 'screen_id' => 1, 'playlist_id' => null],
    ['code' => 'PROMO1', 'user_id' => 3, 'screen_id' => 2, 'playlist_id' => null],
]);

// locations & devices -------------------------------------------------------
$sql .= insert('locations', [
    ['location_id' => 1, 'user_id' => 3, 'location_name' => 'Al Wasl Store', 'address' => 'Al Wasl Road', 'city' => 'Dubai', 'country' => 'UAE', 'latitude' => 25.1972, 'longitude' => 55.2441, 'timezone' => 'Asia/Dubai'],
    ['location_id' => 2, 'user_id' => 3, 'location_name' => 'Mall Kiosk', 'address' => 'Level 1, City Mall', 'city' => 'Dubai', 'country' => 'UAE', 'latitude' => null, 'longitude' => null, 'timezone' => 'Asia/Dubai'],
]);
$caps = ['commands' => ['restart', 'pause', 'resume', 'sync', 'clear_cache', 'identify'], 'video' => true, 'webview' => true, 'max_texture' => 4096];
$sql .= insert('devices', [
    ['device_id' => 1, 'device_uid' => 'demo-lobby-tv-0001', 'user_id' => 3, 'location_id' => 1, 'device_name' => 'Entrance TV',
        'device_code' => 'DSP-7K4Q', 'device_token_hash' => hash('sha256', 'dev-device-token-lobby-tv'), 'device_type' => 'android_tv',
        'platform' => 'android', 'os_version' => '12', 'manufacturer' => 'Generic', 'model' => 'Android TV 4K', 'resolution' => '3840x2160',
        'orientation' => 'landscape', 'app_version' => '1.0.0', 'timezone' => 'Asia/Dubai', 'ip_address' => '10.0.0.21',
        'storage_total_mb' => 8192, 'storage_free_mb' => 5120, 'capabilities_json' => jr($caps), 'current_playlist_id' => 1, 'current_screen_id' => 1,
        'current_content_version' => 'pl:1@v1', 'playback_state' => 'playing', 'last_heartbeat' => '2026-11-05 08:00:00',
        'last_sync_at' => '2026-11-05 07:55:00', 'paired_at' => '2026-11-01 09:10:00', 'status' => 'active'],
    ['device_id' => 2, 'device_uid' => 'demo-kiosk-box-0002', 'user_id' => 3, 'location_id' => 2, 'device_name' => 'Kiosk Portrait Screen',
        'device_code' => 'DSP-9M2X', 'device_token_hash' => hash('sha256', 'dev-device-token-kiosk'), 'device_type' => 'android_box',
        'platform' => 'android', 'os_version' => '11', 'manufacturer' => 'Generic', 'model' => 'TV Box X96', 'resolution' => '1080x1920',
        'orientation' => 'portrait', 'app_version' => '1.0.0', 'timezone' => 'Asia/Dubai', 'ip_address' => '10.0.1.33',
        'storage_total_mb' => 16384, 'storage_free_mb' => 12000, 'capabilities_json' => jr($caps), 'current_playlist_id' => 2, 'current_screen_id' => null,
        'current_content_version' => 'pl:2@v1', 'playback_state' => 'idle', 'last_heartbeat' => '2026-11-03 22:14:00',
        'last_sync_at' => '2026-11-03 22:10:00', 'paired_at' => '2026-11-02 12:00:00', 'status' => 'active'],
    ['device_id' => 3, 'device_uid' => 'demo-unpaired-tv-0003', 'user_id' => null, 'location_id' => null, 'device_name' => 'New display',
        'device_code' => 'DSP-3H8R', 'device_token_hash' => hash('sha256', 'dev-device-token-unpaired'), 'device_type' => 'android_tv',
        'platform' => 'android', 'os_version' => '13', 'manufacturer' => 'Generic', 'model' => 'Smart TV', 'resolution' => '1920x1080',
        'orientation' => 'auto', 'app_version' => '1.0.0', 'timezone' => null, 'ip_address' => null, 'storage_total_mb' => null,
        'storage_free_mb' => null, 'capabilities_json' => jr($caps), 'current_playlist_id' => null, 'current_screen_id' => null,
        'current_content_version' => null, 'playback_state' => 'idle', 'last_heartbeat' => null, 'last_sync_at' => null, 'paired_at' => null, 'status' => 'unpaired'],
]);
$sql .= insert('device_playlists', [
    ['device_id' => 1, 'playlist_id' => 1, 'user_id' => 3, 'is_default' => 1, 'assigned_by' => 3, 'assigned_at' => '2026-11-01 09:12:00'],
    ['device_id' => 1, 'playlist_id' => 2, 'user_id' => 3, 'is_default' => 0, 'assigned_by' => 3, 'assigned_at' => '2026-11-01 09:12:00'],
    ['device_id' => 2, 'playlist_id' => 2, 'user_id' => 3, 'is_default' => 1, 'assigned_by' => 3, 'assigned_at' => '2026-11-02 12:05:00'],
]);
$sql .= insert('device_pairing_codes', [
    ['pairing_code_hash' => hash('sha256', 'K7Q4ZP'), 'device_id' => 3, 'attempts' => 0, 'expires_at' => '2099-12-31 23:59:59', 'used_at' => null, 'used_by' => null],
]);
$sql .= insert('device_logs', [
    ['device_id' => 1, 'log_type' => 'sync', 'message' => 'Downloaded playlist 1 v1 (3 media files)', 'context_json' => jr(['playlist_id' => 1, 'version' => 1]), 'logged_at' => '2026-11-05 07:55:00'],
    ['device_id' => 1, 'log_type' => 'info', 'message' => 'Playback started', 'context_json' => null, 'logged_at' => '2026-11-05 07:55:05'],
    ['device_id' => 2, 'log_type' => 'playback_error', 'message' => 'Weather provider timeout; widget hidden, playback continued', 'context_json' => jr(['screen_id' => 3, 'widget_type' => 'weather']), 'logged_at' => '2026-11-03 21:40:00'],
    ['device_id' => 2, 'log_type' => 'warning', 'message' => 'Network lost — continuing from offline cache', 'context_json' => null, 'logged_at' => '2026-11-03 22:14:30'],
]);
$sql .= insert('device_commands', [
    ['device_id' => 1, 'command' => 'sync', 'payload_json' => null, 'status' => 'completed', 'result_message' => 'Up to date', 'issued_by' => 3,
        'delivered_at' => '2026-11-04 10:00:20', 'completed_at' => '2026-11-04 10:00:25', 'expires_at' => '2026-11-04 11:00:00'],
]);
$sql .= insert('playback_logs', [
    ['device_id' => 1, 'playlist_id' => 1, 'screen_id' => 1, 'playlist_version' => 1, 'screen_version' => 1, 'started_at' => '2026-11-05 07:56:00', 'duration_ms' => 10000, 'completed' => 1],
    ['device_id' => 1, 'playlist_id' => 1, 'screen_id' => 2, 'playlist_version' => 1, 'screen_version' => 1, 'started_at' => '2026-11-05 07:56:10', 'duration_ms' => 12000, 'completed' => 1],
    ['device_id' => 1, 'playlist_id' => 1, 'screen_id' => 3, 'playlist_version' => 1, 'screen_version' => 1, 'started_at' => '2026-11-05 07:56:22', 'duration_ms' => 15000, 'completed' => 1],
]);
$sql .= insert('emergency_messages', [
    ['user_id' => 3, 'title' => 'Fire drill', 'body' => 'This is a scheduled drill. Please proceed calmly to the nearest exit.',
        'style_json' => jr(['background' => '#B00020', 'color' => '#FFFFFF', 'flash' => true]), 'target_all_devices' => 1,
        'target_device_ids_json' => null, 'starts_at' => '2026-10-15 10:00:00', 'ends_at' => '2026-10-15 10:15:00', 'status' => 'expired'],
]);

// notifications, activity, settings -----------------------------------------
$sql .= insert('notifications', [
    ['user_id' => 3, 'title' => 'Device offline', 'message' => 'Kiosk Portrait Screen has not sent a heartbeat since 3 Nov 22:14.', 'type' => 'device_offline', 'link' => '/devices/2', 'is_read' => 0],
    ['user_id' => 3, 'title' => 'Playlist published', 'message' => 'Store Front Loop v1 is live on 1 device.', 'type' => 'success', 'link' => '/playlists/1', 'is_read' => 1],
    ['user_id' => 2, 'title' => 'Screen limit reached', 'message' => 'You are using 2 of 2 screens. Contact your administrator to upgrade.', 'type' => 'limit_reached', 'link' => '/screens', 'is_read' => 0],
]);
$sql .= insert('activity_logs', [
    ['user_id' => 1, 'action' => 'admin.user.create', 'entity_type' => 'user', 'entity_id' => 3, 'description' => 'Created user bilal@demo.local', 'meta_json' => null, 'ip_address' => '127.0.0.1'],
    ['user_id' => 1, 'action' => 'admin.user.limits', 'entity_type' => 'user', 'entity_id' => 3, 'description' => 'Set max screens to 5', 'meta_json' => jr(['max_screens' => 5]), 'ip_address' => '127.0.0.1'],
    ['user_id' => 3, 'action' => 'screen.publish', 'entity_type' => 'screen', 'entity_id' => 1, 'description' => 'Published Welcome Screen v1', 'meta_json' => null, 'ip_address' => '127.0.0.1'],
    ['user_id' => 3, 'action' => 'playlist.publish', 'entity_type' => 'playlist', 'entity_id' => 1, 'description' => 'Published Store Front Loop v1', 'meta_json' => null, 'ip_address' => '127.0.0.1'],
    ['user_id' => 3, 'action' => 'device.pair', 'entity_type' => 'device', 'entity_id' => 1, 'description' => 'Paired Entrance TV', 'meta_json' => null, 'ip_address' => '127.0.0.1'],
    ['user_id' => 1, 'action' => 'admin.user.suspend', 'entity_type' => 'user', 'entity_id' => 6, 'description' => 'Suspended eva@demo.local', 'meta_json' => null, 'ip_address' => '127.0.0.1'],
]);
$settings = [
    ['app_name', 'Signal Signage', 'string', 1, 'Product name shown in apps'],
    ['brand_primary_color', '#008C86', 'string', 1, 'Primary brand colour'],
    ['support_email', 'support@signage.local', 'string', 1, 'Support contact'],
    ['token_ttl_days', '30', 'int', 0, 'API token lifetime for management apps'],
    ['pairing_code_ttl_minutes', '15', 'int', 0, 'Lifetime of a TV pairing code'],
    ['heartbeat_interval_seconds', '60', 'int', 1, 'How often players report in'],
    ['offline_after_seconds', '180', 'int', 0, 'Device considered offline after this silence'],
    ['sync_interval_seconds', '120', 'int', 1, 'How often players poll for new versions'],
    ['max_image_upload_mb', '20', 'int', 0, 'Global ceiling for image uploads'],
    ['max_video_upload_mb', '500', 'int', 0, 'Global ceiling for video uploads'],
    ['allowed_image_mimes', '["image/jpeg","image/png","image/webp","image/gif"]', 'json', 0, 'Accepted image MIME types'],
    ['allowed_video_mimes', '["video/mp4"]', 'json', 0, 'Accepted video MIME types (H.264/AAC MP4 for Android TV)'],
    ['allowed_document_mimes', '["application/pdf"]', 'json', 0, 'Accepted document MIME types'],
    ['weather_provider', 'open_meteo', 'string', 0, 'open_meteo (no key) | openweathermap (needs key)'],
    ['weather_api_key', '', 'string', 0, 'API key for key-based weather providers'],
    ['storage_driver', 'local', 'string', 0, 'local | s3'],
    ['login_rate_limit_per_15min', '10', 'int', 0, 'Login attempts per IP per 15 minutes'],
    ['maintenance_mode', 'false', 'bool', 1, 'Blocks non-admin API access when true'],
];
$sql .= insert('app_settings', array_map(fn($s) => ['setting_key' => $s[0], 'setting_value' => $s[1], 'value_type' => $s[2], 'is_public' => $s[3], 'description' => $s[4]], $settings));

$sql .= "COMMIT;\n";

file_put_contents($OUT_SQL, $sql);
fwrite(STDOUT, sprintf("seed.sql written (%d KB): %d templates, %d widget types, %d screens, %d widgets, %d media\n",
    strlen($sql) / 1024, count($T), count($WIDGETS), count($screenRows), count($widgetRows), count($mediaRows)));
