<?php
/**
 * Site settings stored in the site_settings key/value table, plus theme CSS generation.
 */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM site_settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key, $default ? '1' : '0');
    return $v === '1' || $v === 1 || $v === true || $v === 'on';
}

function setting_save(string $key, $value, string $group = 'general'): void
{
    db_exec(
        'INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)',
        [$key, is_array($value) ? json_encode($value) : (string) $value, $group]
    );
    settings_all(true);
}

function setting_json(string $key, array $default = []): array
{
    $v = json_decode((string) setting($key, ''), true);
    return is_array($v) ? $v : $default;
}

/** Google Fonts the theme editor offers. Values are font-family names on fonts.google.com. */
function theme_font_options(): array
{
    return [
        'heading' => ['Playfair Display', 'Cormorant Garamond', 'Bodoni Moda', 'Libre Caslon Display', 'Marcellus', 'Cinzel', 'DM Serif Display', 'Oswald', 'Bebas Neue', 'Montserrat'],
        'body'    => ['Manrope', 'Inter', 'Jost', 'DM Sans', 'Montserrat', 'Lato', 'Work Sans', 'Poppins', 'Nunito Sans'],
    ];
}

function theme_defaults(): array
{
    return [
        'theme_primary' => '#111111',
        'theme_secondary' => '#242424',
        'theme_graphite' => '#3A3A3A',
        'theme_accent' => '#B69B63',
        'theme_beige' => '#D8CBB8',
        'theme_sand' => '#E9E1D5',
        'theme_body_bg' => '#F7F6F2',
        'theme_text' => '#1A1A1A',
        'theme_header_bg' => '#111111',
        'theme_header_text' => '#F7F6F2',
        'theme_footer_bg' => '#111111',
        'theme_footer_text' => '#D8CBB8',
        'theme_announce_bg' => '#B69B63',
        'theme_announce_text' => '#111111',
        'theme_btn_bg' => '#111111',
        'theme_btn_text' => '#FFFFFF',
        'theme_font_heading' => 'Playfair Display',
        'theme_font_body' => 'Manrope',
        'theme_heading_transform' => 'none',
        'theme_heading_weight' => '500',
        'theme_button_style' => 'square',
        'theme_button_uppercase' => '1',
        'theme_radius' => '2',
        'theme_container' => '1320',
        'theme_section_spacing' => '96',
        'theme_card_ratio' => '3/4',
        'theme_card_align' => 'left',
        'theme_card_hover_swap' => '1',
        'theme_card_show_colors' => '1',
        'theme_card_show_rating' => '1',
        'theme_nav_layout' => 'logo-left',
        'theme_sticky_header' => '1',
        'theme_mobile_nav' => 'drawer-left',
        'theme_footer_layout' => 'columns',
        'theme_animations' => '1',
        'theme_animation_speed' => '700',
    ];
}

function theme(string $key): string
{
    $d = theme_defaults();
    return (string) setting($key, $d[$key] ?? '');
}

function safe_color(string $value, string $fallback = '#111111'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
}

function google_fonts_url(): string
{
    $fonts = array_unique([theme('theme_font_heading'), theme('theme_font_body')]);
    $allowed = array_merge(...array_values(theme_font_options()));
    $families = [];
    foreach ($fonts as $f) {
        if (in_array($f, $allowed, true)) {
            $families[] = 'family=' . str_replace(' ', '+', $f) . ':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400';
        }
    }
    return 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap';
}

/** CSS custom properties generated from the theme settings (printed in <head>). */
function theme_css_vars(): string
{
    $c = fn($k) => safe_color(theme($k), theme_defaults()[$k]);
    $radius = max(0, min(24, (int) theme('theme_radius')));
    $btnRadius = ['square' => $radius . 'px', 'rounded' => '8px', 'pill' => '999px'][theme('theme_button_style')] ?? '0px';
    $ratio = in_array(theme('theme_card_ratio'), ['3/4', '4/5', '1/1', '2/3'], true) ? theme('theme_card_ratio') : '3/4';
    $font = fn($k) => '"' . str_replace('"', '', theme($k)) . '"';
    $vars = [
        '--uc-primary' => $c('theme_primary'),
        '--uc-secondary' => $c('theme_secondary'),
        '--uc-graphite' => $c('theme_graphite'),
        '--uc-accent' => $c('theme_accent'),
        '--uc-beige' => $c('theme_beige'),
        '--uc-sand' => $c('theme_sand'),
        '--uc-body-bg' => $c('theme_body_bg'),
        '--uc-text' => $c('theme_text'),
        '--uc-header-bg' => $c('theme_header_bg'),
        '--uc-header-text' => $c('theme_header_text'),
        '--uc-footer-bg' => $c('theme_footer_bg'),
        '--uc-footer-text' => $c('theme_footer_text'),
        '--uc-announce-bg' => $c('theme_announce_bg'),
        '--uc-announce-text' => $c('theme_announce_text'),
        '--uc-btn-bg' => $c('theme_btn_bg'),
        '--uc-btn-text' => $c('theme_btn_text'),
        '--uc-font-heading' => $font('theme_font_heading') . ', Georgia, serif',
        '--uc-font-body' => $font('theme_font_body') . ', system-ui, sans-serif',
        '--uc-heading-transform' => theme('theme_heading_transform') === 'uppercase' ? 'uppercase' : 'none',
        '--uc-heading-weight' => (string) max(300, min(800, (int) theme('theme_heading_weight'))),
        '--uc-radius' => $radius . 'px',
        '--uc-btn-radius' => $btnRadius,
        '--uc-btn-transform' => theme('theme_button_uppercase') === '1' ? 'uppercase' : 'none',
        '--uc-container' => max(960, min(1920, (int) theme('theme_container'))) . 'px',
        '--uc-section-space' => max(24, min(200, (int) theme('theme_section_spacing'))) . 'px',
        '--uc-card-ratio' => $ratio,
        '--uc-card-align' => theme('theme_card_align') === 'center' ? 'center' : 'left',
        '--uc-anim-speed' => max(200, min(2000, (int) theme('theme_animation_speed'))) . 'ms',
    ];
    $css = ':root{';
    foreach ($vars as $k => $v) {
        $css .= $k . ':' . $v . ';';
    }
    return $css . '}';
}

function store_name(): string
{
    return setting('store_name', 'Urban Collections');
}
