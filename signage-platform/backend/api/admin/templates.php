<?php
// GET all templates (incl. inactive). POST {action: save|delete|toggle, template_id?, ...}
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
if (request_method() === 'POST') {
    $in = input();
    $action = v_enum($in, 'action', ['save', 'delete', 'toggle'], true);
    $id = v_int($in, 'template_id', false, 1);
    if ($action === 'delete') { db_run('UPDATE screen_templates SET deleted_at = UTC_TIMESTAMP() WHERE template_id = ?', [$id]); ok(null, 'Template deleted'); }
    if ($action === 'toggle') { db_run("UPDATE screen_templates SET status = IF(status = 'active', 'inactive', 'active') WHERE template_id = ?", [$id]); ok(null, 'Template status changed'); }
    $json = $in['template_json'] ?? null;
    if (is_string($json)) $json = json_decode($json, true);
    if (!is_array($json) || !isset($json['widgets'])) fail('template_json must be a layout document with widgets', 400, 'VALIDATION');
    $orientation = v_enum($in, 'orientation', ORIENTATIONS, false, $json['canvas']['orientation'] ?? 'landscape');
    [$o, $w, $h] = canvas_for($orientation, (int)($json['canvas']['width'] ?? 0), (int)($json['canvas']['height'] ?? 0));
    $json['schema_version'] = 1; $json['canvas'] = ['orientation' => $o, 'width' => $w, 'height' => $h];
    $vals = [v_int($in, 'category_id', true, 1), v_str($in, 'template_name', true, 150), v_str($in, 'description', false, 255), $o, $w, $h, jencode($json), v_str($in, 'tags', false, 255), v_bool($in, 'is_premium', false), v_enum($in, 'status', ['active', 'inactive'], false, 'active')];
    if ($id) db_run('UPDATE screen_templates SET category_id = ?, template_name = ?, description = ?, orientation = ?, width = ?, height = ?, template_json = ?, tags = ?, is_premium = ?, status = ?, is_public = 1 WHERE template_id = ?', array_merge($vals, [$id]));
    else $id = db_insert('INSERT INTO screen_templates (category_id, template_name, description, orientation, width, height, template_json, tags, is_premium, status, template_key, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, ['admin_' . substr(random_token(6), 0, 12), $admin['user_id']]));
    log_activity((int)$admin['user_id'], 'admin.template.save', 'template', $id, 'Saved template');
    ok(shape_row(db_one('SELECT * FROM screen_templates WHERE template_id = ?', [$id]), ['template_json']), 'Template saved');
}
if ($tid = v_int(input(), 'template_id', false, 1)) {
    $t = shape_row(db_one('SELECT t.*, c.category_name FROM screen_templates t JOIN template_categories c USING (category_id) WHERE t.template_id = ? AND t.deleted_at IS NULL', [$tid]), ['template_json']);
    if (!$t) not_found('Template');
    $t['media'] = (object)template_media($t['template_json'] ?? []);
    ok($t);
}
$rows = shape_rows(db_all('SELECT t.*, c.category_name FROM screen_templates t JOIN template_categories c USING (category_id) WHERE t.deleted_at IS NULL AND t.is_public = 1 ORDER BY t.sort_order, t.template_id DESC'), ['template_json']);
foreach ($rows as &$r) $r['media'] = (object)template_media($r['template_json'] ?? []);
ok($rows);
