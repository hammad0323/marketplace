<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
require_permission($u, 'media.view');
$in = input();
[$page, $per, $off] = pagination();
$uid = (int)$u['user_id'];
$where = ['m.deleted_at IS NULL'];
$params = [];
if (is_admin($u) && v_bool($in, 'all', false)) {
    if ($o = v_int($in, 'user_id', false, 1)) { $where[] = 'm.user_id = ?'; $params[] = $o; }
} elseif (v_bool($in, 'include_global', true)) {
    $where[] = '(m.user_id = ? OR m.is_global = 1)'; $params[] = $uid;
} else { $where[] = 'm.user_id = ?'; $params[] = $uid; }
if ($t = v_enum($in, 'file_type', ['image', 'video', 'document', 'logo', 'audio'])) { $where[] = 'm.file_type = ?'; $params[] = $t; }
if ($q = v_str($in, 'q', false, 100)) { $where[] = 'm.original_name LIKE ?'; $params[] = "%{$q}%"; }
if (array_key_exists('folder_id', $in) && $in['folder_id'] !== '' && $in['folder_id'] !== null) {
    $fid = v_int($in, 'folder_id', false, 0);
    if ($fid === 0) $where[] = 'm.folder_id IS NULL'; else { $where[] = 'm.folder_id = ?'; $params[] = $fid; }
}
$w = implode(' AND ', $where);
$sort = ['created_at' => 'm.created_at', 'original_name' => 'm.original_name', 'file_size' => 'm.file_size'][$in['sort'] ?? 'created_at'] ?? 'm.created_at';
$order = strtolower((string)($in['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$total = (int)db_val("SELECT COUNT(*) FROM media_library m WHERE {$w}", $params);
$rows = db_all("SELECT m.*, (SELECT COUNT(*) FROM widget_assets wa WHERE wa.media_id = m.media_id) + (SELECT COUNT(*) FROM screens s WHERE s.background_media_id = m.media_id AND s.deleted_at IS NULL) AS usage_count
                  FROM media_library m WHERE {$w} ORDER BY m.is_global ASC, {$sort} {$order} LIMIT ? OFFSET ?", array_merge($params, [$per, $off]));
$folders = db_all('SELECT f.*, (SELECT COUNT(*) FROM media_library m WHERE m.folder_id = f.folder_id AND m.deleted_at IS NULL) AS item_count FROM media_folders f WHERE f.user_id = ? ORDER BY f.folder_name', [$uid]);
ok(array_map('present_media', $rows), 'OK', 200, [
    'meta' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'total_pages' => (int)ceil($total / $per)],
    'folders' => $folders,
    'storage' => ['used_bytes' => user_usage($uid)['storage_used_bytes'], 'limit_mb' => effective_limits($uid)['max_storage_mb']],
]);
