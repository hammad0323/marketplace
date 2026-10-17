<?php
/**
 * Proposal icon library (Bootstrap Icons, MIT licence — see assets/picons/LICENSE.txt).
 * Icons are emitted as coloured SVG data-URIs so they look identical in the
 * browser and in the Dompdf-generated PDF.
 */

function icon_names(): array
{
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (glob(APP_ROOT . '/assets/picons/*.svg') as $f) {
            $names[] = basename($f, '.svg');
        }
        sort($names);
    }
    return $names;
}

function icon_svg(string $name, string $color = '#000000'): string
{
    static $cache = [];
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($name));
    $file = APP_ROOT . '/assets/picons/' . $name . '.svg';
    if (!is_file($file)) {
        $file = APP_ROOT . '/assets/picons/check-circle.svg';
    }
    if (!isset($cache[$file])) {
        $cache[$file] = file_get_contents($file);
    }
    $svg = $cache[$file];
    $svg = preg_replace('/\s(width|height|class)="[^"]*"/', '', $svg);
    return str_replace('currentColor', $color, $svg);
}

/** <img> tag for an icon. */
function icon_img(string $name, string $color, int $size = 24, string $class = 'pm-ico'): string
{
    if ($name === '') return '';
    $data = 'data:image/svg+xml;base64,' . base64_encode(icon_svg($name, $color));
    return '<img class="' . e($class) . '" src="' . $data . '" width="' . $size . '" height="' . $size . '" style="width:' . $size . 'px;height:' . $size . 'px" alt="">';
}

/** Small shapes (check, cross, dot …) used in lists / tables. */
function shape_img(string $shape, string $color, int $size = 14): string
{
    $map = [
        'check'  => 'check-lg',
        'circle-check' => 'check-circle',
        'cross'  => 'x-circle',
        'arrow'  => 'arrow-right',
        'star'   => 'star-fill',
        'dot'    => null,
    ];
    if ($shape === 'dot') {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><circle cx="8" cy="8" r="4" fill="' . $color . '"/></svg>';
        return '<img class="pm-ico" src="data:image/svg+xml;base64,' . base64_encode($svg) . '" width="' . $size . '" height="' . $size . '" style="width:' . $size . 'px;height:' . $size . 'px" alt="">';
    }
    return icon_img($map[$shape] ?? 'check-lg', $color, $size);
}
