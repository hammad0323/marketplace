<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$uid = (int)$u['user_id'];
$by = db_all('SELECT file_type, COUNT(*) AS files, COALESCE(SUM(file_size),0) AS bytes FROM media_library WHERE user_id = ? AND deleted_at IS NULL GROUP BY file_type', [$uid]);
ok(['used_bytes' => user_usage($uid)['storage_used_bytes'], 'limit_mb' => effective_limits($uid)['max_storage_mb'], 'by_type' => $by]);
