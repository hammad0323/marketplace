<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
require_permission($u, 'screens.view');
$in = input();
[$page, $per, $off] = pagination();
$where = ['s.deleted_at IS NULL'];
$params = [];
if (is_admin($u) && v_bool($in, 'all', false)) {
    if ($ownerId = v_int($in, 'user_id', false, 1)) { $where[] = 's.user_id = ?'; $params[] = $ownerId; }
} else { $where[] = 's.user_id = ?'; $params[] = (int)$u['user_id']; }
if ($q = v_str($in, 'q', false, 100)) { $where[] = 's.screen_name LIKE ?'; $params[] = '%' . $q . '%'; }
if ($st = v_enum($in, 'status', ['draft', 'published', 'archived'])) { $where[] = 's.status = ?'; $params[] = $st; }
if ($o = v_enum($in, 'orientation', ORIENTATIONS)) { $where[] = 's.orientation = ?'; $params[] = $o; }
if (($fid = v_int($in, 'folder_id', false, 0)) !== null) { if ($fid === 0) $where[] = 's.folder_id IS NULL'; else { $where[] = 's.folder_id = ?'; $params[] = $fid; } }
if (v_bool($in, 'favorites', false)) $where[] = 's.is_favorite = 1';
$sortMap = ['updated_at' => 's.updated_at', 'created_at' => 's.created_at', 'screen_name' => 's.screen_name'];
$sort = $sortMap[$in['sort'] ?? 'updated_at'] ?? 's.updated_at';
$order = strtolower((string)($in['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM screens s WHERE {$w}", $params);
$rows = db_all("SELECT s.*, u.full_name AS owner_name, (SELECT COUNT(*) FROM screen_widgets sw WHERE sw.screen_id = s.screen_id) AS widget_count
                  FROM screens s JOIN users u ON u.user_id = s.user_id WHERE {$w} ORDER BY {$sort} {$order}, s.screen_id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$per, $off]));
$withLayout = v_bool($in, 'with_layout', true);
paged(array_map(fn($s) => present_screen($s, $withLayout), $rows), $total, $page, $per);
