<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'templates.save_own');
$in = input();
$s = owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $u);
$layout = screen_layout($s);
foreach ($layout['widgets'] as &$w) unset($w['widget_id'], $w['widget_uid']);
unset($w);
$cat = v_int($in, 'category_id', false, 1) ?? (int)db_val("SELECT category_id FROM template_categories WHERE category_key = 'minimal'");
$key = 'user_' . $u['user_id'] . '_' . substr(random_token(6), 0, 10);
$isPublic = is_admin($u) && v_bool($in, 'is_public', false) ? 1 : 0;
$id = db_insert('INSERT INTO screen_templates (category_id, template_key, template_name, description, orientation, width, height, template_json, is_premium, created_by, is_public)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
    [$cat, $key, v_str($in, 'template_name', false, 150) ?? $s['screen_name'], v_str($in, 'description', false, 255), $s['orientation'], $s['width'], $s['height'], jencode($layout), $u['user_id'], $isPublic]);
log_activity((int)$u['user_id'], 'template.create', 'template', $id, 'Saved screen as template');
ok(shape_row(db_one('SELECT * FROM screen_templates WHERE template_id = ?', [$id]), ['template_json']), 'Saved as template', 201);
