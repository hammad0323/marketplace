<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$ids = v_int_list($in, 'media_ids', true);
$folder = v_int($in, 'folder_id', false, 0);
if ($folder) owned_row('media_folders', 'folder_id', $folder, $u);
$n = db_exec('UPDATE media_library SET folder_id = ? WHERE user_id = ? AND deleted_at IS NULL AND media_id IN (' . placeholders($ids) . ')',
    array_merge([$folder ?: null, $u['user_id']], $ids));
ok(['moved' => $n], "Moved {$n} file(s)");
