<?php
/**
 * THE 10 PROPOSAL DESIGNS (templates).
 *
 * For each template you will find:
 *   - its name, fonts and default colours            (pm_templates)
 *   - the size of the content area on inner pages    ('body')
 *   - its cover page HTML                             (tpl_cover_1 ... tpl_cover_10)
 *   - its inner page decoration (bars, frames...)     (tpl_deco)
 *
 * The look (colours, positions) is in assets/css/templates.css.
 * Colours are never written in the HTML: they come from CSS variables
 * like var(--primary), so every proposal can have its own colours.
 *
 * An A4 sheet is 793 × 1122 pixels.
 */

const PM_SHEET_W = 793;
const PM_SHEET_H = 1122;

function pm_templates()
{
    static $t = null;
    if ($t !== null) return $t;
    $t = [
        1 => [
            'name' => 'Modern Corporate', 'slug' => 'modern-corporate',
            'desc' => 'Clean, professional white layout with large typography and geometric blocks.',
            'suitable' => 'Web development, SEO, business clients',
            'fonts' => ['heading' => 'Montserrat', 'body' => 'Inter'],
            'colors' => ['primary' => '#015957', 'secondary' => '#0f172a', 'accent' => '#f59e0b', 'text' => '#374151', 'heading' => '#0f172a', 'background' => '#ffffff', 'card' => '#f3f6f8', 'border' => '#e5e7eb', 'button' => '#015957'],
            'body' => ['x' => 64, 'top' => 122, 'h' => 900, 'header' => 44, 'footer' => 1060],
        ],
        2 => [
            'name' => 'Dark Premium', 'slug' => 'dark-premium',
            'desc' => 'Luxurious dark canvas, serif headlines and gold accents with elegant spacing.',
            'suitable' => 'Branding, high-value websites, digital marketing',
            'fonts' => ['heading' => 'Playfair Display', 'body' => 'Inter'],
            'colors' => ['primary' => '#d4af37', 'secondary' => '#0b0f19', 'accent' => '#d4af37', 'text' => '#c9ccd3', 'heading' => '#ffffff', 'background' => '#0b0f19', 'card' => '#141a28', 'border' => '#2a3142', 'button' => '#d4af37'],
            'body' => ['x' => 72, 'top' => 128, 'h' => 884, 'header' => 52, 'footer' => 1056],
        ],
        3 => [
            'name' => 'Creative Agency', 'slug' => 'creative-agency',
            'desc' => 'Big imagery, playful shapes, bold type and asymmetrical composition.',
            'suitable' => 'Graphics, video, branding, social media',
            'fonts' => ['heading' => 'Space Grotesk', 'body' => 'DM Sans'],
            'colors' => ['primary' => '#6d28d9', 'secondary' => '#111827', 'accent' => '#fb7185', 'text' => '#374151', 'heading' => '#111827', 'background' => '#ffffff', 'card' => '#f5f3ff', 'border' => '#e9e5f5', 'button' => '#6d28d9'],
            'body' => ['x' => 64, 'top' => 130, 'h' => 892, 'header' => 46, 'footer' => 1054],
        ],
        4 => [
            'name' => 'Minimal White', 'slug' => 'minimal-white',
            'desc' => 'Extremely clean with generous whitespace, black type and a single accent.',
            'suitable' => 'Corporate clients, professional services',
            'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            'colors' => ['primary' => '#111111', 'secondary' => '#111111', 'accent' => '#ef4444', 'text' => '#3f3f46', 'heading' => '#09090b', 'background' => '#ffffff', 'card' => '#fafafa', 'border' => '#e4e4e7', 'button' => '#111111'],
            'body' => ['x' => 80, 'top' => 120, 'h' => 900, 'header' => 52, 'footer' => 1062],
        ],
        5 => [
            'name' => 'Tech / Digital', 'slug' => 'tech-digital',
            'desc' => 'Technology aesthetic with gradient accents, cards, grids and futuristic details.',
            'suitable' => 'Software, mobile apps, SaaS, web development',
            'fonts' => ['heading' => 'Space Grotesk', 'body' => 'Inter'],
            'colors' => ['primary' => '#2563eb', 'secondary' => '#0b1120', 'accent' => '#06b6d4', 'text' => '#334155', 'heading' => '#0b1120', 'background' => '#ffffff', 'card' => '#f1f5fb', 'border' => '#dbe4f0', 'button' => '#2563eb'],
            'body' => ['x' => 64, 'top' => 128, 'h' => 892, 'header' => 48, 'footer' => 1058],
        ],
        6 => [
            'name' => 'Elegant Business', 'slug' => 'elegant-business',
            'desc' => 'Sophisticated editorial typography, thin rules and refined spacing.',
            'suitable' => 'Consultants, agencies, premium clients',
            'fonts' => ['heading' => 'Cormorant Garamond', 'body' => 'Lora'],
            'colors' => ['primary' => '#7c5c3b', 'secondary' => '#2b2118', 'accent' => '#b08d57', 'text' => '#4a4038', 'heading' => '#2b2118', 'background' => '#fbf8f3', 'card' => '#f4eee4', 'border' => '#d9cbb5', 'button' => '#7c5c3b'],
            'body' => ['x' => 84, 'top' => 140, 'h' => 862, 'header' => 70, 'footer' => 1040],
        ],
        7 => [
            'name' => 'Bold Creative', 'slug' => 'bold-creative',
            'desc' => 'Huge typography, strong colour blocks and dynamic, energetic sections.',
            'suitable' => 'Creative agencies, marketing, graphics',
            'fonts' => ['heading' => 'Montserrat', 'body' => 'Poppins'],
            'colors' => ['primary' => '#e11d48', 'secondary' => '#111111', 'accent' => '#facc15', 'text' => '#27272a', 'heading' => '#111111', 'background' => '#ffffff', 'card' => '#fff1f2', 'border' => '#f1d5da', 'button' => '#e11d48'],
            'body' => ['x' => 60, 'top' => 112, 'h' => 900, 'header' => 46, 'footer' => 1076],
        ],
        8 => [
            'name' => 'Startup', 'slug' => 'startup',
            'desc' => 'Friendly and modern with rounded cards, soft blobs and gradient accents.',
            'suitable' => 'Startups, SaaS, mobile apps',
            'fonts' => ['heading' => 'Poppins', 'body' => 'DM Sans'],
            'colors' => ['primary' => '#7c3aed', 'secondary' => '#1e1b4b', 'accent' => '#14b8a6', 'text' => '#475569', 'heading' => '#1e1b4b', 'background' => '#ffffff', 'card' => '#f6f3ff', 'border' => '#e7e1fb', 'button' => '#7c3aed'],
            'body' => ['x' => 60, 'top' => 128, 'h' => 892, 'header' => 40, 'footer' => 1052],
        ],
        9 => [
            'name' => 'Professional Blue', 'slug' => 'professional-blue',
            'desc' => 'Corporate blue, structured sections, strong tables and data presentation.',
            'suitable' => 'Enterprise, SEO, web development, business',
            'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            'colors' => ['primary' => '#1d4ed8', 'secondary' => '#0b1f44', 'accent' => '#38bdf8', 'text' => '#334155', 'heading' => '#0b1f44', 'background' => '#ffffff', 'card' => '#f0f5ff', 'border' => '#d6e0f5', 'button' => '#1d4ed8'],
            'body' => ['x' => 60, 'top' => 112, 'h' => 908, 'header' => 26, 'footer' => 1062],
        ],
        10 => [
            'name' => 'Luxury Minimal', 'slug' => 'luxury-minimal',
            'desc' => 'High-end, airy presentation with elegant serif type and hairline borders.',
            'suitable' => 'Premium clients, branding, web design, retainers',
            'fonts' => ['heading' => 'Cormorant Garamond', 'body' => 'Montserrat'],
            'colors' => ['primary' => '#1c1917', 'secondary' => '#1c1917', 'accent' => '#a8865b', 'text' => '#57534e', 'heading' => '#1c1917', 'background' => '#faf9f6', 'card' => '#f3f1ec', 'border' => '#dcd6cc', 'button' => '#1c1917'],
            'body' => ['x' => 88, 'top' => 136, 'h' => 864, 'header' => 66, 'footer' => 1036],
        ],
    ];
    return $t;
}

function pm_template($id)
{
    $all = pm_templates();
    return ($all[$id] ?? $all[1]) + ['id' => isset($all[$id]) ? $id : 1];
}

function color_keys()
{
    return [
        'primary' => 'Primary', 'secondary' => 'Secondary', 'accent' => 'Accent', 'text' => 'Text',
        'heading' => 'Heading', 'background' => 'Background', 'card' => 'Card Background', 'border' => 'Border', 'button' => 'Button',
    ];
}

/* ===================================================================
 *  COVER LAYOUTS
 *  $x = prepared cover context (all strings already escaped)
 * ================================================================= */

function tpl_cover($doc, $x)
{
    $id = $doc['tpl']['id'];
    if ($x['layout'] === 'centered') return tpl_cover_centered($doc, $x);
    if ($x['layout'] === 'split') return tpl_cover_split($doc, $x);
    // every template has its own cover function: tpl_cover_1() ... tpl_cover_10()
    $function = 'tpl_cover_' . $id;
    if (function_exists($function)) {
        return $function($doc, $x);
    }
    return tpl_cover_1($doc, $x);
}

function cover_meta_table($x, $class = '', $cols = 0, $skipClient = false)
{
    $items = $skipClient && $x['client'] !== '' ? $x['meta_rest'] : $x['meta'];
    if (!$items) return '';
    $cols = $cols ?: count($items);
    $html = '<table class="pm-cmeta ' . $class . '"><tr>';
    $i = 0;
    foreach ($items as $label => $val) {
        if ($i && $i % $cols === 0) $html .= '</tr><tr>';
        $html .= '<td><div class="pm-cmeta-l">' . $label . '</div><div class="pm-cmeta-v">' . $val . '</div></td>';
        $i++;
    }
    $html .= '</tr></table>';
    return $html;
}

function cover_title_block($x, $prefix)
{
    $h = '';
    if ($x['eyebrow'] !== '') $h .= '<div class="' . $prefix . '-eyebrow">' . $x['eyebrow'] . '</div>';
    $h .= '<div class="' . $prefix . '-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>';
    return $h;
}

/* 01 — Modern Corporate */
function tpl_cover_1($doc, $x)
{
    $h = '';
    $img = $x['image'] ? doc_cover_image($doc, $x['image_raw'], 330, 700) : '';
    $h .= '<div class="c1-panel">' . ($img ? '<img src="' . $img . '" width="330" height="700" alt=""><div class="c1-panel-tint"></div>' : '') . '</div>';
    if ($x['shapes']) {
        $h .= '<div class="c1-sq"></div><div class="c1-sq2"></div><div class="c1-line"></div>';
    }
    $h .= '<div class="c1-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c1-head">' . cover_title_block($x, 'c1') . '<div class="c1-bar"></div>'
        . ($x['subtitle'] !== '' ? '<div class="c1-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    if ($x['client'] !== '') {
        $h .= '<div class="c1-client"><div class="c1-client-l">' . $x['for_label'] . '</div><div class="c1-client-v">' . $x['client'] . '</div>'
            . ($x['client_name'] !== '' && $x['client_name'] !== $x['client'] ? '<div class="c1-client-n">' . $x['client_name'] . '</div>' : '') . '</div>';
    }
    $h .= '<div class="c1-meta">' . cover_meta_table($x, '', 0, true) . '</div>';
    $h .= '<div class="c1-foot"></div>';
    return $h;
}

/* 02 — Dark Premium */
function tpl_cover_2($doc, $x)
{
    $h = '';
    if ($x['image']) {
        $h .= '<div class="c2-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 793, 520) . '" width="793" height="520" alt=""></div><div class="c2-img-tint"></div><div class="c2-img-fade"></div>';
    } elseif ($x['shapes']) {
        $h .= '<div class="c2-ring"></div><div class="c2-ring2"></div>';
    }
    $h .= '<div class="c2-frame"></div>';
    $h .= '<div class="c2-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c2-head">' . ($x['eyebrow'] !== '' ? '<div class="c2-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="c2-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div><div class="c2-rule"></div>'
        . ($x['subtitle'] !== '' ? '<div class="c2-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    if ($x['client'] !== '') {
        $h .= '<div class="c2-client"><div class="c2-client-l">' . $x['for_label'] . '</div><div class="c2-client-v">' . $x['client'] . '</div></div>';
    }
    $h .= '<div class="c2-meta">' . cover_meta_table($x, '', 0, true) . '</div>';
    return $h;
}

/* 03 — Creative Agency */
function tpl_cover_3($doc, $x)
{
    $h = '<div class="c3-hero">';
    if ($x['image']) {
        $h .= '<img src="' . doc_cover_image($doc, $x['image_raw'], 793, 560) . '" width="793" height="560" alt="">';
    } else {
        $h .= '<div class="c3-hero-c1"></div><div class="c3-hero-c2"></div><div class="c3-hero-c3"></div>';
    }
    $h .= '</div>';
    if ($x['shapes']) {
        $h .= '<div class="c3-blob"><div class="c3-blob-t">' . $x['year'] . '</div></div><div class="c3-dot1"></div><div class="c3-dot2"></div>';
    }
    $h .= '<div class="c3-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c3-head">' . ($x['eyebrow'] !== '' ? '<span class="c3-tag">' . $x['eyebrow'] . '</span>' : '')
        . '<div class="c3-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>'
        . ($x['subtitle'] !== '' ? '<div class="c3-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    $h .= '<div class="c3-meta">' . cover_meta_table($x) . '</div>';
    $h .= '<div class="c3-stripe"></div><div class="c3-stripe2"></div>';
    return $h;
}

/* 04 — Minimal White */
function tpl_cover_4($doc, $x)
{
    $h = '<div class="c4-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c4-num">' . $x['number'] . '</div>';
    if ($x['image']) {
        $h .= '<div class="c4-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 633, 250) . '" width="633" height="250" alt=""></div>';
    }
    $h .= '<div class="c4-head' . ($x['image'] ? ' c4-head-img' : '') . '">'
        . ($x['eyebrow'] !== '' ? '<div class="c4-eyebrow">' . ($x['shapes'] ? '<span class="c4-dot"></span>' : '') . $x['eyebrow'] . '</div>' : '')
        . '<div class="c4-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>'
        . ($x['subtitle'] !== '' ? '<div class="c4-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    $h .= '<div class="c4-meta">' . cover_meta_table($x) . '</div>';
    return $h;
}

/* 05 — Tech / Digital */
function tpl_cover_5($doc, $x)
{
    $h = '';
    if ($x['shapes']) {
        $line = doc_mix($doc, 'secondary', 0.07);
        for ($i = 1; $i <= 7; $i++) {
            $h .= '<div class="c5-vl" style="left:' . ($i * 99) . 'px;background:' . $line . '"></div>';
        }
        for ($i = 1; $i <= 10; $i++) {
            $h .= '<div class="c5-hl" style="top:' . ($i * 110) . 'px;background:' . $line . '"></div>';
        }
        $h .= '<div class="c5-glow1"></div><div class="c5-glow2"></div>';
    }
    $h .= '<div class="c5-top"><table><tr><td class="c5-logo">' . $x['logo'] . '</td><td class="c5-code">// ' . $x['number'] . '</td></tr></table></div>';
    $h .= '<div class="c5-head">' . ($x['eyebrow'] !== '' ? '<span class="c5-tag">&lt;/&gt; ' . $x['eyebrow'] . '</span>' : '')
        . '<div class="c5-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>'
        . ($x['subtitle'] !== '' ? '<div class="c5-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    if ($x['image']) {
        $h .= '<div class="c5-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 665, 230) . '" width="665" height="230" alt=""></div>';
    }
    $h .= '<div class="c5-meta">' . cover_meta_table($x, '', min(4, max(1, count($x['meta'])))) . '</div>';
    $h .= '<div class="c5-bar"></div>';
    return $h;
}

/* 06 — Elegant Business */
function tpl_cover_6($doc, $x)
{
    $h = '<div class="c6-frame"></div><div class="c6-frame2"></div>';
    $h .= '<div class="c6-logo">' . $x['logo'] . '</div>';
    if ($x['has_logo']) $h .= '<div class="c6-company">' . $x['company'] . '</div>';
    if ($x['image']) {
        $h .= '<div class="c6-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 230, 230) . '" width="230" height="230" alt=""></div>';
    } elseif ($x['shapes']) {
        $h .= '<div class="c6-orn"><span class="c6-orn-l"></span><span class="c6-orn-d"></span><span class="c6-orn-l"></span></div>';
    }
    $h .= '<div class="c6-head' . ($x['image'] ? ' c6-head-img' : '') . '">'
        . ($x['eyebrow'] !== '' ? '<div class="c6-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="c6-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div><div class="c6-rule"></div>'
        . ($x['subtitle'] !== '' ? '<div class="c6-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    if ($x['client'] !== '') {
        $h .= '<div class="c6-client"><div class="c6-client-l">' . $x['for_label'] . '</div><div class="c6-client-v">' . $x['client'] . '</div></div>';
    }
    $h .= '<div class="c6-meta">' . implode(' &nbsp;&middot;&nbsp; ', array_filter([$x['date'], $x['number']])) . '</div>';
    return $h;
}

/* 07 — Bold Creative */
function tpl_cover_7($doc, $x)
{
    $h = '<div class="c7-top">';
    if ($x['shapes']) $h .= '<div class="c7-big">' . $x['year_short'] . '</div><div class="c7-sq"></div>';
    $h .= '<div class="c7-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c7-head">' . ($x['eyebrow'] !== '' ? '<div class="c7-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="c7-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div></div>';
    $h .= '</div>';
    $h .= '<div class="c7-band"><table><tr><td class="c7-band-a"><span class="c7-band-l">' . $x['for_label'] . '</span> ' . ($x['client'] !== '' ? $x['client'] : '&nbsp;') . '</td><td class="c7-band-b">' . $x['date'] . '</td></tr></table></div>';
    $h .= '<div class="c7-low' . ($x['image'] ? ' c7-low-img' : '') . '">' . ($x['subtitle'] !== '' ? '<div class="c7-sub">' . $x['subtitle'] . '</div>' : '')
        . cover_meta_table($x, '', 2, true) . '</div>';
    if ($x['image']) {
        $h .= '<div class="c7-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 300, 300) . '" width="300" height="300" alt=""></div>';
    }
    $h .= '<div class="c7-foot"></div>';
    return $h;
}

/* 08 — Startup */
function tpl_cover_8($doc, $x)
{
    $h = '';
    if ($x['shapes']) $h .= '<div class="c8-b1"></div><div class="c8-b2"></div><div class="c8-b3"></div>';
    $h .= '<div class="c8-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="c8-card">' . ($x['eyebrow'] !== '' ? '<span class="c8-pill">' . $x['eyebrow'] . '</span>' : '')
        . '<div class="c8-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>'
        . ($x['subtitle'] !== '' ? '<div class="c8-sub">' . $x['subtitle'] . '</div>' : '');
    if ($x['image']) {
        $h .= '<div class="c8-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 577, 210) . '" width="577" height="210" alt=""></div>';
    }
    $h .= '</div>';
    $h .= '<div class="c8-meta">' . cover_meta_table($x, '', min(4, max(1, count($x['meta'])))) . '</div>';
    return $h;
}

/* 09 — Professional Blue */
function tpl_cover_9($doc, $x)
{
    $h = '<div class="c9-band"><table><tr><td class="c9-logo">' . $x['logo'] . '</td><td class="c9-contact">' . implode('<br>', array_filter([$x['website'], $x['email']])) . '</td></tr></table></div>';
    $h .= '<div class="c9-side"><div class="c9-side-h">Proposal Details</div><table class="c9-dl">';
    foreach ($x['meta'] as $l => $v) {
        $h .= '<tr><td><div class="c9-dl-l">' . $l . '</div><div class="c9-dl-v">' . $v . '</div></td></tr>';
    }
    $h .= '</table>';
    if ($x['total'] !== '') {
        $h .= '<div class="c9-total"><div class="c9-total-l">Total Investment</div><div class="c9-total-v">' . $x['total'] . '</div></div>';
    }
    $h .= '</div>';
    $h .= '<div class="c9-main">' . ($x['eyebrow'] !== '' ? '<div class="c9-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="c9-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div><div class="c9-rule"></div>'
        . ($x['subtitle'] !== '' ? '<div class="c9-sub">' . $x['subtitle'] . '</div>' : '');
    if ($x['image']) {
        $h .= '<div class="c9-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 420, 220) . '" width="420" height="220" alt=""></div>';
    }
    if ($x['toc']) {
        $h .= '<div class="c9-toc"><div class="c9-toc-h">Contents</div><table>';
        foreach (array_slice($x['toc'], 0, $x['image'] ? 7 : 12) as $i => $t) {
            $h .= '<tr><td class="c9-toc-n">' . sprintf('%02d', $i + 1) . '</td><td class="c9-toc-t">' . $t['title'] . '</td><td class="c9-toc-p">' . $t['page'] . '</td></tr>';
        }
        $h .= '</table></div>';
    }
    $h .= '</div><div class="c9-foot"></div>';
    return $h;
}

/* 10 — Luxury Minimal */
function tpl_cover_10($doc, $x)
{
    $h = '<div class="c10-frame"></div>';
    $h .= '<div class="c10-logo">' . $x['logo'] . '</div>';
    if ($x['image']) {
        $h .= '<div class="c10-img"><img src="' . doc_cover_image($doc, $x['image_raw'], 300, 360) . '" width="300" height="360" alt=""></div>';
    }
    $h .= '<div class="c10-head' . ($x['image'] ? ' c10-head-img' : '') . '">'
        . ($x['eyebrow'] !== '' ? '<div class="c10-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="c10-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div>'
        . ($x['shapes'] ? '<div class="c10-rule"></div>' : '')
        . ($x['subtitle'] !== '' ? '<div class="c10-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    $h .= '<div class="c10-meta">' . cover_meta_table($x) . '</div>';
    return $h;
}

/* Generic alternative layouts — still styled through each template's variables/fonts */
function tpl_cover_centered($doc, $x)
{
    $h = '';
    if ($x['image']) {
        $h .= '<div class="cx-bgimg"><img src="' . doc_cover_image($doc, $x['image_raw'], 793, 1122) . '" width="793" height="1122" alt=""></div><div class="cx-tint"></div>';
    } elseif ($x['shapes']) {
        $h .= '<div class="cx-c1"></div><div class="cx-c2"></div>';
    }
    $h .= '<div class="cx-center' . ($x['image'] ? ' cx-on-img' : '') . '"><div class="cx-logo">' . $x['logo'] . '</div>'
        . ($x['eyebrow'] !== '' ? '<div class="cx-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="cx-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div><div class="cx-rule"></div>'
        . ($x['subtitle'] !== '' ? '<div class="cx-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    $h .= '<div class="cx-meta' . ($x['image'] ? ' cx-on-img' : '') . '">' . cover_meta_table($x) . '</div>';
    return $h;
}

function tpl_cover_split($doc, $x)
{
    $h = '<div class="cs-side">';
    if ($x['image']) {
        $h .= '<img src="' . doc_cover_image($doc, $x['image_raw'], 340, 1122) . '" width="340" height="1122" alt=""><div class="cs-tint"></div>';
    } elseif ($x['shapes']) {
        $h .= '<div class="cs-c1"></div><div class="cs-c2"></div>';
    }
    $h .= '</div>';
    $h .= '<div class="cs-logo">' . $x['logo'] . '</div>';
    $h .= '<div class="cs-head">' . ($x['eyebrow'] !== '' ? '<div class="cs-eyebrow">' . $x['eyebrow'] . '</div>' : '')
        . '<div class="cs-title" style="font-size:' . $x['title_size'] . 'px">' . $x['title'] . '</div><div class="cs-rule"></div>'
        . ($x['subtitle'] !== '' ? '<div class="cs-sub">' . $x['subtitle'] . '</div>' : '') . '</div>';
    $h .= '<div class="cs-meta">' . cover_meta_table($x, '', 2) . '</div>';
    return $h;
}

/* ===================================================================
 *  INTERNAL PAGE DECORATION (behind header/body/footer)
 * ================================================================= */
function tpl_deco($doc)
{
    switch ($doc['tpl']['id']) {
        case 1: return '<div class="d1-mark"></div><div class="d1-corner"></div>';
        case 2: return '<div class="d2-frame"></div>';
        case 3: return '<div class="d3-c1"></div><div class="d3-c2"></div><div class="d3-strip"></div>';
        case 4: return '';
        case 5: return '<div class="d5-bar"></div><div class="d5-g1"></div><div class="d5-g2"></div><div class="d5-g3"></div>';
        case 6: return '<div class="d6-frame"></div><div class="d6-frame2"></div>';
        case 7: return '<div class="d7-band"></div><div class="d7-acc"></div><div class="d7-foot"></div>';
        case 8: return '<div class="d8-b1"></div><div class="d8-b2"></div>';
        case 9: return '<div class="d9-band"></div><div class="d9-strip"></div>';
        case 10: return '<div class="d10-frame"></div>';
    }
    return '';
}
