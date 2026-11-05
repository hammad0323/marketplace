<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../devices/_common.php';
require_admin();
$in = input();
[$page, $per, $off] = pagination();
$where = ['d.deleted_at IS NULL']; $params = [];
if ($q = v_str($in, 'q', false, 100)) { $where[] = '(d.device_name LIKE ? OR d.device_code LIKE ? OR u.email LIKE ?)'; array_push($params, "%{$q}%", "%{$q}%", "%{$q}%"); }
if (v_bool($in, 'paired_only', true)) $where[] = 'd.user_id IS NOT NULL';
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM devices d LEFT JOIN users u ON u.user_id = d.user_id WHERE {$w}", $params);
paged(array_map('present_device', db_all(device_select_sql() . " WHERE {$w} ORDER BY d.last_heartbeat DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off]))), $total, $page, $per);
