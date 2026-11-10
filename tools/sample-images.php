<?php
/**
 * Generates the SVG illustrations used by the sample catalog
 * (uploads/products/sample/*.svg and assets/images/categories/*.svg).
 * Run from CLI:  php tools/sample-images.php
 * (Also used by tools/seed.php.)  Real stores replace these by uploading photos.
 */

function svg_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Split text into lines of max $n chars. */
function svg_wrap(string $text, int $n): array
{
    $words = explode(' ', $text);
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        if ($cur !== '' && mb_strlen($cur . ' ' . $w) > $n) {
            $lines[] = $cur;
            $cur = $w;
        } else {
            $cur = $cur === '' ? $w : $cur . ' ' . $w;
        }
    }
    if ($cur !== '') $lines[] = $cur;
    return array_slice($lines, 0, 3);
}

function svg_text_lines(array $lines, int $x, int $y, int $size, string $color, string $weight = '700', int $lh = 0, string $anchor = 'middle'): string
{
    $lh = $lh ?: (int) round($size * 1.2);
    $out = '';
    foreach ($lines as $i => $l) {
        $out .= '<text x="' . $x . '" y="' . ($y + $i * $lh) . '" font-family="Arial,Helvetica,sans-serif" font-size="' . $size . '" font-weight="' . $weight . '" fill="' . $color . '" text-anchor="' . $anchor . '">' . svg_esc($l) . '</text>';
    }
    return $out;
}

function svg_wrap_doc(string $inner, string $bg = '#f8fafc'): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400">'
        . '<rect width="400" height="400" fill="' . $bg . '"/>'
        . '<ellipse cx="200" cy="352" rx="130" ry="14" fill="#0f172a" opacity=".08"/>' . $inner . '</svg>';
}

/** Book cover */
function svg_book(string $title, string $sub, string $c1, string $c2, string $publisher = ''): string
{
    $t = svg_text_lines(svg_wrap(mb_strtoupper($title), 13), 212, 150, 22, '#ffffff', '800', 26);
    return svg_wrap_doc(
        '<defs><linearGradient id="b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
        . '<rect x="112" y="46" width="200" height="296" rx="8" fill="#e2e8f0"/>'
        . '<rect x="100" y="40" width="200" height="300" rx="8" fill="url(#b)"/>'
        . '<rect x="100" y="40" width="18" height="300" rx="4" fill="#000" opacity=".18"/>'
        . '<rect x="132" y="70" width="152" height="4" rx="2" fill="#fff" opacity=".6"/>'
        . $t
        . '<circle cx="212" cy="250" r="34" fill="#fff" opacity=".18"/>'
        . '<rect x="140" y="292" width="144" height="26" rx="13" fill="#fff" opacity=".92"/>'
        . svg_text_lines([$sub], 212, 310, 14, $c2, '700')
        . ($publisher ? svg_text_lines([$publisher], 212, 92, 11, '#ffffff', '600') : ''),
        '#f1f5f9');
}

/** Notebook / register */
function svg_notebook(string $title, string $sub, string $c1): string
{
    $rings = '';
    for ($y = 70; $y <= 320; $y += 22) $rings .= '<rect x="102" y="' . $y . '" width="26" height="8" rx="4" fill="#94a3b8"/>';
    return svg_wrap_doc(
        '<rect x="118" y="44" width="190" height="300" rx="10" fill="#cbd5e1"/>'
        . '<rect x="110" y="38" width="190" height="300" rx="10" fill="' . $c1 . '"/>' . $rings
        . '<rect x="140" y="120" width="140" height="92" rx="8" fill="#fff"/>'
        . svg_text_lines(svg_wrap(mb_strtoupper($title), 12), 210, 152, 17, '#1f2933', '800', 20)
        . svg_text_lines([$sub], 210, 200, 12, '#475569', '600')
        . '<rect x="140" y="250" width="140" height="6" rx="3" fill="#fff" opacity=".35"/><rect x="140" y="266" width="100" height="6" rx="3" fill="#fff" opacity=".35"/>',
        '#f8fafc');
}

/** Pencils / pens set */
function svg_pens(string $title, array $colors): string
{
    $inner = '';
    foreach ($colors as $i => $c) {
        $x = 128 + $i * 30;
        $inner .= '<rect x="' . $x . '" y="70" width="22" height="200" rx="3" fill="' . $c . '"/>'
            . '<polygon points="' . $x . ',270 ' . ($x + 22) . ',270 ' . ($x + 11) . ',304" fill="#fcd9a5"/>'
            . '<polygon points="' . ($x + 7) . ',293 ' . ($x + 15) . ',293 ' . ($x + 11) . ',304" fill="' . $c . '"/>'
            . '<rect x="' . $x . '" y="70" width="22" height="18" fill="#e5e7eb"/>';
    }
    return svg_wrap_doc($inner . '<rect x="100" y="210" width="200" height="110" rx="14" fill="#0f766e"/>'
        . svg_text_lines(svg_wrap(mb_strtoupper($title), 16), 200, 258, 17, '#ffffff', '800', 21), '#f8fafc');
}

/** Sack (flour, rice, sugar) */
function svg_sack(string $brand, string $title, string $weight, string $c1, string $c2): string
{
    return svg_wrap_doc(
        '<path d="M130 70 Q200 50 270 70 L292 320 Q200 345 108 320 Z" fill="' . $c1 . '"/>'
        . '<path d="M130 70 Q200 90 270 70 L266 92 Q200 110 134 92 Z" fill="#000" opacity=".1"/>'
        . '<rect x="135" y="130" width="130" height="130" rx="14" fill="#fff"/>'
        . svg_text_lines([mb_strtoupper($brand)], 200, 160, 18, $c2, '900')
        . svg_text_lines(svg_wrap($title, 14), 200, 192, 15, '#1f2933', '700', 18)
        . '<rect x="160" y="226" width="80" height="24" rx="12" fill="' . $c2 . '"/>'
        . svg_text_lines([$weight], 200, 243, 14, '#ffffff', '800'),
        '#fffbeb');
}

/** Bottle (oil, liquids) */
function svg_bottle(string $brand, string $title, string $size, string $c1, string $liquid = '#facc15'): string
{
    return svg_wrap_doc(
        '<rect x="180" y="40" width="40" height="30" rx="5" fill="' . $c1 . '"/>'
        . '<path d="M175 70 h50 v30 q45 25 45 70 v150 q0 20 -20 20 h-100 q-20 0 -20 -20 v-150 q0 -45 45 -70 z" fill="' . $liquid . '" opacity=".9"/>'
        . '<path d="M140 170 h120 v120 h-120 z" fill="#fff"/>'
        . svg_text_lines([mb_strtoupper($brand)], 200, 200, 18, $c1, '900')
        . svg_text_lines(svg_wrap($title, 13), 200, 228, 13, '#1f2933', '700', 16)
        . svg_text_lines([$size], 200, 278, 15, $c1, '800'),
        '#f0fdfa');
}

/** Box (tea, biscuits, detergent) */
function svg_box(string $brand, string $title, string $size, string $c1, string $c2): string
{
    return svg_wrap_doc(
        '<polygon points="110,100 250,80 300,110 160,130" fill="' . $c2 . '"/>'
        . '<polygon points="110,100 160,130 160,330 110,305" fill="' . $c2 . '" opacity=".85"/>'
        . '<polygon points="160,130 300,110 300,310 160,330" fill="' . $c1 . '"/>'
        . '<rect x="175" y="160" width="110" height="110" rx="10" fill="#fff" transform="skewY(-8) translate(0,40)"/>'
        . svg_text_lines([mb_strtoupper($brand)], 230, 205, 18, $c1, '900')
        . svg_text_lines(svg_wrap($title, 11), 230, 230, 12, '#1f2933', '700', 15)
        . svg_text_lines([$size], 230, 283, 14, '#ffffff', '800'),
        '#f8fafc');
}

/** Pouch / packet (spices, pulses, snacks) */
function svg_packet(string $brand, string $title, string $size, string $c1, string $c2): string
{
    return svg_wrap_doc(
        '<path d="M120 60 h160 l10 20 v230 q0 20 -20 20 h-140 q-20 0 -20 -20 v-230 z" fill="' . $c1 . '"/>'
        . '<path d="M120 60 h160 l10 20 h-180 z" fill="#000" opacity=".15"/>'
        . '<circle cx="200" cy="190" r="62" fill="#fff"/>'
        . svg_text_lines([mb_strtoupper($brand)], 200, 182, 18, $c2, '900')
        . svg_text_lines(svg_wrap($title, 12), 200, 204, 12, '#1f2933', '700', 15)
        . svg_text_lines([$size], 200, 300, 16, '#ffffff', '800'),
        '#fff7ed');
}

/** Bar soap / tin */
function svg_soap(string $brand, string $title, string $c1): string
{
    return svg_wrap_doc(
        '<rect x="90" y="150" width="220" height="130" rx="40" fill="' . $c1 . '"/>'
        . '<rect x="90" y="140" width="220" height="130" rx="40" fill="' . $c1 . '" opacity=".75"/>'
        . '<ellipse cx="200" cy="205" rx="80" ry="40" fill="#fff" opacity=".9"/>'
        . svg_text_lines([mb_strtoupper($brand)], 200, 205, 22, $c1, '900')
        . svg_text_lines([$title], 200, 228, 12, '#1f2933', '700')
        . '<circle cx="290" cy="110" r="14" fill="#bae6fd"/><circle cx="315" cy="85" r="9" fill="#bae6fd"/><circle cx="270" cy="80" r="6" fill="#bae6fd"/>',
        '#f0f9ff');
}

/** Generic item (bags, geometry box, etc) */
function svg_item(string $title, string $sub, string $c1, string $icon = 'bag'): string
{
    $shape = $icon === 'bag'
        ? '<rect x="120" y="110" width="160" height="200" rx="26" fill="' . $c1 . '"/><path d="M160 110 v-20 a40 40 0 0 1 80 0 v20" fill="none" stroke="' . $c1 . '" stroke-width="12"/><rect x="145" y="190" width="110" height="70" rx="12" fill="#fff" opacity=".9"/>'
        : '<rect x="95" y="130" width="210" height="140" rx="14" fill="' . $c1 . '"/><rect x="110" y="145" width="180" height="110" rx="8" fill="#fff" opacity=".9"/>';
    $ty = $icon === 'bag' ? 218 : 196;
    return svg_wrap_doc($shape . svg_text_lines(svg_wrap(mb_strtoupper($title), 12), 200, $ty, 14, '#1f2933', '800', 17)
        . svg_text_lines([$sub], 200, $icon === 'bag' ? 250 : 240, 11, '#475569', '600'), '#f8fafc');
}

/* ---------------- Category illustrations ---------------- */
function svg_cat_books(): string
{
    $s = '';
    $cols = [['#ef4444', 250], ['#3b82f6', 220], ['#f59e0b', 190], ['#10b981', 160], ['#8b5cf6', 130]];
    foreach ($cols as $i => [$c, $y]) {
        $x = 110 + ($i % 2) * 14;
        $s .= '<rect x="' . $x . '" y="' . $y . '" width="180" height="30" rx="5" fill="' . $c . '"/><rect x="' . ($x + 150) . '" y="' . ($y + 4) . '" width="26" height="22" rx="2" fill="#fff" opacity=".85"/>';
    }
    return svg_wrap_doc($s . '<rect x="160" y="70" width="70" height="60" rx="6" fill="#0f766e"/><rect x="170" y="80" width="50" height="6" rx="3" fill="#fff"/>', '#ffffff');
}

function svg_cat_stationery(): string
{
    return svg_wrap_doc(svg_pens_inner() . '<rect x="120" y="200" width="160" height="120" rx="16" fill="#22c55e"/><rect x="120" y="200" width="160" height="22" rx="10" fill="#16a34a"/>'
        . '<rect x="70" y="300" width="260" height="18" rx="4" fill="#facc15"/><rect x="80" y="304" width="2" height="10" fill="#a16207"/><rect x="100" y="304" width="2" height="10" fill="#a16207"/><rect x="120" y="304" width="2" height="10" fill="#a16207"/>', '#ffffff');
}

function svg_pens_inner(): string
{
    $s = '';
    foreach (['#ef4444', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'] as $i => $c) {
        $x = 135 + $i * 22;
        $s .= '<rect x="' . $x . '" y="' . (70 + ($i % 3) * 14) . '" width="14" height="150" rx="3" fill="' . $c . '"/>';
    }
    return $s;
}

function svg_cat_grocery(): string
{
    return svg_wrap_doc(
        '<rect x="90" y="150" width="70" height="170" rx="10" fill="#facc15"/><rect x="100" y="200" width="50" height="40" rx="6" fill="#fff"/>'
        . '<path d="M175 140 Q215 125 255 140 L265 320 Q215 335 165 320 Z" fill="#f97316"/><rect x="185" y="200" width="60" height="50" rx="8" fill="#fff"/>'
        . '<rect x="270" y="190" width="60" height="130" rx="8" fill="#22c55e"/><rect x="278" y="220" width="44" height="40" rx="5" fill="#fff"/>'
        . '<circle cx="130" cy="125" r="18" fill="#ef4444"/><path d="M130 107 q6 -12 14 -10" stroke="#16a34a" stroke-width="4" fill="none"/>', '#ffffff');
}

function svg_cat_general(): string
{
    return svg_wrap_doc(
        '<rect x="80" y="170" width="70" height="150" rx="12" fill="#3b82f6"/><rect x="88" y="150" width="54" height="26" rx="6" fill="#1d4ed8"/>'
        . '<rect x="165" y="120" width="80" height="200" rx="10" fill="#ef4444"/><rect x="175" y="180" width="60" height="60" rx="8" fill="#fff"/>'
        . '<rect x="260" y="200" width="70" height="120" rx="10" fill="#10b981"/><rect x="268" y="180" width="54" height="24" rx="6" fill="#047857"/>', '#ffffff');
}

function svg_cat_clean(): string
{
    return svg_wrap_doc('<path d="M150 90 h50 v40 q40 20 40 60 v120 q0 15 -15 15 h-100 q-15 0 -15 -15 v-120 q0 -40 40 -60 z" fill="#2563eb"/>'
        . '<rect x="140" y="190" width="90" height="70" rx="8" fill="#fff"/><rect x="250" y="210" width="80" height="110" rx="10" fill="#f97316"/><rect x="258" y="235" width="64" height="40" rx="6" fill="#fff"/>'
        . '<circle cx="300" cy="130" r="16" fill="#bae6fd"/><circle cx="325" cy="100" r="10" fill="#bae6fd"/>', '#ffffff');
}

function svg_cat_register(): string
{
    return svg_wrap_doc('<rect x="140" y="70" width="150" height="230" rx="8" fill="#1d4ed8" transform="rotate(-8 215 185)"/>'
        . '<rect x="110" y="80" width="150" height="230" rx="8" fill="#2563eb"/><rect x="110" y="80" width="16" height="230" fill="#1e3a8a"/>'
        . '<rect x="140" y="130" width="100" height="40" rx="6" fill="#fff"/><text x="190" y="157" font-family="Arial" font-size="18" font-weight="800" fill="#1e3a8a" text-anchor="middle">SOLO</text>', '#ffffff');
}
