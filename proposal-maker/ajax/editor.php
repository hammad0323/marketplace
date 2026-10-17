<?php
/**
 * EDITOR AJAX — the editor (assets/js/editor.js) calls this file for:
 *   render   HTML of the pages            save          save everything
 *   upload   upload an image              media         list uploaded images
 *   library_list / library_get / library_save          content library
 *   versions / save_version / save_as_template / preset_save
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

/** Merge client-side proposal fields over the stored row (sanitised). */
function editor_proposal($row, $in)
{
    $p = pm_decode_row($row);
    foreach (['title', 'proposal_number', 'client_name', 'client_company', 'client_email', 'client_phone', 'client_address', 'client_website', 'currency', 'currency_symbol'] as $k) {
        if (isset($in[$k]) && is_scalar($in[$k])) $p[$k] = mb_substr(trim((string) $in[$k]), 0, $k === 'currency_symbol' || $k === 'currency' ? 10 : 250);
    }
    foreach (['proposal_date', 'expiry_date'] as $k) {
        if (isset($in[$k])) $p[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $in[$k]) ? $in[$k] : null;
    }
    if (isset($in['template_id'])) $p['template_id'] = pm_template((int) $in['template_id'])['id'];
    $tpl = pm_template((int) $p['template_id']);
    if (isset($in['colors']) && is_array($in['colors'])) $p['colors'] = pm_resolve_colors($in['colors'], $tpl);
    if (isset($in['font_heading'])) $p['font_heading'] = valid_font($in['font_heading'], $tpl['fonts']['heading']);
    if (isset($in['font_body'])) $p['font_body'] = valid_font($in['font_body'], $tpl['fonts']['body']);
    if (isset($in['settings']) && is_array($in['settings'])) {
        $s = pm_merge_settings($in['settings']);
        foreach (['header', 'footer'] as $hf) {
            foreach ($s[$hf] as $k => $v) {
                if (is_string($v)) $s[$hf][$k] = mb_substr($v, 0, 200);
            }
        }
        $breaks = [];
        foreach ((array) ($s['breaks'] ?? []) as $pg => $list) {
            if (!is_array($list) || !preg_match('/^[a-z0-9_\-]{3,40}$/i', (string) $pg)) continue;
            foreach (array_slice($list, 0, 200) as $br) {
                if (isset($br['b']) && preg_match('/^[a-z0-9_\-]{3,40}$/i', (string) $br['b'])) $breaks[$pg][] = ['b' => $br['b'], 'c' => max(0, (int) ($br['c'] ?? 0))];
            }
        }
        $s['breaks'] = $breaks;
        $s['allow_accept'] = !empty($s['allow_accept']);
        $s['allow_download'] = !empty($s['allow_download']);
        $s['require_signature'] = !empty($s['require_signature']);
        $p['settings'] = $s;
    }
    return $p;
}

function editor_state()
{
    $st = json_decode($_POST['state'] ?? '', true);
    return is_array($st) ? $st : [];
}

$needsProposal = in_array($action, ['render', 'save', 'versions', 'save_version', 'save_as_template'], true);
$row = null;
if ($needsProposal) {
    $row = db_row('SELECT * FROM proposals WHERE id = ?', [$id]);
    if (!$row) json_out(['ok' => false, 'message' => 'Proposal not found.'], 404);
}

try {
    switch ($action) {
        case 'render':
            $st = editor_state();
            $p = editor_proposal($row, $st['proposal'] ?? []);
            $pages = pm_normalize_pages($st['pages'] ?? []);
            $t = pm_compute_totals($pages);
            $p = array_merge($p, $t);
            $doc = doc_create($p, $pages, company(), 'editor');
            $only = isset($_POST['only']) ? (array) json_decode($_POST['only'], true) : null;
            $out = [];
            foreach ($pages as $i => $pg) {
                if ($only && !in_array($pg['uid'], $only, true)) continue;
                $out[$pg['uid']] = render_editor_page($doc, $pg, $i);
            }
            json_out(['ok' => true, 'pages' => $out, 'docClass' => 'pm-doc pm-screen pm-editing tpl-' . $doc['tpl']['id'], 'docStyle' => doc_css_vars($doc), 'totals' => $t, 'symbol' => $doc['symbol']]);

        case 'save':
            $st = editor_state();
            $p = editor_proposal($row, $st['proposal'] ?? []);
            if ($p['proposal_number'] === '') $p['proposal_number'] = $row['proposal_number'];
            if (db_value('SELECT id FROM proposals WHERE proposal_number = ? AND id <> ?', [$p['proposal_number'], $id])) {
                json_out(['ok' => false, 'message' => 'Another proposal already uses number ' . $p['proposal_number']], 422);
            }
            db_update('proposals', [
                'title' => $p['title'] ?: 'Proposal', 'proposal_number' => $p['proposal_number'],
                'client_name' => $p['client_name'], 'client_company' => $p['client_company'], 'client_email' => $p['client_email'],
                'client_phone' => $p['client_phone'], 'client_address' => $p['client_address'], 'client_website' => $p['client_website'],
                'template_id' => (int) $p['template_id'], 'colors' => jencode($p['colors']),
                'font_heading' => $p['font_heading'], 'font_body' => $p['font_body'], 'settings' => jencode($p['settings']),
                'currency' => $p['currency'] ?: 'USD', 'currency_symbol' => $p['currency_symbol'],
                'proposal_date' => $p['proposal_date'], 'expiry_date' => $p['expiry_date'],
            ], 'id = ?', [$id]);
            if (($row['status'] === 'expired') && $p['expiry_date'] && $p['expiry_date'] >= date('Y-m-d')) {
                db_update('proposals', ['status' => $row['sent_at'] ? 'sent' : 'draft'], 'id = ?', [$id]);
            }
            pm_save_pages($id, $st['pages'] ?? []);
            $last = db_value('SELECT created_at FROM proposal_versions WHERE proposal_id = ? ORDER BY id DESC LIMIT 1', [$id]);
            if (!$last || strtotime($last) < time() - 600) pm_snapshot($id, 'Auto-save');
            $fresh = db_row('SELECT subtotal, discount, tax, total FROM proposals WHERE id = ?', [$id]);
            json_out(['ok' => true, 'totals' => $fresh, 'saved_at' => date('H:i')]);

        case 'save_version':
            pm_snapshot($id, post('label') ?: 'Manual version');
            log_activity($id, 'version_saved', post('label') ?: 'Manual version');
            json_out(['ok' => true]);

        case 'versions':
            $rows = db_all('SELECT v.id, v.label, v.created_at, u.name FROM proposal_versions v LEFT JOIN users u ON u.id = v.created_by WHERE v.proposal_id = ? ORDER BY v.id DESC', [$id]);
            $versions = [];
            foreach ($rows as $v) {
                $versions[] = ['id' => (int) $v['id'], 'label' => $v['label'], 'when' => fmt_date($v['created_at'], 'M j, Y H:i'), 'ago' => time_ago($v['created_at']), 'by' => $v['name']];
            }
            json_out(['ok' => true, 'versions' => $versions]);

        case 'save_as_template':
            $name = post('name');
            if ($name === '') json_out(['ok' => false, 'message' => 'Name is required.'], 422);
            $pages = pm_pages($id);
            // copy pages + blocks without their ids
            $structure = [];
            foreach ($pages as $pg) {
                $blocks = [];
                foreach ($pg['blocks'] as $b) {
                    $blocks[] = ['type' => $b['type'], 'data' => $b['data'], 'hidden' => $b['hidden']];
                }
                $structure[] = ['title' => $pg['title'], 'page_type' => $pg['page_type'], 'settings' => $pg['settings'], 'blocks' => $blocks];
            }
            db_insert('service_templates', ['name' => $name, 'slug' => slugify(strtolower($name)), 'service' => post('service') ?: ($row['service_type'] ?: $name), 'description' => post('description'), 'icon' => 'file-earmark-text', 'structure' => jencode($structure), 'is_system' => 0, 'created_at' => date('Y-m-d H:i:s')]);
            json_out(['ok' => true]);

        case 'upload':
            if (empty($_FILES['file'])) json_out(['ok' => false, 'message' => 'No file uploaded.'], 422);
            $path = handle_image_upload($_FILES['file'], 'images');
            json_out(['ok' => true, 'path' => $path, 'url' => media_src($path)]);

        case 'media':
            $files = [];
            foreach (['images', 'logos'] as $dir) {
                $it = is_dir(APP_ROOT . '/uploads/' . $dir) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads/' . $dir, FilesystemIterator::SKIP_DOTS)) : [];
                foreach ($it as $f) {
                    if (preg_match('/\.(jpe?g|png|gif|svg|webp)$/i', $f->getFilename())) {
                        $rel = 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen(APP_ROOT . '/uploads/')));
                        $files[$rel] = $f->getMTime();
                    }
                }
            }
            arsort($files);
            $files = array_slice(array_keys($files), 0, 200);
            $items = [];
            foreach ($files as $path) {
                $items[] = ['path' => $path, 'url' => media_src($path)];
            }
            json_out(['ok' => true, 'items' => $items]);

        case 'library_list':
            $rows = db_all('SELECT id, name, category, description, blocks FROM content_library ORDER BY category, name');
            $items = [];
            foreach ($rows as $r) {
                $items[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'category' => $r['category'], 'count' => count(jdecode($r['blocks']))];
            }
            json_out(['ok' => true, 'items' => $items]);

        case 'library_get':
            $row = db_row('SELECT * FROM content_library WHERE id = ?', [(int) post('lib_id')]);
            if (!$row) json_out(['ok' => false, 'message' => 'Section not found.'], 404);
            $blocks = [];
            foreach (jdecode($row['blocks']) as $b) {
                if (!isset(pm_blocks()[$b['type'] ?? '']) || $b['type'] === 'cover') continue;
                $blocks[] = ['uid' => uid('b'), 'type' => $b['type'], 'data' => array_merge(pm_block_defaults($b['type']), $b['data'] ?? []), 'hidden' => false];
            }
            json_out(['ok' => true, 'blocks' => $blocks]);

        case 'library_save':
            $name = post('name');
            $blocks = json_decode($_POST['blocks'] ?? '[]', true);
            if ($name === '' || !is_array($blocks) || !$blocks) json_out(['ok' => false, 'message' => 'Name and content are required.'], 422);
            $clean = [];
            foreach ($blocks as $b) {
                if (isset(pm_blocks()[$b['type'] ?? '']) && $b['type'] !== 'cover') $clean[] = ['type' => $b['type'], 'data' => is_array($b['data'] ?? null) ? $b['data'] : []];
            }
            $lid = db_insert('content_library', ['name' => $name, 'category' => post('category') ?: 'General', 'description' => '', 'blocks' => jencode($clean), 'created_at' => date('Y-m-d H:i:s')]);
            json_out(['ok' => true, 'id' => $lid]);

        case 'preset_save':
            $name = post('name');
            $colors = json_decode($_POST['colors'] ?? '{}', true);
            if ($name === '' || !is_array($colors)) json_out(['ok' => false, 'message' => 'Name is required.'], 422);
            $clean = [];
            foreach (color_keys() as $k => $l) if (is_hex_color($colors[$k] ?? '')) $clean[$k] = strtolower($colors[$k]);
            $pid = db_insert('brand_presets', ['name' => $name, 'colors' => jencode($clean), 'font_heading' => post('font_heading') ?: null, 'font_body' => post('font_body') ?: null, 'created_at' => date('Y-m-d H:i:s')]);
            json_out(['ok' => true, 'id' => $pid, 'name' => $name, 'colors' => $clean]);

        default:
            json_out(['ok' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 500);
}
