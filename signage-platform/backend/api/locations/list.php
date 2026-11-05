<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
ok(db_all('SELECT l.*, (SELECT COUNT(*) FROM devices d WHERE d.location_id = l.location_id AND d.deleted_at IS NULL) AS device_count FROM locations l WHERE l.user_id = ? ORDER BY l.location_name', [$u['user_id']]));
