<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$parent = v_int($in, 'parent_folder_id', false, 1);
if ($parent) owned_row('media_folders', 'folder_id', $parent, $u);
$id = db_insert('INSERT INTO media_folders (user_id, folder_name, parent_folder_id) VALUES (?, ?, ?)', [$u['user_id'], v_str($in, 'folder_name', true, 150), $parent]);
ok(db_one('SELECT * FROM media_folders WHERE folder_id = ?', [$id]), 'Folder created', 201);
