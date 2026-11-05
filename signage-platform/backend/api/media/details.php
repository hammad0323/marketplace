<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$id = v_int(input(), 'media_id', true, 1);
$m = db_one('SELECT * FROM media_library WHERE media_id = ? AND deleted_at IS NULL AND (user_id = ? OR is_global = 1 OR ? = 1)', [$id, $u['user_id'], is_admin($u) ? 1 : 0]);
if (!$m) not_found('Media');
$out = present_media($m);
$out['used_in'] = db_all('SELECT DISTINCT s.screen_id, s.screen_name FROM screens s
                          LEFT JOIN screen_widgets sw ON sw.screen_id = s.screen_id
                          LEFT JOIN widget_assets wa ON wa.widget_id = sw.widget_id
                          WHERE s.deleted_at IS NULL AND (wa.media_id = ? OR s.background_media_id = ?)', [$id, $id]);
ok($out);
