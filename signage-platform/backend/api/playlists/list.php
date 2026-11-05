<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('GET');
$u = require_user();
require_permission($u, 'playlists.view');
$in = input();
[$page, $per, $off] = pagination();
$where = ['p.deleted_at IS NULL']; $params = [];
if (is_admin($u) && v_bool($in, 'all', false)) { if ($o = v_int($in, 'user_id', false, 1)) { $where[] = 'p.user_id = ?'; $params[] = $o; } }
else { $where[] = 'p.user_id = ?'; $params[] = (int)$u['user_id']; }
if ($q = v_str($in, 'q', false, 100)) { $where[] = 'p.playlist_name LIKE ?'; $params[] = "%{$q}%"; }
if ($st = v_enum($in, 'status', ['draft', 'published', 'paused', 'archived'])) { $where[] = 'p.status = ?'; $params[] = $st; }
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM playlists p WHERE {$w}", $params);
$rows = db_all("SELECT p.*,
                  (SELECT COUNT(*) FROM playlist_items pi JOIN screens s USING (screen_id) WHERE pi.playlist_id = p.playlist_id AND s.deleted_at IS NULL) AS item_count,
                  (SELECT COALESCE(SUM(COALESCE(pi.duration, s.default_duration)),0) FROM playlist_items pi JOIN screens s USING (screen_id) WHERE pi.playlist_id = p.playlist_id AND pi.is_enabled = 1 AND s.deleted_at IS NULL) AS total_duration,
                  (SELECT COUNT(*) FROM device_playlists dp WHERE dp.playlist_id = p.playlist_id) AS device_count,
                  (SELECT COUNT(*) FROM playlist_schedules ps WHERE ps.playlist_id = p.playlist_id AND ps.status = 'active') AS schedule_count
                  FROM playlists p WHERE {$w} ORDER BY p.updated_at DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off]));
paged(array_map(fn($p) => present_playlist($p, v_bool($in, 'with_items', true)), $rows), $total, $page, $per);
