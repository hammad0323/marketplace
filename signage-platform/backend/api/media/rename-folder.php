<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$f = owned_row('media_folders', 'folder_id', v_int($in, 'folder_id', true, 1), $u);
db_run('UPDATE media_folders SET folder_name = ? WHERE folder_id = ?', [v_str($in, 'folder_name', true, 150), $f['folder_id']]);
ok(db_one('SELECT * FROM media_folders WHERE folder_id = ?', [$f['folder_id']]), 'Folder renamed');
