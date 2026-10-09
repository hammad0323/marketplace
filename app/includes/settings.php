<?php
/**
 * Site settings (key/value in `site_settings`) and theme helpers.
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
    return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key, $default ? '1' : '0');
    return $v === '1' || $v === 1 || $v === true || $v === 'true';
}

function setting_json(string $key, $default = [])
{
    $v = json_decode((string) setting($key, ''), true);
    return is_array($v) ? $v : $default;
}

function save_setting(string $key, $value, string $group = 'general'): void
{
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    db_exec(
        'INSERT INTO site_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)',
        [$key, (string) $value, $group]
    );
    settings_all(true);
}

/** Font pairings bundled under assets/vendor/fonts. */
function font_options(): array
{
    return [
        'cormorant_inter' => ['label' => 'Cormorant Garamond + Inter', 'heading' => "'Cormorant Garamond', Georgia, serif", 'body' => "'Inter', system-ui, sans-serif"],
        'playfair_jost'   => ['label' => 'Playfair Display + Jost', 'heading' => "'Playfair Display', Georgia, serif", 'body' => "'Jost', system-ui, sans-serif"],
        'cormorant_jost'  => ['label' => 'Cormorant Garamond + Jost', 'heading' => "'Cormorant Garamond', Georgia, serif", 'body' => "'Jost', system-ui, sans-serif"],
        'playfair_inter'  => ['label' => 'Playfair Display + Inter', 'heading' => "'Playfair Display', Georgia, serif", 'body' => "'Inter', system-ui, sans-serif"],
    ];
}

/** Defaults for every theme token (also used by the admin form). */
function theme_defaults(): array
{
    return [
        'theme_primary'        => '#214E9B',
        'theme_secondary'      => '#101D35',
        'theme_dark'           => '#0A1426',
        'theme_ivory'          => '#F7F5F0',
        'theme_beige'          => '#E8DDCC',
        'theme_accent'         => '#B99A5B',
        'theme_header_bg'      => '#101D35',
        'theme_header_text'    => '#F7F5F0',
        'theme_footer_bg'      => '#0A1426',
        'theme_footer_text'    => '#E8DDCC',
        'theme_button_bg'      => '#214E9B',
        'theme_button_text'    => '#FFFFFF',
        'theme_body_bg'        => '#F7F5F0',
        'theme_text'           => '#1B2333',
        'theme_fonts'          => 'cormorant_inter',
        'theme_base_font_size' => '16',
        'theme_container'      => '1320',
        'theme_section_spacing'=> '96',
        'theme_radius'         => '4',
        'theme_card_style'     => 'classic',
        'theme_header_layout'  => 'logo_left',
        'theme_footer_layout'  => 'columns',
        'theme_mobile_columns' => '2',
        'theme_mobile_sticky_cart' => '1',
        'theme_animations'     => '1',
    ];
}

function theme(string $key): string
{
    $d = theme_defaults();
    return (string) setting($key, $d[$key] ?? '');
}

/** Inline CSS custom properties generated from theme settings. */
function theme_css_vars(): string
{
    $fonts = font_options()[theme('theme_fonts')] ?? font_options()['cormorant_inter'];
    $colorKeys = ['primary', 'secondary', 'dark', 'ivory', 'beige', 'accent', 'header_bg', 'header_text', 'footer_bg', 'footer_text', 'button_bg', 'button_text', 'body_bg', 'text'];
    $css = ':root{';
    foreach ($colorKeys as $k) {
        $v = theme('theme_' . $k);
        if (valid_hex($v)) {
            $css .= '--bg-' . str_replace('_', '-', $k) . ':' . $v . ';';
        }
    }
    $css .= '--font-heading:' . $fonts['heading'] . ';';
    $css .= '--font-body:' . $fonts['body'] . ';';
    $css .= '--fs-base:' . max(14, min(19, (int) theme('theme_base_font_size'))) . 'px;';
    $css .= '--container:' . max(960, min(1800, (int) theme('theme_container'))) . 'px;';
    $css .= '--section-space:' . max(32, min(200, (int) theme('theme_section_spacing'))) . 'px;';
    $css .= '--radius:' . max(0, min(32, (int) theme('theme_radius'))) . 'px;';
    $css .= '}';
    return $css;
}

function currency_code(): string
{
    return setting('currency_code', 'PKR');
}
