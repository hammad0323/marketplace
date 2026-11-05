<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();
$in = input();
[$page, $per, $off] = pagination();
$where = ['1=1']; $params = [];
if ($uid = v_int($in, 'user_id', false, 1)) { $where[] = 'a.user_id = ?'; $params[] = $uid; }
if ($a = v_str($in, 'action', false, 80)) { $where[] = 'a.action LIKE ?'; $params[] = $a . '%'; }
if ($f = v_date($in, 'from')) { $where[] = 'a.created_at >= ?'; $params[] = "{$f} 00:00:00"; }
if ($t = v_date($in, 'to')) { $where[] = 'a.created_at <= ?'; $params[] = "{$t} 23:59:59"; }
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM activity_logs a WHERE {$w}", $params);
paged(shape_rows(db_all("SELECT a.*, u.full_name, u.email FROM activity_logs a LEFT JOIN users u USING (user_id) WHERE {$w} ORDER BY a.activity_id DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off])), ['meta_json']), $total, $page, $per);
