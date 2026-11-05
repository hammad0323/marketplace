<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$tid = v_int(input(), 'template_id', true, 1);
if (!db_val('SELECT 1 FROM screen_templates WHERE template_id = ? AND deleted_at IS NULL', [$tid])) not_found('Template');
if (db_exec('DELETE FROM template_favorites WHERE user_id = ? AND template_id = ?', [$u['user_id'], $tid]) > 0) ok(['is_favorite' => false], 'Removed from favourites');
db_run('INSERT INTO template_favorites (user_id, template_id) VALUES (?, ?)', [$u['user_id'], $tid]);
ok(['is_favorite' => true], 'Added to favourites');
