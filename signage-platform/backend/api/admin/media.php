<?php
// Admin media overview across users. POST {media_id, action: delete|make_global|make_private}
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
if (request_method() === 'POST') {
    $in = input();
    $id = v_int($in, 'media_id', true, 1);
    $a = v_enum($in, 'action', ['delete', 'make_global', 'make_private'], true);
    if ($a === 'delete') db_run('UPDATE media_library SET deleted_at = UTC_TIMESTAMP() WHERE media_id = ?', [$id]);
    else db_run('UPDATE media_library SET is_global = ? WHERE media_id = ?', [$a === 'make_global', $id]);
    log_activity((int)$admin['user_id'], 'admin.media.' . $a, 'media', $id, "Media {$a}");
    ok(null, 'Updated');
}
$in = input();
[$page, $per, $off] = pagination();
$where = 'm.deleted_at IS NULL'; $params = [];
if ($q = v_str($in, 'q', false, 100)) { $where .= ' AND (m.original_name LIKE ? OR u.email LIKE ?)'; array_push($params, "%{$q}%", "%{$q}%"); }
if ($t = v_str($in, 'file_type', false, 20)) { $where .= ' AND m.file_type = ?'; $params[] = $t; }
$total = (int)db_val("SELECT COUNT(*) FROM media_library m LEFT JOIN users u ON u.user_id = m.user_id WHERE {$where}", $params);
$rows = db_all("SELECT m.*, u.full_name AS owner_name FROM media_library m LEFT JOIN users u ON u.user_id = m.user_id WHERE {$where} ORDER BY m.media_id DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off]));
paged(array_map('present_media', $rows), $total, $page, $per);
