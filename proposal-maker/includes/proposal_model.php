<?php
/**
 * PROPOSALS — loading, saving, copying and making the PDF.
 *
 *   pm_get($id)                 one proposal
 *   pm_pages($id)               its pages and blocks
 *   pm_save_pages($id, $pages)  save pages + blocks (and recalculate the total)
 *   pm_create($data, $pages)    new proposal
 *   pm_duplicate($id)           copy a proposal
 *   pm_snapshot($id)            save a version (history)
 *   pm_pdf($p, $pages)          make the PDF with Dompdf
 *   pm_seed_defaults()          fill an empty database with the starter content
 */

function pm_next_number($prefix = null)
{
    $prefix = $prefix ?? (company()['proposal_prefix'] ?? 'PROP');
    $prefix = preg_replace('/[^A-Za-z0-9\-]/', '', $prefix) ?: 'PROP';
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $rows = db_all('SELECT proposal_number FROM proposals WHERE proposal_number LIKE ?', [$like]);
    $max = 0;
    foreach ($rows as $r) {
        if (preg_match('/-(\d+)$/', $r['proposal_number'], $m)) $max = max($max, (int) $m[1]);
    }
    return sprintf('%s-%s-%03d', $prefix, $year, $max + 1);
}

function pm_new_token()
{
    do {
        $t = bin2hex(random_bytes(8));
    } while (db_value('SELECT id FROM proposals WHERE public_token = ?', [$t]));
    return $t;
}

/** Decode a proposal row's JSON columns. */
function pm_decode_row($p)
{
    $p['colors'] = jdecode($p['colors'] ?? '');
    $p['settings'] = jdecode($p['settings'] ?? '');
    return $p;
}

function pm_get($id)
{
    $p = db_row('SELECT * FROM proposals WHERE id = ?', [$id]);
    return $p ? pm_decode_row($p) : null;
}

function pm_get_by_token($token)
{
    if (!preg_match('/^[a-f0-9]{8,64}$/', $token)) return null;
    $p = db_row('SELECT * FROM proposals WHERE public_token = ?', [$token]);
    return $p ? pm_decode_row($p) : null;
}

function pm_pages($proposalId)
{
    $pages = db_all('SELECT * FROM proposal_pages WHERE proposal_id = ? ORDER BY sort_order, id', [$proposalId]);
    $blocks = db_all('SELECT * FROM proposal_blocks WHERE proposal_id = ? ORDER BY sort_order, id', [$proposalId]);
    $byPage = [];
    foreach ($blocks as $b) {
        $byPage[$b['page_id']][] = [
            'uid' => $b['uid'],
            'type' => $b['type'],
            'data' => jdecode($b['data']),
            'hidden' => (bool) $b['is_hidden'],
        ];
    }
    $out = [];
    foreach ($pages as $pg) {
        $out[] = [
            'uid' => $pg['uid'],
            'title' => $pg['title'],
            'page_type' => $pg['page_type'],
            'settings' => jdecode($pg['settings']),
            'blocks' => $byPage[$pg['id']] ?? [],
        ];
    }
    return $out;
}

/** Sanitise a client-supplied page structure. */
function pm_normalize_pages($pages)
{
    $types = pm_blocks();
    $out = [];
    $hasCover = false;
    foreach ($pages as $pg) {
        if (!is_array($pg)) continue;
        $isCover = ($pg['page_type'] ?? 'content') === 'cover';
        if ($isCover && $hasCover) $isCover = false;
        $hasCover = $hasCover || $isCover;
        $blocks = [];
        foreach ((array) ($pg['blocks'] ?? []) as $b) {
            if (!is_array($b) || !isset($types[$b['type'] ?? ''])) continue;
            if ($b['type'] === 'cover' && !$isCover) continue;
            $blocks[] = [
                'uid' => preg_match('/^[a-z0-9_\-]{3,40}$/i', $b['uid'] ?? '') ? $b['uid'] : uid('b'),
                'type' => $b['type'],
                'data' => is_array($b['data'] ?? null) ? $b['data'] : [],
                'hidden' => !empty($b['hidden']),
            ];
        }
        // a cover page must always contain a cover block
        $hasCoverBlock = false;
        foreach ($blocks as $b) {
            if ($b['type'] === 'cover') $hasCoverBlock = true;
        }
        if ($isCover && !$hasCoverBlock) {
            array_unshift($blocks, ['uid' => uid('b'), 'type' => 'cover', 'data' => pm_block_defaults('cover'), 'hidden' => false]);
        }
        $ps = is_array($pg['settings'] ?? null) ? $pg['settings'] : [];
        $out[] = [
            'uid' => preg_match('/^[a-z0-9_\-]{3,40}$/i', $pg['uid'] ?? '') ? $pg['uid'] : uid('p'),
            'title' => mb_substr(trim((string) ($pg['title'] ?? 'Page')) ?: 'Page', 0, 180),
            'page_type' => $isCover ? 'cover' : 'content',
            'settings' => [
                'hidden' => !empty($ps['hidden']),
                'show_header' => !isset($ps['show_header']) || !empty($ps['show_header']),
                'show_footer' => !isset($ps['show_footer']) || !empty($ps['show_footer']),
                'bg' => is_hex_color($ps['bg'] ?? '') ? $ps['bg'] : '',
            ],
            'blocks' => $blocks,
        ];
    }
    return $out;
}

/** Totals = sum of all visible pricing tables. */
function pm_compute_totals($pages)
{
    $t = ['subtotal' => 0.0, 'discount' => 0.0, 'tax' => 0.0, 'total' => 0.0];
    foreach ($pages as $pg) {
        if (!empty($pg['settings']['hidden'])) continue;
        foreach ($pg['blocks'] as $b) {
            if ($b['type'] !== 'pricing_table' || !empty($b['hidden'])) continue;
            $x = pm_pricing_totals(array_merge(pm_block_defaults('pricing_table'), $b['data']));
            foreach ($t as $k => $v) $t[$k] += $x[$k];
        }
    }
    foreach ($t as $k => $v) {
        $t[$k] = round($v, 2);
    }
    return $t;
}

/** Replace all pages/blocks of a proposal (transaction). */
function pm_save_pages($proposalId, $pages)
{
    $pages = pm_normalize_pages($pages);
    $db = db();
    $db->begin_transaction();
    try {
        db_query('DELETE FROM proposal_pages WHERE proposal_id = ?', [$proposalId]);
        foreach ($pages as $i => $pg) {
            $pageId = db_insert('proposal_pages', [
                'proposal_id' => $proposalId,
                'uid' => $pg['uid'],
                'title' => $pg['title'],
                'page_type' => $pg['page_type'],
                'sort_order' => $i,
                'settings' => jencode($pg['settings']),
            ]);
            foreach ($pg['blocks'] as $j => $b) {
                db_insert('proposal_blocks', [
                    'proposal_id' => $proposalId,
                    'page_id' => $pageId,
                    'uid' => $b['uid'],
                    'type' => $b['type'],
                    'data' => jencode($b['data']),
                    'sort_order' => $j,
                    'is_hidden' => $b['hidden'] ? 1 : 0,
                ]);
            }
        }
        $t = pm_compute_totals($pages);
        db_update('proposals', $t + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$proposalId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $pages;
}

/** Build page structure from a service template structure (array of pages). */
function pm_structure_instantiate($structure)
{
    $pages = [];
    foreach ($structure as $pg) {
        $blocks = [];
        foreach ($pg['blocks'] ?? [] as $b) {
            $blocks[] = ['uid' => uid('b'), 'type' => $b['type'], 'data' => array_merge(pm_block_defaults($b['type']), $b['data'] ?? []), 'hidden' => !empty($b['hidden'])];
        }
        $pages[] = ['uid' => uid('p'), 'title' => $pg['title'] ?? 'Page', 'page_type' => $pg['page_type'] ?? 'content', 'settings' => $pg['settings'] ?? [], 'blocks' => $blocks];
    }
    return $pages;
}

function pm_blank_structure()
{
    return [
        ['title' => 'Cover', 'page_type' => 'cover', 'blocks' => [['type' => 'cover', 'data' => []]]],
        ['title' => 'Introduction', 'blocks' => [
            ['type' => 'heading', 'data' => ['eyebrow' => 'Introduction', 'text' => 'Dear {{client_name}},']],
            ['type' => 'text', 'data' => ['html' => '<p>Thank you for the opportunity to submit this proposal. We have carefully reviewed your requirements and are excited to present our approach.</p>']],
        ]],
    ];
}

/** Snapshot the current proposal into proposal_versions. */
function pm_snapshot($proposalId, $label = 'Auto-save')
{
    $p = db_row('SELECT * FROM proposals WHERE id = ?', [$proposalId]);
    if (!$p) return;
    $snap = ['proposal' => $p, 'pages' => pm_pages($proposalId)];
    db_insert('proposal_versions', [
        'proposal_id' => $proposalId,
        'label' => mb_substr($label, 0, 180),
        'snapshot' => jencode($snap),
        'created_by' => current_user()['id'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    // keep the latest 30 versions
    $ids = db_all('SELECT id FROM proposal_versions WHERE proposal_id = ? ORDER BY id DESC LIMIT 30, 1000', [$proposalId]);
    foreach ($ids as $r) db_query('DELETE FROM proposal_versions WHERE id = ?', [(int) $r['id']]);
}

function pm_restore_version($versionId)
{
    $v = db_row('SELECT * FROM proposal_versions WHERE id = ?', [$versionId]);
    if (!$v) return null;
    $snap = jdecode($v['snapshot']);
    $pid = (int) $v['proposal_id'];
    pm_snapshot($pid, 'Before restoring version #' . $versionId);
    $p = $snap['proposal'] ?? [];
    $fields = ['title', 'client_name', 'client_company', 'client_email', 'client_phone', 'client_address', 'client_website', 'template_id', 'colors', 'font_heading', 'font_body', 'settings', 'currency', 'currency_symbol', 'proposal_date', 'expiry_date'];
    $upd = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $p)) $upd[$f] = $p[$f];
    }
    if ($upd) db_update('proposals', $upd, 'id = ?', [$pid]);
    pm_save_pages($pid, $snap['pages'] ?? []);
    return $pid;
}

/** Create a proposal record + pages. Returns new id. */
function pm_create($data, $pages)
{
    $tpl = pm_template((int) ($data['template_id'] ?? 1));
    $co = company();
    $cur = $data['currency'] ?? ($co['default_currency'] ?? 'USD');
    $sym = $data['currency_symbol'] ?? (currencies()[$cur]['symbol'] ?? '$');
    $row = [
        'proposal_number' => $data['proposal_number'] ?? pm_next_number(),
        'title' => $data['title'] ?? 'Proposal',
        'service_type' => $data['service_type'] ?? null,
        'client_name' => $data['client_name'] ?? '',
        'client_company' => $data['client_company'] ?? '',
        'client_email' => $data['client_email'] ?? '',
        'client_phone' => $data['client_phone'] ?? '',
        'client_address' => $data['client_address'] ?? '',
        'client_website' => $data['client_website'] ?? '',
        'template_id' => $tpl['id'],
        'colors' => jencode($data['colors'] ?? $tpl['colors']),
        'font_heading' => $data['font_heading'] ?? $tpl['fonts']['heading'],
        'font_body' => $data['font_body'] ?? $tpl['fonts']['body'],
        'settings' => jencode($data['settings'] ?? pm_settings_defaults()),
        'currency' => $cur,
        'currency_symbol' => $sym,
        'status' => 'draft',
        'public_token' => pm_new_token(),
        'proposal_date' => $data['proposal_date'] ?? date('Y-m-d'),
        'expiry_date' => $data['expiry_date'] ?? date('Y-m-d', strtotime('+' . (int) ($co['default_validity_days'] ?? 30) . ' days')),
        'created_by' => current_user()['id'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $id = db_insert('proposals', $row);
    pm_save_pages($id, $pages);
    log_activity($id, 'created', 'Proposal created');
    return $id;
}

function pm_duplicate($id)
{
    $p = db_row('SELECT * FROM proposals WHERE id = ?', [$id]);
    if (!$p) return null;
    $pages = pm_pages($id);
    // regenerate uids so breaks map stays consistent
    $settings = jdecode($p['settings']);
    $newBreaks = [];
    foreach ($pages as &$pg) {
        $oldPage = $pg['uid'];
        $pg['uid'] = uid('p');
        $map = [];
        foreach ($pg['blocks'] as &$b) {
            $new = uid('b');
            $map[$b['uid']] = $new;
            $b['uid'] = $new;
        }
        unset($b);
        foreach ((array) ($settings['breaks'][$oldPage] ?? []) as $br) {
            if (isset($map[$br['b'] ?? ''])) $newBreaks[$pg['uid']][] = ['b' => $map[$br['b']], 'c' => (int) ($br['c'] ?? 0)];
        }
    }
    unset($pg);
    $settings['breaks'] = $newBreaks;
    $newId = pm_create([
        'proposal_number' => pm_next_number(),
        'title' => $p['title'] . ' (Copy)',
        'service_type' => $p['service_type'],
        'client_name' => $p['client_name'], 'client_company' => $p['client_company'], 'client_email' => $p['client_email'],
        'client_phone' => $p['client_phone'], 'client_address' => $p['client_address'], 'client_website' => $p['client_website'],
        'template_id' => (int) $p['template_id'],
        'colors' => jdecode($p['colors']),
        'font_heading' => $p['font_heading'], 'font_body' => $p['font_body'],
        'settings' => $settings,
        'currency' => $p['currency'], 'currency_symbol' => $p['currency_symbol'],
        'proposal_date' => date('Y-m-d'),
        'expiry_date' => date('Y-m-d', strtotime('+' . (int) (company()['default_validity_days'] ?? 30) . ' days')),
    ], $pages);
    log_activity($newId, 'duplicated', 'Duplicated from ' . $p['proposal_number']);
    return $newId;
}

/** Mark proposals past their expiry date as expired. */
function pm_refresh_expired()
{
    db_query("UPDATE proposals SET status = 'expired' WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() AND status IN ('draft','sent','viewed')");
}

function pm_filename($p)
{
    $client = ($p['client_company'] ?? '') !== '' ? $p['client_company'] : ($p['client_name'] ?? '');
    return slugify(($p['title'] ?? 'Proposal') . ($client !== '' ? '_' . $client : '')) . '.pdf';
}

/** Generate PDF binary for a proposal. */
function pm_pdf($p, $pages)
{
    if (!is_file(APP_ROOT . '/vendor/autoload.php')) {
        throw new RuntimeException('Dompdf is not installed (vendor folder missing).');
    }
    require_once APP_ROOT . '/vendor/autoload.php';
    @set_time_limit(120);
    @ini_set('memory_limit', '512M');
    $doc = doc_create($p, $pages, company(), 'pdf');
    $html = render_pdf_html($doc);
    $fontDir = APP_ROOT . '/storage/fonts';
    if (!is_dir($fontDir)) @mkdir($fontDir, 0755, true);
    $opt = new Dompdf\Options();
    $opt->set('isRemoteEnabled', true);
    $opt->set('isHtml5ParserEnabled', true);
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('dpi', 96);
    $opt->set('fontDir', $fontDir);
    $opt->set('fontCache', $fontDir);
    $opt->set('tempDir', APP_ROOT . '/storage/tmp');
    $opt->setChroot([APP_ROOT]);
    $opt->set('fontHeightRatio', 1.0);
    // register + normalise fonts with a throwaway instance (metrics are cached per instance)
    pm_prepare_pdf_fonts(new Dompdf\Dompdf($opt), [$doc['font_heading'], $doc['font_body']]);
    $dompdf = new Dompdf\Dompdf($opt);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->addInfo('Title', $p['title'] ?? 'Proposal');
    $dompdf->addInfo('Author', company()['company_name'] ?? '');
    return $dompdf->output();
}

/**
 * Dompdf derives line heights from each font's ascender/descender, while
 * browsers use font-size × line-height. Normalising the cached metrics so
 * (ascender − descender) = 1 em makes Dompdf lay text out like Chrome, so
 * the PDF paginates exactly like the editor preview.
 */
function pm_prepare_pdf_fonts($dompdf, $families)
{
    $fm = $dompdf->getFontMetrics();
    $list = font_list();
    foreach (array_unique($families) as $fam) {
        if (!isset($list[$fam])) continue;
        foreach (['normal' => 'Regular', 'bold' => 'Bold'] as $weight => $suffix) {
            $file = APP_ROOT . '/assets/fonts/' . $list[$fam]['file'] . '-' . $suffix . '.ttf';
            if (!is_file($file)) continue;
            $fams = $fm->getFontFamilies();
            if (empty($fams[strtolower($fam)][$weight])) {
                $fm->registerFont(['family' => $fam, 'weight' => $weight, 'style' => 'normal'], $file);
                $fams = $fm->getFontFamilies();
            }
            $base = $fams[strtolower($fam)][$weight] ?? null;
            if ($base) pm_normalize_font_metrics($base);
        }
    }
}

function pm_normalize_font_metrics($base)
{
    $json = $base . '.ufm.json';
    if (is_file($json)) {
        $d = json_decode(file_get_contents($json), true);
        if (is_array($d) && empty($d['PMNormalized']) && isset($d['Ascender'], $d['Descender'])) {
            $asc = (float) $d['Ascender'];
            $desc = (float) $d['Descender'];
            $sum = $asc - $desc;
            if ($sum > 0) {
                $d['Ascender'] = (string) round($asc * 1000 / $sum);
                $d['Descender'] = (string) round($desc * 1000 / $sum);
                $d['FontHeightOffset'] = '0';
                $d['PMNormalized'] = 1;
                file_put_contents($json, json_encode($d));
            }
        }
    }
    $ufm = $base . '.ufm';
    if (is_file($ufm)) {
        $txt = file_get_contents($ufm);
        if (strpos($txt, 'Comment PMNormalized') === false && preg_match('/^Ascender\s+(-?\d+)/m', $txt, $a) && preg_match('/^Descender\s+(-?\d+)/m', $txt, $dd)) {
            $sum = (float) $a[1] - (float) $dd[1];
            if ($sum > 0) {
                $txt = preg_replace('/^Ascender\s+-?\d+/m', 'Ascender ' . round($a[1] * 1000 / $sum), $txt);
                $txt = preg_replace('/^Descender\s+-?\d+/m', 'Descender ' . round($dd[1] * 1000 / $sum), $txt);
                $txt = preg_replace('/^(StartFontMetrics[^\n]*\n)/', "$1Comment PMNormalized\n", $txt);
                file_put_contents($ufm, $txt);
            }
        }
    }
}

/* ===================================================================
 *  Seeding (service templates, content library, presets)
 * ================================================================= */
function pm_seed_defaults($force = false)
{
    require_once __DIR__ . '/prebuilt.php';
    if ($force || !(int) db_value('SELECT COUNT(*) FROM service_templates')) {
        foreach (pm_prebuilt_proposals() as $slug => $t) {
            db_insert('service_templates', [
                'name' => $t['name'], 'slug' => $slug, 'service' => $t['service'], 'description' => $t['description'],
                'icon' => $t['icon'], 'structure' => jencode($t['pages']), 'is_system' => 1, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    if ($force || !(int) db_value('SELECT COUNT(*) FROM content_library')) {
        foreach (pm_prebuilt_library() as $item) {
            db_insert('content_library', [
                'name' => $item['name'], 'category' => $item['category'], 'description' => $item['description'] ?? '',
                'blocks' => jencode($item['blocks']), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    if (!(int) db_value('SELECT COUNT(*) FROM brand_presets')) {
        $presets = [
            'Webanza Default' => ['primary' => '#015957', 'secondary' => '#0f172a', 'accent' => '#f59e0b', 'text' => '#374151', 'heading' => '#0f172a', 'background' => '#ffffff', 'card' => '#f3f6f8', 'border' => '#e5e7eb', 'button' => '#015957'],
            'Blue Corporate' => ['primary' => '#1d4ed8', 'secondary' => '#0b1f44', 'accent' => '#38bdf8', 'text' => '#334155', 'heading' => '#0b1f44', 'background' => '#ffffff', 'card' => '#f0f5ff', 'border' => '#d6e0f5', 'button' => '#1d4ed8'],
            'Dark Premium' => ['primary' => '#d4af37', 'secondary' => '#0b0f19', 'accent' => '#d4af37', 'text' => '#c9ccd3', 'heading' => '#ffffff', 'background' => '#0b0f19', 'card' => '#141a28', 'border' => '#2a3142', 'button' => '#d4af37'],
            'Green Modern' => ['primary' => '#059669', 'secondary' => '#064e3b', 'accent' => '#a3e635', 'text' => '#374151', 'heading' => '#052e2b', 'background' => '#ffffff', 'card' => '#ecfdf5', 'border' => '#d1fae5', 'button' => '#059669'],
            'Red Creative' => ['primary' => '#e11d48', 'secondary' => '#111111', 'accent' => '#facc15', 'text' => '#27272a', 'heading' => '#111111', 'background' => '#ffffff', 'card' => '#fff1f2', 'border' => '#f1d5da', 'button' => '#e11d48'],
        ];
        $first = true;
        foreach ($presets as $name => $colors) {
            db_insert('brand_presets', ['name' => $name, 'colors' => jencode($colors), 'is_default' => $first ? 1 : 0, 'created_at' => date('Y-m-d H:i:s')]);
            $first = false;
        }
    }
}
