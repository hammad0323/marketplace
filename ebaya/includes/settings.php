<?php
/**
 * Site settings. The schema below drives both the storefront defaults and
 * the admin settings forms, so adding a setting here is all it takes to make
 * it editable. Theme settings support draft → preview → publish.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function font_choices(): array
{
    return [
        'Cormorant Garamond' => 'Cormorant Garamond (serif)',
        'Playfair Display' => 'Playfair Display (serif)',
        'Bodoni Moda' => 'Bodoni Moda (serif)',
        'Marcellus' => 'Marcellus (serif)',
        'Lora' => 'Lora (serif)',
        'Jost' => 'Jost (sans)',
        'Montserrat' => 'Montserrat (sans)',
        'Inter' => 'Inter (sans)',
        'Lato' => 'Lato (sans)',
        'Nunito Sans' => 'Nunito Sans (sans)',
    ];
}

/**
 * group => [label, perm, draftable, fields[key => [label, type, default, extra]]]
 * types: text, textarea, html, email, url, color, number, toggle, select, image, secret
 */
function settings_schema(): array
{
    return [
        'general' => ['Store', 'settings.manage', false, [
            'site_name' => ['Store name', 'text', 'Ebaya'],
            'tagline' => ['Tagline', 'text', 'Premium Handcrafted Abayas'],
            'logo' => ['Logo (dark, for light header)', 'image', ''],
            'logo_light' => ['Logo (light, for dark backgrounds)', 'image', ''],
            'logo_height' => ['Logo height (px)', 'number', 42],
            'favicon' => ['Favicon (PNG, square)', 'image', ''],
            'currency_code' => ['Currency code', 'text', 'PKR'],
            'currency_symbol' => ['Currency symbol', 'text', 'Rs.'],
            'contact_email' => ['Customer service email', 'email', 'care@ebaya.pk'],
            'contact_phone' => ['Customer service phone', 'text', '+92 300 0000000'],
            'whatsapp_number' => ['WhatsApp number (international format, digits only)', 'text', ''],
            'address' => ['Studio / business address', 'textarea', 'Lahore, Pakistan'],
            'business_hours' => ['Customer service hours', 'text', 'Mon – Sat, 11am – 7pm'],
            'copyright_text' => ['Copyright text', 'text', '© {year} Ebaya. All rights reserved.'],
        ]],
        'announcement' => ['Announcement bar', 'homepage.manage', false, [
            'announcement_enabled' => ['Show announcement bar', 'toggle', 1],
            'announcement_text' => ['Messages (one per line — they rotate)', 'textarea', "Complimentary delivery on orders above Rs. 15,000\nEach piece finished by hand — made-to-order styles available"],
            'announcement_link' => ['Link (optional)', 'url', '/shop'],
            'announcement_bg' => ['Background colour', 'color', '#354638'],
            'announcement_color' => ['Text colour', 'color', '#F8F5EF'],
        ]],
        'social' => ['Social links', 'settings.manage', false, [
            'social_instagram' => ['Instagram URL', 'url', 'https://instagram.com/'],
            'social_facebook' => ['Facebook URL', 'url', ''],
            'social_tiktok' => ['TikTok URL', 'url', ''],
            'social_pinterest' => ['Pinterest URL', 'url', ''],
            'social_youtube' => ['YouTube URL', 'url', ''],
            'instagram_handle' => ['Instagram handle (shown in footer)', 'text', '@ebaya'],
        ]],
        'checkout' => ['Checkout & policies', 'settings.manage', false, [
            'order_prefix' => ['Order number prefix', 'text', 'EB'],
            'min_order_value' => ['Minimum order value (0 = none)', 'number', 0],
            'allow_checkout_registration' => ['Offer account creation at checkout', 'toggle', 1],
            'custom_order_terms' => ['Custom / made-to-order confirmation text', 'textarea', 'I understand that made-to-order and customised pieces are produced specifically for me, require the stated production time, and are not eligible for change-of-mind returns.'],
            'checkout_terms_text' => ['Checkout terms consent label', 'text', 'I agree to the Terms & Conditions and Returns Policy.'],
            'delivery_info' => ['Delivery info (product page)', 'textarea', 'Ready-to-ship pieces are dispatched within 1–2 working days. Made-to-order pieces are dispatched after their production time.'],
            'returns_info' => ['Returns info (product page)', 'textarea', 'Ready-to-ship pieces may be exchanged within 7 days of delivery if unworn with tags attached. See our Returns & Exchanges policy for details.'],
            'low_stock_default' => ['Default low-stock threshold', 'number', 3],
        ]],
        'email' => ['Email', 'settings.manage', false, [
            'mail_enabled' => ['Send emails', 'toggle', 0],
            'mail_from_name' => ['From name', 'text', 'Ebaya'],
            'mail_from_email' => ['From email', 'email', 'no-reply@example.com'],
            'admin_notify_email' => ['New-order notification email', 'email', ''],
            'smtp_host' => ['SMTP host (blank = PHP mail())', 'text', ''],
            'smtp_port' => ['SMTP port', 'number', 587],
            'smtp_secure' => ['SMTP encryption', 'select', 'tls', ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', '' => 'None']],
            'smtp_user' => ['SMTP username', 'text', ''],
            'smtp_pass' => ['SMTP password', 'secret', ''],
        ]],
        'seo' => ['SEO & analytics', 'seo.manage', false, [
            'seo_title_suffix' => ['Title suffix', 'text', ' | Ebaya'],
            'seo_home_title' => ['Homepage title', 'text', 'Ebaya — Premium Handcrafted Abayas'],
            'seo_default_description' => ['Default meta description', 'textarea', 'Discover Ebaya — premium abayas with hand embroidery, handmade crochet flowers and artisanal details, for everyday elegance and special occasions.'],
            'seo_default_og_image' => ['Default social sharing image', 'image', ''],
            'seo_noindex_site' => ['Discourage search engines from indexing the whole site (staging)', 'toggle', 0],
            'robots_txt_extra' => ['Extra robots.txt rules', 'textarea', ''],
            'ads_txt' => ['ads.txt content', 'textarea', ''],
            'google_site_verification' => ['Google Search Console verification code', 'text', ''],
            'bing_site_verification' => ['Bing verification code', 'text', ''],
            'ga4_id' => ['Google Analytics 4 measurement ID (G-XXXX)', 'text', ''],
            'gtm_id' => ['Google Tag Manager ID (GTM-XXXX)', 'text', ''],
            'meta_pixel_id' => ['Meta Pixel ID', 'text', ''],
        ]],
        'theme' => ['Theme', 'theme.manage', true, [
            'color_primary' => ['Primary (deep olive)', 'color', '#354638'],
            'color_secondary' => ['Secondary (sage green)', 'color', '#A7B29A'],
            'color_bg' => ['Page background (warm ivory)', 'color', '#F8F5EF'],
            'color_surface' => ['Soft surface (champagne beige)', 'color', '#E7D8C4'],
            'color_accent' => ['Accent (antique gold)', 'color', '#B89A64'],
            'color_text' => ['Text (deep espresso)', 'color', '#332820'],
            'header_bg' => ['Header background', 'color', '#F8F5EF'],
            'header_text' => ['Header text', 'color', '#332820'],
            'header_transparent_home' => ['Transparent header over homepage hero', 'toggle', 1],
            'footer_bg' => ['Footer background', 'color', '#354638'],
            'footer_text' => ['Footer text', 'color', '#F8F5EF'],
            'font_heading' => ['Heading font', 'select', 'Cormorant Garamond', font_choices()],
            'font_body' => ['Body font', 'select', 'Jost', font_choices()],
            'heading_weight' => ['Heading weight', 'select', '500', ['300' => 'Light', '400' => 'Regular', '500' => 'Medium', '600' => 'Semi-bold']],
            'heading_case' => ['Heading style', 'select', 'none', ['none' => 'As typed', 'uppercase' => 'Uppercase', 'capitalize' => 'Capitalised']],
            'heading_tracking' => ['Heading letter spacing (em)', 'text', '0.01'],
            'btn_style' => ['Button style', 'select', 'solid', ['solid' => 'Solid', 'outline' => 'Outline', 'underline' => 'Text with underline']],
            'btn_radius' => ['Button corner radius (px)', 'number', 0],
            'btn_case' => ['Button text', 'select', 'uppercase', ['uppercase' => 'Uppercase', 'none' => 'As typed']],
            'radius' => ['Card / image corner radius (px)', 'number', 2],
            'card_style' => ['Product card style', 'select', 'minimal', ['minimal' => 'Minimal', 'bordered' => 'Bordered', 'elevated' => 'Soft shadow']],
            'card_ratio' => ['Product image ratio', 'select', '3/4', ['3/4' => 'Portrait 3:4', '4/5' => 'Portrait 4:5', '2/3' => 'Tall 2:3', '1/1' => 'Square']],
            'card_show_colors' => ['Show colour swatches on cards', 'toggle', 1],
            'container_width' => ['Content width (px)', 'number', 1320],
            'section_spacing' => ['Section spacing (px, desktop)', 'number', 110],
            'nav_layout' => ['Navigation layout', 'select', 'center', ['center' => 'Logo centred, menu below', 'left' => 'Logo left, menu inline']],
            'header_sticky' => ['Sticky header', 'toggle', 1],
            'mobile_nav' => ['Mobile navigation', 'select', 'drawer', ['drawer' => 'Slide-in drawer', 'fullscreen' => 'Full-screen overlay']],
            'footer_layout' => ['Footer layout', 'select', 'columns', ['columns' => 'Four columns', 'centered' => 'Centred']],
            'anim_enabled' => ['Enable scroll animations', 'toggle', 1],
            'anim_style' => ['Reveal animation', 'select', 'fade-up', ['fade-up' => 'Fade up', 'fade' => 'Fade', 'zoom' => 'Soft zoom']],
            'anim_duration' => ['Animation duration (ms)', 'number', 900],
            'parallax_enabled' => ['Enable parallax banners', 'toggle', 1],
            'image_hover_zoom' => ['Zoom product images on hover', 'toggle', 1],
        ]],
    ];
}

function settings_defaults(): array
{
    static $d = null;
    if ($d === null) {
        $d = [];
        foreach (settings_schema() as $g) {
            foreach ($g[3] as $k => $f) $d[$k] = $f[2];
        }
    }
    return $d;
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;
    $cache = settings_defaults();
    $preview = is_preview();
    foreach (db_all('SELECT setting_key, setting_value, draft_value FROM site_settings') as $r) {
        $val = ($preview && $r['draft_value'] !== null) ? $r['draft_value'] : $r['setting_value'];
        if ($val !== null) $cache[$r['setting_key']] = $val; // NULL = draft-only row → keep default
    }
    return $cache;
}

function setting(string $key, $default = null)
{
    $all = settings_all();
    $v = $all[$key] ?? null;
    return ($v === null || $v === '') && $default !== null ? $default : $v;
}

function setting_set(string $key, $value, string $group = 'general', bool $draft = false): void
{
    $value = $value === null ? null : (string)$value;
    if ($draft) {
        db_exec('INSERT INTO site_settings (setting_key, setting_value, draft_value, setting_group) VALUES (?, NULL, ?, ?)
                 ON DUPLICATE KEY UPDATE draft_value = VALUES(draft_value)', [$key, $value, $group]);
    } else {
        db_exec('INSERT INTO site_settings (setting_key, setting_value, draft_value, setting_group) VALUES (?, ?, NULL, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), draft_value = NULL', [$key, $value, $group]);
    }
}

/** Preview mode: an admin viewing unpublished theme/homepage drafts. */
function is_preview(): bool
{
    if (PHP_SAPI === 'cli' || empty($_SESSION['admin_id'])) return false;
    if (isset($_GET['preview'])) {
        $_SESSION['preview_mode'] = $_GET['preview'] === '1';
    }
    return !empty($_SESSION['preview_mode']);
}

/** CSS custom properties generated from theme settings. */
function theme_css_vars(): string
{
    $s = settings_all();
    $hex = fn($k) => v_hex((string)$s[$k]) ? $s[$k] : settings_defaults()[$k];
    $num = fn($k, $min, $max) => clamp_int($s[$k], $min, $max);
    $font = fn($k) => array_key_exists($s[$k], font_choices()) ? $s[$k] : settings_defaults()[$k];
    $serif = fn($f) => preg_match('/Garamond|Playfair|Bodoni|Marcellus|Lora/', $f) ? 'serif' : 'sans-serif';
    $vars = [
        '--eb-primary' => $hex('color_primary'),
        '--eb-secondary' => $hex('color_secondary'),
        '--eb-bg' => $hex('color_bg'),
        '--eb-surface' => $hex('color_surface'),
        '--eb-accent' => $hex('color_accent'),
        '--eb-text' => $hex('color_text'),
        '--eb-header-bg' => $hex('header_bg'),
        '--eb-header-text' => $hex('header_text'),
        '--eb-footer-bg' => $hex('footer_bg'),
        '--eb-footer-text' => $hex('footer_text'),
        '--eb-font-heading' => "'" . $font('font_heading') . "', " . $serif($font('font_heading')),
        '--eb-font-body' => "'" . $font('font_body') . "', " . $serif($font('font_body')),
        '--eb-heading-weight' => in_list((string)$s['heading_weight'], ['300', '400', '500', '600'], '500'),
        '--eb-heading-case' => in_list((string)$s['heading_case'], ['none', 'uppercase', 'capitalize'], 'none'),
        '--eb-heading-tracking' => (is_numeric($s['heading_tracking']) ? (float)$s['heading_tracking'] : 0.01) . 'em',
        '--eb-btn-radius' => $num('btn_radius', 0, 40) . 'px',
        '--eb-btn-case' => in_list((string)$s['btn_case'], ['uppercase', 'none'], 'uppercase'),
        '--eb-radius' => $num('radius', 0, 30) . 'px',
        '--eb-container' => $num('container_width', 960, 1800) . 'px',
        '--eb-section-space' => $num('section_spacing', 40, 200) . 'px',
        '--eb-card-ratio' => in_list((string)$s['card_ratio'], ['3/4', '4/5', '2/3', '1/1'], '3/4'),
        '--eb-anim-duration' => $num('anim_duration', 200, 2000) . 'ms',
        '--eb-announce-bg' => v_hex((string)$s['announcement_bg']) ? $s['announcement_bg'] : '#354638',
        '--eb-announce-text' => v_hex((string)$s['announcement_color']) ? $s['announcement_color'] : '#F8F5EF',
    ];
    $css = ':root{';
    foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
    return $css . '}';
}

function google_fonts_url(): string
{
    $fams = array_unique([setting('font_heading', 'Cormorant Garamond'), setting('font_body', 'Jost')]);
    // Axis specs per family — requesting a weight a family lacks makes Google Fonts fail.
    $axes = [
        'Cormorant Garamond' => ':ital,wght@0,300;0,400;0,500;0,600;1,400',
        'Playfair Display' => ':ital,wght@0,400;0,500;0,600;1,400',
        'Bodoni Moda' => ':ital,wght@0,400;0,500;0,600;1,400',
        'Marcellus' => '',
        'Lora' => ':ital,wght@0,400;0,500;0,600;1,400',
        'Jost' => ':ital,wght@0,300;0,400;0,500;0,600;1,400',
        'Montserrat' => ':ital,wght@0,300;0,400;0,500;0,600;1,400',
        'Inter' => ':wght@300;400;500;600',
        'Lato' => ':ital,wght@0,300;0,400;0,700;1,400',
        'Nunito Sans' => ':wght@300;400;500;600',
    ];
    $parts = [];
    foreach ($fams as $f) {
        if (!isset($axes[$f])) continue;
        $parts[] = 'family=' . str_replace(' ', '+', $f) . $axes[$f];
    }
    return 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
}
