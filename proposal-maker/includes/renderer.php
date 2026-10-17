<?php
/**
 * ProposalRenderer — turns a proposal (pages + blocks) into HTML.
 *
 * Modes:
 *   editor — one growing sheet per logical page, blocks carry data-uid
 *   screen — fixed A4 sheets (preview / public link / print)
 *   pdf    — fixed A4 sheets with local file paths for Dompdf
 *
 * Pagination: the editor measures blocks in the browser and stores
 * "breaks" (where a new A4 sheet must start) in proposal settings, so the
 * server can paginate identically for the preview and the PDF.
 */
class ProposalRenderer
{
    public array $p;
    public array $pages;
    public array $company;
    public string $mode;
    public array $tpl;
    public array $colors;
    public array $settings;
    private ?array $varMap = null;
    private $acceptance = false;

    public function __construct(array $proposal, array $pages, array $company, string $mode = 'screen')
    {
        $this->p = $proposal;
        $this->pages = $pages;
        $this->company = $company;
        $this->mode = $mode;
        $this->tpl = pm_template((int) ($proposal['template_id'] ?? 1));
        $this->colors = pm_resolve_colors($proposal['colors'] ?? [], $this->tpl);
        $this->settings = pm_merge_settings($proposal['settings'] ?? []);
    }

    /* ---------------- colours ---------------- */
    public function c(string $key): string
    {
        return $this->colors[$key] ?? '#000000';
    }

    /** Readable text colour on top of a palette colour. */
    public function on(string $key): string
    {
        return color_is_dark($this->c($key)) ? '#ffffff' : '#111111';
    }

    public function mix(string $key, float $amount): string
    {
        return color_mix($this->c($key), $amount);
    }

    /** Soft tint of a colour suitable as a background on this template. */
    public function soft(string $key): string
    {
        $ref = $this->ref($key);
        return color_is_dark($this->c('background')) ? color_mix($ref, -0.7) : color_mix($ref, 0.88);
    }

    /** Colour reference: palette key, 'white' or a hex value. */
    public function ref(string $key): string
    {
        if ($key === 'white') return '#ffffff';
        if (is_hex_color($key)) return $key;
        return $this->c(isset($this->colors[$key]) ? $key : 'primary');
    }

    public function cssVars(): string
    {
        $c = $this->colors;
        $dark = color_is_dark($c['background']);
        $vars = [
            '--primary' => $c['primary'], '--secondary' => $c['secondary'], '--accent' => $c['accent'],
            '--text' => $c['text'], '--heading' => $c['heading'], '--bg' => $c['background'],
            '--card' => $c['card'], '--border' => $c['border'], '--button' => $c['button'],
            '--on-primary' => $this->on('primary'), '--on-secondary' => $this->on('secondary'),
            '--on-accent' => $this->on('accent'), '--on-button' => $this->on('button'),
            '--primary-soft' => $dark ? color_mix($c['primary'], -0.72) : color_mix($c['primary'], 0.9),
            '--accent-soft' => $dark ? color_mix($c['accent'], -0.72) : color_mix($c['accent'], 0.86),
            '--primary-dark' => color_mix($c['primary'], -0.25),
            '--secondary-2' => color_mix($c['secondary'], 0.1),
            '--secondary-3' => color_mix($c['secondary'], 0.22),
            '--muted' => $dark ? color_mix($c['text'], -0.25) : color_mix($c['text'], 0.3),
            '--soft-line' => $dark ? color_mix($c['background'], 0.12) : color_mix($c['border'], 0.4),
            '--font-heading' => font_stack($this->fontHeading()),
            '--font-body' => font_stack($this->fontBody()),
        ];
        $b = $this->tpl['body'];
        $vars['--body-x'] = $b['x'] . 'px';
        $vars['--body-w'] = (PM_SHEET_W - 2 * $b['x']) . 'px';
        $vars['--body-top'] = $b['top'] . 'px';
        $vars['--body-h'] = $b['h'] . 'px';
        $vars['--header-top'] = $b['header'] . 'px';
        $vars['--footer-top'] = $b['footer'] . 'px';
        $out = '';
        foreach ($vars as $k => $v) $out .= $k . ':' . $v . ';';
        return $out;
    }

    public function fontHeading(): string
    {
        return valid_font($this->p['font_heading'] ?? '', $this->tpl['fonts']['heading']);
    }

    public function fontBody(): string
    {
        return valid_font($this->p['font_body'] ?? '', $this->tpl['fonts']['body']);
    }

    public function bodyWidth(): int
    {
        return PM_SHEET_W - 2 * $this->tpl['body']['x'];
    }

    /* ---------------- media ---------------- */
    public function media(?string $path): string
    {
        return media_src($path, $this->mode === 'pdf' ? 'pdf' : 'screen');
    }

    public function cropped(string $src, int $w, int $h): string
    {
        return image_cover($src, $w, $h);
    }

    public function coverImage(string $src, int $w, int $h): string
    {
        return e($this->media($this->cropped($src, $w, $h)));
    }

    public function logo(int $height = 48, bool $light = false): string
    {
        $file = $light && !empty($this->company['logo_light']) ? $this->company['logo_light'] : ($this->company['logo'] ?? '');
        $src = $this->media($file);
        if ($src !== '') {
            return '<img class="pm-logo" src="' . e($src) . '" style="height:' . $height . 'px;width:auto" height="' . $height . '" alt="' . e($this->company['company_name'] ?? '') . '">';
        }
        $size = max(14, (int) round($height * 0.5));
        return '<span class="pm-logo-text" style="font-size:' . $size . 'px;' . ($light ? 'color:#ffffff' : '') . '">' . e($this->company['company_name'] ?? 'Your Company') . '</span>';
    }

    public function currencySymbol(): string
    {
        return (string) ($this->p['currency_symbol'] ?? '$');
    }

    public function fmtDate($d): string
    {
        return fmt_date($d, $this->settings['date_format'] ?? 'F j, Y');
    }

    public function linkUrl(string $url): string
    {
        $url = $this->replaceVars($url, false);
        if ($url === '#accept') {
            return $this->mode === 'pdf' ? public_url($this->p['public_token'] ?? '') : '#pm-respond';
        }
        if ($url !== '' && $url[0] !== '#' && !preg_match('#^(https?:|mailto:|tel:)#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        return $url;
    }

    public function acceptance(): ?array
    {
        if ($this->acceptance === false) {
            $this->acceptance = null;
            if (!empty($this->p['id'])) {
                $this->acceptance = db_row("SELECT * FROM proposal_responses WHERE proposal_id = ? AND response = 'accepted' ORDER BY id DESC LIMIT 1", [(int) $this->p['id']]);
            }
        }
        return $this->acceptance;
    }

    /* ---------------- variables ---------------- */
    public function vars(): array
    {
        if ($this->varMap !== null) return $this->varMap;
        $p = $this->p;
        $co = $this->company;
        $sym = $this->currencySymbol();
        $this->varMap = [
            'client_name' => $p['client_name'] ?? '',
            'client_company' => ($p['client_company'] ?? '') !== '' ? $p['client_company'] : ($p['client_name'] ?? ''),
            'client_email' => $p['client_email'] ?? '',
            'client_phone' => $p['client_phone'] ?? '',
            'client_address' => $p['client_address'] ?? '',
            'client_website' => $p['client_website'] ?? '',
            'proposal_title' => $p['title'] ?? '',
            'project_name' => $p['title'] ?? '',
            'proposal_number' => $p['proposal_number'] ?? '',
            'proposal_date' => $this->fmtDate($p['proposal_date'] ?? ''),
            'expiry_date' => $this->fmtDate($p['expiry_date'] ?? ''),
            'company_name' => $co['company_name'] ?? '',
            'company_tagline' => $co['tagline'] ?? '',
            'company_email' => $co['email'] ?? '',
            'company_phone' => $co['phone'] ?? '',
            'company_whatsapp' => $co['whatsapp'] ?? '',
            'company_website' => $co['website'] ?? '',
            'company_address' => trim(implode(', ', array_filter([$co['address'] ?? '', $co['city'] ?? '', $co['country'] ?? '']))),
            'total_amount' => money($p['total'] ?? 0, $sym),
            'subtotal' => money($p['subtotal'] ?? 0, $sym),
            'currency' => $p['currency'] ?? 'USD',
            'today' => $this->fmtDate(date('Y-m-d')),
            'proposal_link' => !empty($p['public_token']) ? public_url($p['public_token']) : '',
        ];
        return $this->varMap;
    }

    public function replaceVars(string $s, bool $escape = true): string
    {
        if (strpos($s, '{{') === false) return $s;
        $vars = $this->vars();
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($vars, $escape) {
            $k = strtolower($m[1]);
            if (!array_key_exists($k, $vars)) return $m[0];
            return $escape ? e($vars[$k]) : $vars[$k];
        }, $s);
    }

    /* ---------------- blocks ---------------- */
    public function renderBlock(array $b, ?array $slice = null): string
    {
        $type = $b['type'] ?? '';
        $fn = 'pm_render_' . $type;
        if (!function_exists($fn)) return '';
        $d = array_merge(pm_block_defaults($type), is_array($b['data'] ?? null) ? $b['data'] : []);
        $b['data'] = $d;
        try {
            $html = $fn($this, $d, $b);
        } catch (Throwable $e) {
            $html = '<div class="pm-block pm-error">Block error: ' . e($e->getMessage()) . '</div>';
        }
        if ($slice) {
            $html = $this->sliceFlow($html, (int) $slice[0], $slice[1] === null ? null : (int) $slice[1]);
        }
        return $this->replaceVars($html);
    }

    /** Keep only children [from, to) of the block's .pm-flow container. */
    private function sliceFlow(string $html, int $from, ?int $to): string
    {
        if (!class_exists('DOMDocument')) return $html;
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="pmroot">' . $html . '</div></body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $flow = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-flow ")]')->item(0);
        if (!$flow) return $html;
        $kids = [];
        foreach ($flow->childNodes as $n) {
            if ($n->nodeType === XML_ELEMENT_NODE) $kids[] = $n;
        }
        foreach ($kids as $i => $n) {
            if ($i < $from || ($to !== null && $i >= $to)) $flow->removeChild($n);
        }
        // drop loose text nodes
        foreach (iterator_to_array($flow->childNodes) as $n) {
            if ($n->nodeType === XML_TEXT_NODE && trim($n->nodeValue) === '') continue;
            if ($n->nodeType === XML_TEXT_NODE && $from > 0) $flow->removeChild($n);
        }
        if ($from > 0) {
            foreach (iterator_to_array($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-chrome-top ")]')) as $n) $n->parentNode->removeChild($n);
            // continued ordered lists keep numbering
            if (in_array(strtolower($flow->nodeName), ['ol'], true)) $flow->setAttribute('start', (string) ($from + 1));
        }
        if ($to !== null) {
            foreach (iterator_to_array($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " pm-chrome-bottom ")]')) as $n) $n->parentNode->removeChild($n);
        }
        $root = $doc->getElementById('pmroot');
        $out = '';
        foreach ($root->childNodes as $n) $out .= $doc->saveHTML($n);
        return $out;
    }

    /* ---------------- header / footer ---------------- */
    private function headerHtml(array $page): string
    {
        $s = $this->settings['header'];
        if (empty($s['enabled']) || (isset($page['settings']['show_header']) && !$page['settings']['show_header'])) return '';
        $light = in_array($this->tpl['id'], [2, 9], true) || color_is_dark($this->c('background'));
        if (($s['style'] ?? 'template') === 'bar') $light = color_is_dark($this->c('primary'));
        $left = (!empty($s['show_logo']) ? '<span class="pm-hlogo">' . $this->logo((int) ($s['logo_height'] ?? 26), $light) . '</span>' : '')
            . (($s['left'] ?? '') !== '' ? '<span class="pm-htext">' . e($s['left']) . '</span>' : '');
        $right = ($s['right'] ?? '') !== '' ? e($s['right']) : '';
        $style = preg_replace('/[^a-z]/', '', $s['style'] ?? 'template');
        return $this->replaceVars('<div class="pm-header pm-hs-' . $style . '"><div class="pm-hin"><table><tr><td class="pm-hl">' . $left . '</td><td class="pm-hr">' . $right . '</td></tr></table></div></div>');
    }

    private function footerHtml(array $page, int $no, int $total): string
    {
        $s = $this->settings['footer'];
        if (empty($s['enabled']) || (isset($page['settings']['show_footer']) && !$page['settings']['show_footer'])) return '';
        $co = $this->company;
        $parts = [];
        if (!empty($s['show_company']) && !empty($co['company_name'])) $parts[] = '<b>' . e($co['company_name']) . '</b>';
        if (!empty($s['show_website']) && !empty($co['website'])) $parts[] = e($co['website']);
        if (!empty($s['show_email']) && !empty($co['email'])) $parts[] = e($co['email']);
        if (!empty($s['show_phone']) && !empty($co['phone'])) $parts[] = e($co['phone']);
        if (($s['text'] ?? '') !== '') $parts[] = e($s['text']);
        $left = implode('<span class="pm-fsep"> &nbsp;|&nbsp; </span>', $parts);
        $right = '';
        if (!empty($s['page_numbers'])) {
            $fmt = ($s['format'] ?? '') !== '' ? $s['format'] : 'Page {n} of {total}';
            $nStr = $no ? (string) $no : '#';
            $tStr = $total ? (string) $total : '#';
            $right = '<span class="pm-pnum">' . str_replace(['{n}', '{total}', '{nn}'], [$nStr, $tStr, $no ? sprintf('%02d', $no) : '#'], e($fmt)) . '</span>';
        }
        $style = preg_replace('/[^a-z]/', '', $s['style'] ?? 'template');
        return $this->replaceVars('<div class="pm-footer pm-fs-' . $style . '"><div class="pm-fin"><table><tr><td class="pm-fl">' . $left . '</td><td class="pm-fr">' . $right . '</td></tr></table></div></div>');
    }

    /* ---------------- cover ---------------- */
    public function coverContext(array $page, array $toc = []): array
    {
        $cover = null;
        foreach ($page['blocks'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'cover') {
                $cover = $b;
                break;
            }
        }
        $d = array_merge(pm_block_defaults('cover'), is_array($cover['data'] ?? null) ? $cover['data'] : []);
        $p = $this->p;
        $client = ($p['client_company'] ?? '') !== '' ? $p['client_company'] : ($p['client_name'] ?? '');
        $darkCover = in_array($this->tpl['id'], [2, 5, 7], true) || ($d['layout'] === 'centered' && $d['image'] !== '');
        if ($this->tpl['id'] === 9) $darkCover = true;
        $meta = [];
        $clientKey = e($this->replaceVars($d['for_label'], false));
        if (!empty($d['show_client']) && $client !== '') $meta[$clientKey] = e($client);
        if (!empty($d['show_by'])) $meta[e($this->replaceVars($d['by_label'], false))] = e($this->company['company_name'] ?? '');
        if (!empty($d['show_date']) && !empty($p['proposal_date'])) $meta['Date'] = e($this->fmtDate($p['proposal_date']));
        if (!empty($d['show_number']) && !empty($p['proposal_number'])) $meta['Proposal No.'] = e($p['proposal_number']);
        if (!empty($d['show_expiry']) && !empty($p['expiry_date'])) $meta['Valid Until'] = e($this->fmtDate($p['expiry_date']));
        $year = $p['proposal_date'] ? date('Y', strtotime($p['proposal_date'])) : date('Y');
        return [
            'uid' => $cover['uid'] ?? '',
            'layout' => in_array($d['layout'], ['template', 'centered', 'split'], true) ? $d['layout'] : 'template',
            'eyebrow' => $this->replaceVars(e($d['eyebrow'])),
            'title' => $this->replaceVars(e($d['title'])),
            'title_size' => max(24, min(90, (int) $d['title_size'])),
            'subtitle' => $this->replaceVars(nl2br(e($d['subtitle']))),
            'image' => $d['image'] !== '' ? $this->media($d['image']) : '',
            'image_raw' => (string) $d['image'],
            'logo' => $this->logo(max(20, min(140, (int) $d['logo_size'])), $darkCover),
            'shapes' => !empty($d['shapes']),
            'for_label' => e($this->replaceVars($d['for_label'], false)),
            'client' => !empty($d['show_client']) ? e($client) : '',
            'client_name' => e($p['client_name'] ?? ''),
            'company' => e($this->company['company_name'] ?? ''),
            'website' => e($this->company['website'] ?? ''),
            'email' => e($this->company['email'] ?? ''),
            'date' => !empty($d['show_date']) ? e($this->fmtDate($p['proposal_date'] ?? '')) : '',
            'number' => !empty($d['show_number']) ? e($p['proposal_number'] ?? '') : '',
            'year' => $year,
            'year_short' => substr($year, 2),
            'meta' => $meta,
            'meta_rest' => array_diff_key($meta, [$clientKey => 1]),
            'has_logo' => !empty($this->company['logo']),
            'total' => (float) ($p['total'] ?? 0) > 0 ? e(money($p['total'], $this->currencySymbol())) : '',
            'toc' => !empty($d['show_toc']) ? $toc : [],
        ];
    }

    private function coverSheet(array $page, array $toc = []): string
    {
        $x = $this->coverContext($page, $toc);
        $attr = $this->mode === 'editor' ? ' data-uid="' . e($x['uid']) . '" data-type="cover"' : '';
        return '<div class="pm-sheet pm-cover pm-cl-' . $x['layout'] . '"' . $attr . '>' . tpl_cover($this, $x) . '</div>';
    }

    /* ---------------- sheets ---------------- */

    /** Split pages into physical A4 sheets using page breaks + measured breaks. */
    public function sheetPlan(): array
    {
        $breaks = $this->settings['breaks'] ?? [];
        $plan = [];
        foreach ($this->pages as $page) {
            if (!empty($page['settings']['hidden'])) continue;
            if (($page['page_type'] ?? 'content') === 'cover') {
                $plan[] = ['page' => $page, 'cover' => true, 'items' => []];
                continue;
            }
            $pb = [];
            foreach ((array) ($breaks[$page['uid']] ?? []) as $br) {
                if (!isset($br['b'])) continue;
                $pb[$br['b']][] = (int) ($br['c'] ?? 0);
            }
            $sheets = [[]];
            foreach ($page['blocks'] ?? [] as $b) {
                if (!empty($b['hidden'])) continue;
                $cur = count($sheets) - 1;
                if ($b['type'] === 'page_break') {
                    if ($sheets[$cur]) $sheets[] = [];
                    continue;
                }
                $cuts = $pb[$b['uid']] ?? [];
                sort($cuts);
                if (in_array(0, $cuts, true) && $sheets[$cur]) {
                    $sheets[] = [];
                }
                $cuts = array_values(array_filter($cuts, fn($c) => $c > 0));
                if (!$cuts) {
                    $sheets[count($sheets) - 1][] = [$b, null];
                    continue;
                }
                $starts = array_merge([0], $cuts);
                foreach ($starts as $i => $st) {
                    $end = $starts[$i + 1] ?? null;
                    if ($i > 0) $sheets[] = [];
                    $sheets[count($sheets) - 1][] = [$b, [$st, $end]];
                }
            }
            $first = true;
            foreach ($sheets as $items) {
                if (!$items && !$first) continue;
                $plan[] = ['page' => $page, 'cover' => false, 'items' => $items, 'first' => $first];
                $first = false;
            }
        }
        return $plan;
    }

    /** All A4 sheets for screen / pdf output. */
    public function renderSheets(): array
    {
        $plan = $this->sheetPlan();
        $total = count($plan);
        $toc = [];
        foreach ($plan as $i => $s) {
            if (!$s['cover'] && !empty($s['first'])) {
                $toc[] = ['title' => e($s['page']['title'] ?? ''), 'page' => sprintf('%02d', $i + 1)];
            }
        }
        $out = [];
        foreach ($plan as $i => $s) {
            if ($s['cover']) {
                $out[] = $this->coverSheet($s['page'], $toc);
                continue;
            }
            $body = '';
            foreach ($s['items'] as [$b, $slice]) {
                $body .= $this->renderBlock($b, $slice);
            }
            $out[] = $this->innerSheet($s['page'], $body, $i + 1, $total);
        }
        return $out;
    }

    private function innerSheet(array $page, string $body, int $no, int $total, string $extraAttr = ''): string
    {
        $ps = $page['settings'] ?? [];
        $bg = is_hex_color($ps['bg'] ?? '') ? ' style="background-color:' . $ps['bg'] . '"' : '';
        return '<div class="pm-sheet pm-inner"' . $bg . $extraAttr . '><div class="pm-deco">' . tpl_deco($this) . '</div>'
            . $this->headerHtml($page)
            . '<div class="pm-body">' . $body . '</div>'
            . $this->footerHtml($page, $no, $total) . '</div>';
    }

    /** Editor: one sheet per logical page, growing with content. */
    public function editorPage(array $page, int $index = 0): string
    {
        if (($page['page_type'] ?? 'content') === 'cover') {
            $toc = [];
            $n = 1;
            foreach ($this->pages as $pg) {
                if (($pg['page_type'] ?? '') === 'cover' || !empty($pg['settings']['hidden'])) continue;
                $toc[] = ['title' => e($pg['title']), 'page' => sprintf('%02d', ++$n)];
            }
            return $this->coverSheet($page, $toc);
        }
        $body = '';
        foreach ($page['blocks'] ?? [] as $b) {
            $body .= $this->renderBlock($b);
        }
        return $this->innerSheet($page, $body, $index + 1, 0);
    }

    /* ---------------- documents ---------------- */
    public function fontCss(): string
    {
        return font_face_css([$this->fontHeading(), $this->fontBody()], $this->mode === 'pdf' ? 'pdf' : 'screen');
    }

    /** <style>/<link> tags for embedding into a page head. */
    public function headTags(): string
    {
        if ($this->mode === 'pdf') {
            // fonts are registered directly with Dompdf (see pm_prepare_pdf_fonts)
            return '<style>' . file_get_contents(APP_ROOT . '/assets/css/proposal.css') . file_get_contents(APP_ROOT . '/assets/css/templates.css') . '</style>';
        }
        return '<link rel="stylesheet" href="' . asset('css/proposal.css') . '"><link rel="stylesheet" href="' . asset('css/templates.css') . '"><style>' . $this->fontCss() . '</style>';
    }

    public function docAttrs(string $extraClass = ''): string
    {
        return 'class="pm-doc tpl-' . $this->tpl['id'] . ' ' . ($this->mode === 'pdf' ? 'pm-pdf' : 'pm-screen') . ' ' . $extraClass . '" style="' . $this->cssVars() . '"';
    }

    /** Full HTML document for Dompdf. */
    public function pdfDocument(): string
    {
        $sheets = $this->renderSheets();
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($this->p['title'] ?? 'Proposal') . '</title>'
            . $this->headTags() . '<style>@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0}</style></head><body>'
            . '<div ' . $this->docAttrs() . '>' . implode('', $sheets) . '</div></body></html>';
    }
}

/* ===================================================================
 *  Settings / colours helpers
 * ================================================================= */

function pm_settings_defaults(): array
{
    return [
        'header' => ['enabled' => true, 'style' => 'template', 'show_logo' => true, 'logo_height' => 26, 'left' => '', 'right' => '{{proposal_title}}'],
        'footer' => ['enabled' => true, 'style' => 'template', 'show_company' => true, 'show_website' => true, 'show_email' => true, 'show_phone' => false, 'page_numbers' => true, 'format' => 'Page {n} of {total}', 'text' => ''],
        'date_format' => 'F j, Y',
        'breaks' => [],
        'allow_accept' => true,
        'allow_download' => true,
        'require_signature' => false,
    ];
}

function pm_merge_settings($settings): array
{
    $s = jdecode($settings);
    $d = pm_settings_defaults();
    $out = array_merge($d, $s);
    $out['header'] = array_merge($d['header'], is_array($s['header'] ?? null) ? $s['header'] : []);
    $out['footer'] = array_merge($d['footer'], is_array($s['footer'] ?? null) ? $s['footer'] : []);
    if (!is_array($out['breaks'])) $out['breaks'] = [];
    return $out;
}

function pm_resolve_colors($colors, array $tpl): array
{
    $c = jdecode($colors);
    $out = [];
    foreach ($tpl['colors'] as $k => $def) {
        $out[$k] = clean_color($c[$k] ?? '', $def);
    }
    return $out;
}
