<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_admin();
ok(db_all('SELECT p.*, EXISTS(SELECT 1 FROM role_permissions rp JOIN roles r USING (role_id) WHERE rp.permission_id = p.permission_id AND r.role_key = "user") AS user_role_default FROM permissions p ORDER BY module, permission_id'));
