<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('GET');
$u = require_user();
require_permission($u, 'devices.view');
$in = input();
[$page, $per, $off] = pagination();
$where = ['d.deleted_at IS NULL']; $params = [];
if (is_admin($u) && v_bool($in, 'all', false)) { if ($o = v_int($in, 'user_id', false, 1)) { $where[] = 'd.user_id = ?'; $params[] = $o; } }
else { $where[] = 'd.user_id = ?'; $params[] = (int)$u['user_id']; }
if ($q = v_str($in, 'q', false, 100)) { $where[] = '(d.device_name LIKE ? OR d.device_code LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
if ($l = v_int($in, 'location_id', false, 1)) { $where[] = 'd.location_id = ?'; $params[] = $l; }
$off_s = (int)app_setting('offline_after_seconds', 180);
if (($st = v_enum($in, 'connection', ['online', 'offline'])) === 'online') { $where[] = 'd.last_heartbeat >= UTC_TIMESTAMP() - INTERVAL ? SECOND'; $params[] = $off_s; }
elseif ($st === 'offline') { $where[] = '(d.last_heartbeat IS NULL OR d.last_heartbeat < UTC_TIMESTAMP() - INTERVAL ? SECOND)'; $params[] = $off_s; }
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM devices d WHERE {$w}", $params);
$rows = db_all(device_select_sql() . " WHERE {$w} ORDER BY d.device_name LIMIT ? OFFSET ?", array_merge($params, [$per, $off]));
paged(array_map('present_device', $rows), $total, $page, $per);
