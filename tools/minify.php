<?php
/**
 * Production asset minifier (no Node required).
 *   php tools/minify.php
 * Creates *.min.css / *.min.js next to the sources. includes/functions.php
 * asset() serves the .min file automatically when APP_ENV = production.
 */
if (PHP_SAPI !== 'cli') exit('CLI only');
$root = dirname(__DIR__);

function min_css(string $css): string
{
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);
    return trim(str_replace(';}', '}', $css));
}

/** Conservative JS minifier: removes block comments, full-line // comments and indentation. */
function min_js(string $js): string
{
    $js = preg_replace('!^\s*/\*.*?\*/!ms', '', $js);
    $out = [];
    foreach (preg_split('/\R/', $js) as $line) {
        $t = trim($line);
        if ($t === '' || strpos($t, '//') === 0) continue;
        $out[] = $t;
    }
    return implode("\n", $out);
}

$files = ['assets/css/style.css', 'assets/js/app.js', 'assets/js/rashan.js'];
foreach ($files as $f) {
    $src = file_get_contents("$root/$f");
    $min = substr($f, -4) === '.css' ? min_css($src) : min_js($src);
    $dest = preg_replace('/\.(css|js)$/', '.min.$1', $f);
    file_put_contents("$root/$dest", $min);
    printf("%-28s %6d -> %6d bytes\n", $f, strlen($src), strlen($min));
}
