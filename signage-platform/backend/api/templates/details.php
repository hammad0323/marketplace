<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$t = db_one("SELECT t.*, c.category_name FROM screen_templates t JOIN template_categories c USING (category_id)
              WHERE t.template_id = ? AND t.deleted_at IS NULL AND (t.is_public = 1 OR t.created_by = ?)", [v_int(input(), 'template_id', true, 1), $u['user_id']]);
if (!$t) not_found('Template');
ok(shape_row($t, ['template_json']));
