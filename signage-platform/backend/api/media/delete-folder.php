<?php
// Files inside are kept and moved to the library root.
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$f = owned_row('media_folders', 'folder_id', v_int(input(), 'folder_id', true, 1), $u);
db_run('DELETE FROM media_folders WHERE folder_id = ?', [$f['folder_id']]);
ok(null, 'Folder deleted. Its files were moved to All media.');
