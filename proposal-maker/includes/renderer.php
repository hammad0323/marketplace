<?php
/**
 * ============================================================
 *  RENDERER — turns a proposal into HTML
 * ============================================================
 *
 * The same code makes the HTML for:
 *   - the editor        ($mode = 'editor')  one tall sheet per page, clickable blocks
 *   - the preview/link  ($mode = 'screen')  real A4 sheets
 *   - the PDF           ($mode = 'pdf')     real A4 sheets, local file paths for Dompdf
 *
 * How it works, in simple words:
 *   1. doc_create() puts everything we need into ONE array called $doc
 *      (proposal, pages, company, colours, template, settings, variables...).
 *   2. Every other function receives that $doc array and reads from it.
 *   3. render_sheets() returns a list of A4 sheets (HTML strings).
 *
 * Page breaks: the editor (JavaScript) measures every block and saves where
 * a new A4 sheet must start in  settings['breaks'].  We use those breaks here
 * so the preview and the PDF have exactly the same pages.
 */

/**
 * Build the $doc array.
 *
 * @param array  $proposal  proposal row (colors/settings may be arrays or JSON)
 * @param array  $pages     list of pages, each with 'blocks'
 * @param array  $company   company_settings row
 * @param string $mode      'editor', 'screen' or 'pdf'
 */
function doc_create($proposal, $pages, $company, $mode = 'screen')
{
    $tpl = pm_template((int) ($proposal['template_id'] ?? 1));

    $doc = array();
    $doc['p']        = $proposal;
    $doc['pages']    = $pages;
    $doc['company']  = $company;
    $doc['mode']     = $mode;
    $doc['tpl']      = $tpl;
    $doc['colors']   = pm_resolve_colors($proposal['colors'] ?? array(), $tpl);
    $doc['settings'] = pm_merge_settings($proposal['settings'] ?? array());
    $doc['symbol']   = (string) ($proposal['currency_symbol'] ?? '$');
    $doc['body_w']   = PM_SHEET_W - 2 * $tpl['body']['x'];   // width of the content area in px

    // fonts: proposal choice, or the template default
    $doc['font_heading'] = valid_font($proposal['font_heading'] ?? '', $tpl['fonts']['heading']);
    $doc['font_body']    = valid_font($proposal['font_body'] ?? '', $tpl['fonts']['body']);

    // the client's acceptance (used by the Signature block)
    $doc['acceptance'] = null;
    if (!empty($proposal['id'])) {
        $doc['acceptance'] = db_row("SELECT * FROM proposal_responses WHERE proposal_id = ? AND response = 'accepted' ORDER BY id DESC LIMIT 1", [(int) $proposal['id']]);
    }

    // the {{variables}} — built last because they need the date format
    $doc['vars'] = doc_build_vars($doc);
    return $doc;
}

/* ---------------------------------------------------------------
 *  Colours
 * ------------------------------------------------------------- */

/** A colour from the proposal palette, e.g. doc_color($doc, 'primary'). */
function doc_color($doc, $key)
{
    return isset($doc['colors'][$key]) ? $doc['colors'][$key] : '#000000';
}

/** White or black — whichever is readable on top of that palette colour. */
function doc_on($doc, $key)
{
    return color_is_dark(doc_color($doc, $key)) ? '#ffffff' : '#111111';
}

/** Lighter (amount > 0) or darker (amount < 0) version of a palette colour. */
function doc_mix($doc, $key, $amount)
{
    return color_mix(doc_color($doc, $key), $amount);
}

/** A palette key, the word 'white', or a #hex value → hex colour. */
function doc_ref($doc, $key)
{
    if ($key === 'white') return '#ffffff';
    if (is_hex_color($key)) return $key;
    if (isset($doc['colors'][$key])) return $doc['colors'][$key];
    return doc_color($doc, 'primary');
}

/** A soft background tint of a colour (darker on dark templates). */
function doc_soft($doc, $key)
{
    $c = doc_ref($doc, $key);
    if (color_is_dark(doc_color($doc, 'background'))) {
        return color_mix($c, -0.7);
    }
    return color_mix($c, 0.88);
}

/** All CSS variables for this proposal, as one style="" string. */
function doc_css_vars($doc)
{
    $c = $doc['colors'];
    $dark = color_is_dark($c['background']);
    $b = $doc['tpl']['body'];

    $vars = array(
        '--primary'      => $c['primary'],
        '--secondary'    => $c['secondary'],
        '--accent'       => $c['accent'],
        '--text'         => $c['text'],
        '--heading'      => $c['heading'],
        '--bg'           => $c['background'],
        '--card'         => $c['card'],
        '--border'       => $c['border'],
        '--button'       => $c['button'],
        '--on-primary'   => doc_on($doc, 'primary'),
        '--on-secondary' => doc_on($doc, 'secondary'),
        '--on-accent'    => doc_on($doc, 'accent'),
        '--on-button'    => doc_on($doc, 'button'),
        '--primary-soft' => $dark ? color_mix($c['primary'], -0.72) : color_mix($c['primary'], 0.9),
        '--accent-soft'  => $dark ? color_mix($c['accent'], -0.72) : color_mix($c['accent'], 0.86),
        '--primary-dark' => color_mix($c['primary'], -0.25),
        '--secondary-2'  => color_mix($c['secondary'], 0.1),
        '--secondary-3'  => color_mix($c['secondary'], 0.22),
        '--muted'        => $dark ? color_mix($c['text'], -0.25) : color_mix($c['text'], 0.3),
        '--soft-line'    => $dark ? color_mix($c['background'], 0.12) : color_mix($c['border'], 0.4),
        '--font-heading' => font_stack($doc['font_heading']),
        '--font-body'    => font_stack($doc['font_body']),
        // size of the content area on internal pages (set by the template)
        '--body-x'       => $b['x'] . 'px',
        '--body-w'       => $doc['body_w'] . 'px',
        '--body-top'     => $b['top'] . 'px',
        '--body-h'       => $b['h'] . 'px',
        '--header-top'   => $b['header'] . 'px',
        '--footer-top'   => $b['footer'] . 'px',
    );

    $out = '';
    foreach ($vars as $name => $value) {
        $out .= $name . ':' . $value . ';';
    }
    return $out;
}

/* ---------------------------------------------------------------
 *  Images, logo, small helpers
 * ------------------------------------------------------------- */

/** Image path → URL (browser) or file path (PDF). */
function doc_media($doc, $path)
{
    return media_src($path, $doc['mode'] === 'pdf' ? 'pdf' : 'screen');
}

/** Cover image cropped to an exact size, ready for src="". */
function doc_cover_image($doc, $src, $w, $h)
{
    return e(doc_media($doc, image_cover($src, $w, $h)));
}

/** Company logo <img>, or the company name as text when there is no logo. */
function doc_logo($doc, $height = 48, $light = false)
{
    $co = $doc['company'];
    $file = $co['logo'] ?? '';
    if ($light && !empty($co['logo_light'])) {
        $file = $co['logo_light'];
    }
    $src = doc_media($doc, $file);
    if ($src !== '') {
        return '<img class="pm-logo" src="' . e($src) . '" style="height:' . $height . 'px;width:auto" height="' . $height . '" alt="' . e($co['company_name'] ?? '') . '">';
    }
    $size = max(14, (int) round($height * 0.5));
    $color = $light ? 'color:#ffffff' : '';
    return '<span class="pm-logo-text" style="font-size:' . $size . 'px;' . $color . '">' . e($co['company_name'] ?? 'Your Company') . '</span>';
}

/** Format a date with the proposal's date format. */
function doc_date($doc, $date)
{
    $format = $doc['settings']['date_format'] ?? 'F j, Y';
    return fmt_date($date, $format);
}

/** Make a button/link URL safe and complete (adds https://, handles #accept). */
function doc_link($doc, $url)
{
    $url = replace_vars($doc, $url, false);
    if ($url === '#accept') {
        if ($doc['mode'] === 'pdf') {
            return public_url($doc['p']['public_token'] ?? '');
        }
        return '#pm-respond';
    }
    if ($url !== '' && $url[0] !== '#' && !preg_match('#^(https?:|mailto:|tel:)#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return $url;
}

/* ---------------------------------------------------------------
 *  {{variables}}
 * ------------------------------------------------------------- */

/** List of all {{variables}} and their values for this proposal. */
function doc_build_vars($doc)
{
    $p = $doc['p'];
    $co = $doc['company'];
    $sym = $doc['symbol'];

    $clientCompany = $p['client_company'] ?? '';
    if ($clientCompany === '') {
        $clientCompany = $p['client_name'] ?? '';
    }
    $address = array();
    foreach (array('address', 'city', 'country') as $k) {
        if (!empty($co[$k])) $address[] = $co[$k];
    }

    return array(
        'client_name'      => $p['client_name'] ?? '',
        'client_company'   => $clientCompany,
        'client_email'     => $p['client_email'] ?? '',
        'client_phone'     => $p['client_phone'] ?? '',
        'client_address'   => $p['client_address'] ?? '',
        'client_website'   => $p['client_website'] ?? '',
        'proposal_title'   => $p['title'] ?? '',
        'project_name'     => $p['title'] ?? '',
        'proposal_number'  => $p['proposal_number'] ?? '',
        'proposal_date'    => doc_date($doc, $p['proposal_date'] ?? ''),
        'expiry_date'      => doc_date($doc, $p['expiry_date'] ?? ''),
        'company_name'     => $co['company_name'] ?? '',
        'company_tagline'  => $co['tagline'] ?? '',
        'company_email'    => $co['email'] ?? '',
        'company_phone'    => $co['phone'] ?? '',
        'company_whatsapp' => $co['whatsapp'] ?? '',
        'company_website'  => $co['website'] ?? '',
        'company_address'  => implode(', ', $address),
        'total_amount'     => money($p['total'] ?? 0, $sym),
        'subtotal'         => money($p['subtotal'] ?? 0, $sym),
        'currency'         => $p['currency'] ?? 'USD',
        'today'            => doc_date($doc, date('Y-m-d')),
        'proposal_link'    => !empty($p['public_token']) ? public_url($p['public_token']) : '',
    );
}

/**
 * Replace {{variable}} with its value.
 * $escape = true makes the value safe for HTML.
 */
function replace_vars($doc, $text, $escape = true)
{
    $text = (string) $text;
    if (strpos($text, '{{') === false) {
        return $text;
    }
    // find every {{ name }} and swap it
    preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $name = strtolower($m[1]);
        if (!array_key_exists($name, $doc['vars'])) {
            continue; // unknown variable: leave it as it is
        }
        $value = $doc['vars'][$name];
        if ($escape) {
            $value = e($value);
        }
        $text = str_replace($m[0], $value, $text);
    }
    return $text;
}

/* ---------------------------------------------------------------
 *  Blocks
 * ------------------------------------------------------------- */

/**
 * HTML for one block.
 * $slice = [from, to] keeps only part of a long block (when it is split over 2 sheets).
 */
function render_block($doc, $block, $slice = null)
{
    $type = $block['type'] ?? '';
    $function = 'pm_render_' . $type;          // e.g. pm_render_text
    if (!function_exists($function)) {
        return '';
    }

    // fill missing settings with the block's defaults
    $data = pm_block_defaults($type);
    if (isset($block['data']) && is_array($block['data'])) {
        $data = array_merge($data, $block['data']);
    }
    $block['data'] = $data;

    try {
        $html = $function($doc, $data, $block);
    } catch (Throwable $e) {
        $html = '<div class="pm-block pm-error">Block error: ' . e($e->getMessage()) . '</div>';
    }

    if ($slice) {
        $html = slice_flow($html, (int) $slice[0], $slice[1] === null ? null : (int) $slice[1]);
    }
    return replace_vars($doc, $html);
}

/**
 * Long blocks (text, lists, tables...) put their rows inside an element
 * with class "pm-flow". To split a block across pages we keep only the
 * children from $from up to (not including) $to.
 */
function slice_flow($html, $from, $to)
{
    if (!class_exists('DOMDocument')) {
        return $html;
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="pmroot">' . $html . '</div></body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $flow = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-flow ")]')->item(0);
    if (!$flow) {
        return $html;
    }

    // collect the element children (rows / paragraphs / list items)
    $children = array();
    foreach ($flow->childNodes as $node) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $children[] = $node;
        }
    }
    // remove the ones outside [from, to)
    foreach ($children as $i => $node) {
        $outside = $i < $from || ($to !== null && $i >= $to);
        if ($outside) {
            $flow->removeChild($node);
        }
    }
    // remove stray text when this is a continuation part
    if ($from > 0) {
        foreach (iterator_to_array($flow->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $flow->removeChild($node);
            }
        }
    }

    // a continuation does not repeat the block title ("pm-chrome-top")
    if ($from > 0) {
        foreach (iterator_to_array($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-chrome-top ")]')) as $node) {
            $node->parentNode->removeChild($node);
        }
        if (strtolower($flow->nodeName) === 'ol') {
            $flow->setAttribute('start', (string) ($from + 1)); // keep numbering
        }
    }
    // only the last part shows the block footer, e.g. pricing totals ("pm-chrome-bottom")
    if ($to !== null) {
        foreach (iterator_to_array($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-chrome-bottom ")]')) as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    $out = '';
    foreach ($dom->getElementById('pmroot')->childNodes as $node) {
        $out .= $dom->saveHTML($node);
    }
    return $out;
}

/* ---------------------------------------------------------------
 *  Header & footer of internal pages
 * ------------------------------------------------------------- */

function render_header($doc, $page)
{
    $s = $doc['settings']['header'];
    if (empty($s['enabled'])) return '';
    if (isset($page['settings']['show_header']) && !$page['settings']['show_header']) return '';

    // use the light logo on dark headers
    $light = in_array($doc['tpl']['id'], array(2, 9), true) || color_is_dark(doc_color($doc, 'background'));
    if (($s['style'] ?? 'template') === 'bar') {
        $light = color_is_dark(doc_color($doc, 'primary'));
    }

    $left = '';
    if (!empty($s['show_logo'])) {
        $left .= '<span class="pm-hlogo">' . doc_logo($doc, (int) ($s['logo_height'] ?? 26), $light) . '</span>';
    }
    if (($s['left'] ?? '') !== '') {
        $left .= '<span class="pm-htext">' . e($s['left']) . '</span>';
    }
    $right = ($s['right'] ?? '') !== '' ? e($s['right']) : '';
    $style = preg_replace('/[^a-z]/', '', $s['style'] ?? 'template');

    $html = '<div class="pm-header pm-hs-' . $style . '"><div class="pm-hin"><table><tr>'
        . '<td class="pm-hl">' . $left . '</td><td class="pm-hr">' . $right . '</td>'
        . '</tr></table></div></div>';
    return replace_vars($doc, $html);
}

function render_footer($doc, $page, $number, $total)
{
    $s = $doc['settings']['footer'];
    if (empty($s['enabled'])) return '';
    if (isset($page['settings']['show_footer']) && !$page['settings']['show_footer']) return '';

    $co = $doc['company'];
    $parts = array();
    if (!empty($s['show_company']) && !empty($co['company_name'])) $parts[] = '<b>' . e($co['company_name']) . '</b>';
    if (!empty($s['show_website']) && !empty($co['website'])) $parts[] = e($co['website']);
    if (!empty($s['show_email']) && !empty($co['email'])) $parts[] = e($co['email']);
    if (!empty($s['show_phone']) && !empty($co['phone'])) $parts[] = e($co['phone']);
    if (($s['text'] ?? '') !== '') $parts[] = e($s['text']);
    $left = implode('<span class="pm-fsep"> &nbsp;|&nbsp; </span>', $parts);

    // "Page 3 of 10"  ({n} = page, {nn} = page with 2 digits, {total} = number of pages)
    $right = '';
    if (!empty($s['page_numbers'])) {
        $format = ($s['format'] ?? '') !== '' ? $s['format'] : 'Page {n} of {total}';
        $n  = $number ? (string) $number : '#';
        $nn = $number ? sprintf('%02d', $number) : '#';
        $t  = $total ? (string) $total : '#';
        $text = str_replace(array('{n}', '{total}', '{nn}'), array($n, $t, $nn), e($format));
        $right = '<span class="pm-pnum">' . $text . '</span>';
    }
    $style = preg_replace('/[^a-z]/', '', $s['style'] ?? 'template');

    $html = '<div class="pm-footer pm-fs-' . $style . '"><div class="pm-fin"><table><tr>'
        . '<td class="pm-fl">' . $left . '</td><td class="pm-fr">' . $right . '</td>'
        . '</tr></table></div></div>';
    return replace_vars($doc, $html);
}

/* ---------------------------------------------------------------
 *  Cover page
 * ------------------------------------------------------------- */

/**
 * Everything the cover designs need (already escaped and ready to print).
 * $toc = table of contents (used by the "Professional Blue" cover).
 */
function cover_context($doc, $page, $toc = array())
{
    // find the cover block on this page
    $cover = null;
    foreach ($page['blocks'] ?? array() as $b) {
        if (($b['type'] ?? '') === 'cover') {
            $cover = $b;
            break;
        }
    }
    $d = pm_block_defaults('cover');
    if ($cover && is_array($cover['data'] ?? null)) {
        $d = array_merge($d, $cover['data']);
    }

    $p = $doc['p'];
    $tplId = $doc['tpl']['id'];
    $client = ($p['client_company'] ?? '') !== '' ? $p['client_company'] : ($p['client_name'] ?? '');

    // covers with a dark background use the light logo
    $darkCover = in_array($tplId, array(2, 5, 7, 9), true) || ($d['layout'] === 'centered' && $d['image'] !== '');

    // the small "Prepared for / Prepared by / Date / Proposal No." table
    $meta = array();
    $clientKey = e(replace_vars($doc, $d['for_label'], false));
    if (!empty($d['show_client']) && $client !== '') {
        $meta[$clientKey] = e($client);
    }
    if (!empty($d['show_by'])) {
        $meta[e(replace_vars($doc, $d['by_label'], false))] = e($doc['company']['company_name'] ?? '');
    }
    if (!empty($d['show_date']) && !empty($p['proposal_date'])) {
        $meta['Date'] = e(doc_date($doc, $p['proposal_date']));
    }
    if (!empty($d['show_number']) && !empty($p['proposal_number'])) {
        $meta['Proposal No.'] = e($p['proposal_number']);
    }
    if (!empty($d['show_expiry']) && !empty($p['expiry_date'])) {
        $meta['Valid Until'] = e(doc_date($doc, $p['expiry_date']));
    }
    $metaWithoutClient = $meta;
    unset($metaWithoutClient[$clientKey]);

    $year = !empty($p['proposal_date']) ? date('Y', strtotime($p['proposal_date'])) : date('Y');
    $layout = in_array($d['layout'], array('template', 'centered', 'split'), true) ? $d['layout'] : 'template';
    $total = (float) ($p['total'] ?? 0);

    return array(
        'uid'         => $cover['uid'] ?? '',
        'layout'      => $layout,
        'eyebrow'     => replace_vars($doc, e($d['eyebrow'])),
        'title'       => replace_vars($doc, e($d['title'])),
        'title_size'  => max(24, min(90, (int) $d['title_size'])),
        'subtitle'    => replace_vars($doc, nl2br(e($d['subtitle']))),
        'image'       => $d['image'] !== '' ? doc_media($doc, $d['image']) : '',
        'image_raw'   => (string) $d['image'],
        'logo'        => doc_logo($doc, max(20, min(140, (int) $d['logo_size'])), $darkCover),
        'has_logo'    => !empty($doc['company']['logo']),
        'shapes'      => !empty($d['shapes']),
        'for_label'   => $clientKey,
        'client'      => !empty($d['show_client']) ? e($client) : '',
        'client_name' => e($p['client_name'] ?? ''),
        'company'     => e($doc['company']['company_name'] ?? ''),
        'website'     => e($doc['company']['website'] ?? ''),
        'email'       => e($doc['company']['email'] ?? ''),
        'date'        => !empty($d['show_date']) ? e(doc_date($doc, $p['proposal_date'] ?? '')) : '',
        'number'      => !empty($d['show_number']) ? e($p['proposal_number'] ?? '') : '',
        'year'        => $year,
        'year_short'  => substr($year, 2),
        'meta'        => $meta,
        'meta_rest'   => $metaWithoutClient,
        'total'       => $total > 0 ? e(money($total, $doc['symbol'])) : '',
        'toc'         => !empty($d['show_toc']) ? $toc : array(),
    );
}

function render_cover_sheet($doc, $page, $toc = array())
{
    $x = cover_context($doc, $page, $toc);
    $attr = '';
    if ($doc['mode'] === 'editor') {
        $attr = ' data-uid="' . e($x['uid']) . '" data-type="cover"';
    }
    return '<div class="pm-sheet pm-cover pm-cl-' . $x['layout'] . '"' . $attr . '>' . tpl_cover($doc, $x) . '</div>';
}

/* ---------------------------------------------------------------
 *  Internal pages → A4 sheets
 * ------------------------------------------------------------- */

/** One internal A4 sheet: decoration + header + content + footer. */
function render_inner_sheet($doc, $page, $bodyHtml, $number, $total)
{
    $style = '';
    if (is_hex_color($page['settings']['bg'] ?? '')) {
        $style = ' style="background-color:' . $page['settings']['bg'] . '"';
    }
    return '<div class="pm-sheet pm-inner"' . $style . '>'
        . '<div class="pm-deco">' . tpl_deco($doc) . '</div>'
        . render_header($doc, $page)
        . '<div class="pm-body">' . $bodyHtml . '</div>'
        . render_footer($doc, $page, $number, $total)
        . '</div>';
}

/**
 * Decide which blocks go on which A4 sheet.
 * Returns a list of sheets: ['page' => page, 'cover' => bool, 'items' => [[block, slice], ...]]
 */
function sheet_plan($doc)
{
    $breaks = $doc['settings']['breaks'] ?? array();
    $plan = array();

    foreach ($doc['pages'] as $page) {
        if (!empty($page['settings']['hidden'])) {
            continue; // hidden pages are not shown
        }
        if (($page['page_type'] ?? 'content') === 'cover') {
            $plan[] = array('page' => $page, 'cover' => true, 'items' => array(), 'first' => true);
            continue;
        }

        // breaks for this page: block uid => list of child positions where a new sheet starts
        $cutsByBlock = array();
        foreach ((array) ($breaks[$page['uid']] ?? array()) as $br) {
            if (isset($br['b'])) {
                $cutsByBlock[$br['b']][] = (int) ($br['c'] ?? 0);
            }
        }

        $sheets = array(array()); // start with one empty sheet
        foreach ($page['blocks'] ?? array() as $block) {
            if (!empty($block['hidden'])) {
                continue;
            }
            $last = count($sheets) - 1;

            // "Page Break" block → start a new sheet
            if ($block['type'] === 'page_break') {
                if ($sheets[$last]) $sheets[] = array();
                continue;
            }

            $cuts = $cutsByBlock[$block['uid']] ?? array();
            sort($cuts);

            // cut 0 = this whole block moves to a new sheet
            if (in_array(0, $cuts, true) && $sheets[$last]) {
                $sheets[] = array();
            }

            // cuts > 0 = the block itself is split over several sheets
            $innerCuts = array();
            foreach ($cuts as $c) {
                if ($c > 0) $innerCuts[] = $c;
            }
            if (!$innerCuts) {
                $sheets[count($sheets) - 1][] = array($block, null);
                continue;
            }
            $starts = array_merge(array(0), $innerCuts);
            foreach ($starts as $i => $start) {
                $end = isset($starts[$i + 1]) ? $starts[$i + 1] : null;
                if ($i > 0) $sheets[] = array();
                $sheets[count($sheets) - 1][] = array($block, array($start, $end));
            }
        }

        $first = true;
        foreach ($sheets as $items) {
            if (!$items && !$first) continue;
            $plan[] = array('page' => $page, 'cover' => false, 'items' => $items, 'first' => $first);
            $first = false;
        }
    }
    return $plan;
}

/** All A4 sheets (HTML) for the preview, public link and PDF. */
function render_sheets($doc)
{
    $plan = sheet_plan($doc);
    $total = count($plan);

    // table of contents: first sheet of every content page
    $toc = array();
    foreach ($plan as $i => $sheet) {
        if (!$sheet['cover'] && !empty($sheet['first'])) {
            $toc[] = array('title' => e($sheet['page']['title'] ?? ''), 'page' => sprintf('%02d', $i + 1));
        }
    }

    $out = array();
    foreach ($plan as $i => $sheet) {
        if ($sheet['cover']) {
            $out[] = render_cover_sheet($doc, $sheet['page'], $toc);
            continue;
        }
        $body = '';
        foreach ($sheet['items'] as $item) {
            $body .= render_block($doc, $item[0], $item[1]);
        }
        $out[] = render_inner_sheet($doc, $sheet['page'], $body, $i + 1, $total);
    }
    return $out;
}

/** Editor: one sheet per page that grows with its content. */
function render_editor_page($doc, $page, $index = 0)
{
    if (($page['page_type'] ?? 'content') === 'cover') {
        // approximate contents list for the editor
        $toc = array();
        $n = 1;
        foreach ($doc['pages'] as $pg) {
            if (($pg['page_type'] ?? '') === 'cover' || !empty($pg['settings']['hidden'])) continue;
            $n++;
            $toc[] = array('title' => e($pg['title']), 'page' => sprintf('%02d', $n));
        }
        return render_cover_sheet($doc, $page, $toc);
    }
    $body = '';
    foreach ($page['blocks'] ?? array() as $block) {
        $body .= render_block($doc, $block);
    }
    return render_inner_sheet($doc, $page, $body, $index + 1, 0);
}

/* ---------------------------------------------------------------
 *  Putting it on a page
 * ------------------------------------------------------------- */

/** <link>/<style> tags with the proposal CSS (browser) or the inlined CSS (PDF). */
function doc_head_tags($doc)
{
    if ($doc['mode'] === 'pdf') {
        // fonts are given to Dompdf directly (see pm_prepare_pdf_fonts)
        return '<style>' . file_get_contents(APP_ROOT . '/assets/css/proposal.css')
            . file_get_contents(APP_ROOT . '/assets/css/templates.css') . '</style>';
    }
    $fonts = font_face_css(array($doc['font_heading'], $doc['font_body']), 'screen');
    return '<link rel="stylesheet" href="' . asset('css/proposal.css') . '">'
        . '<link rel="stylesheet" href="' . asset('css/templates.css') . '">'
        . '<style>' . $fonts . '</style>';
}

/** class="" and style="" for the element that wraps all sheets. */
function doc_attrs($doc, $extraClass = '')
{
    $modeClass = $doc['mode'] === 'pdf' ? 'pm-pdf' : 'pm-screen';
    return 'class="pm-doc tpl-' . $doc['tpl']['id'] . ' ' . $modeClass . ' ' . $extraClass . '" style="' . doc_css_vars($doc) . '"';
}

/** The complete HTML page that Dompdf turns into the PDF. */
function render_pdf_html($doc)
{
    $sheets = render_sheets($doc);
    return '<!DOCTYPE html><html><head><meta charset="utf-8">'
        . '<title>' . e($doc['p']['title'] ?? 'Proposal') . '</title>'
        . doc_head_tags($doc)
        . '<style>@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0}</style>'
        . '</head><body><div ' . doc_attrs($doc) . '>' . implode('', $sheets) . '</div></body></html>';
}

/* ---------------------------------------------------------------
 *  Settings & colours stored on the proposal
 * ------------------------------------------------------------- */

/** Default proposal settings (header, footer, client page options...). */
function pm_settings_defaults()
{
    return array(
        'header' => array('enabled' => true, 'style' => 'template', 'show_logo' => true, 'logo_height' => 26, 'left' => '', 'right' => '{{proposal_title}}'),
        'footer' => array('enabled' => true, 'style' => 'template', 'show_company' => true, 'show_website' => true, 'show_email' => true, 'show_phone' => false, 'page_numbers' => true, 'format' => 'Page {n} of {total}', 'text' => ''),
        'date_format' => 'F j, Y',
        'breaks' => array(),
        'allow_accept' => true,
        'allow_download' => true,
        'require_signature' => false,
    );
}

/** Saved settings merged over the defaults (so nothing is ever missing). */
function pm_merge_settings($settings)
{
    $saved = jdecode($settings);
    $defaults = pm_settings_defaults();
    $out = array_merge($defaults, $saved);

    foreach (array('header', 'footer') as $part) {
        $savedPart = (isset($saved[$part]) && is_array($saved[$part])) ? $saved[$part] : array();
        $out[$part] = array_merge($defaults[$part], $savedPart);
    }
    if (!is_array($out['breaks'])) {
        $out['breaks'] = array();
    }
    return $out;
}

/** The proposal's colours, with template defaults for anything missing/invalid. */
function pm_resolve_colors($colors, $tpl)
{
    $saved = jdecode($colors);
    $out = array();
    foreach ($tpl['colors'] as $key => $default) {
        $out[$key] = clean_color($saved[$key] ?? '', $default);
    }
    return $out;
}
