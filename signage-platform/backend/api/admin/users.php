<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$u = require_admin();
$in = input();
[$page, $per, $off] = pagination();
$where = ['u.deleted_at IS NULL']; $params = [];
if ($q = v_str($in, 'q', false, 100)) { $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.business_name LIKE ?)'; array_push($params, "%{$q}%", "%{$q}%", "%{$q}%"); }
if ($st = v_enum($in, 'status', ['active', 'inactive', 'suspended'])) { $where[] = 'u.status = ?'; $params[] = $st; }
if ($r = v_enum($in, 'role', ['super_admin', 'user'])) { $where[] = 'r.role_key = ?'; $params[] = $r; }
if ($pl = v_int($in, 'plan_id', false, 1)) { $where[] = "EXISTS (SELECT 1 FROM user_subscriptions s WHERE s.user_id = u.user_id AND s.status = 'active' AND s.plan_id = ?)"; $params[] = $pl; }
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM users u JOIN roles r USING (role_id) WHERE {$w}", $params);
$rows = db_all("SELECT u.*, r.role_key FROM users u JOIN roles r USING (role_id) WHERE {$w} ORDER BY u.user_id DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off]));
paged(array_map(fn($r) => admin_present_user($r), $rows), $total, $page, $per);
