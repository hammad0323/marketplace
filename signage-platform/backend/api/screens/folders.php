<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$uid = (int)$u['user_id'];
if (request_method() === 'GET') {
    ok(db_all('SELECT f.*, (SELECT COUNT(*) FROM screens s WHERE s.folder_id = f.folder_id AND s.deleted_at IS NULL) AS screen_count
                 FROM screen_folders f WHERE f.user_id = ? ORDER BY f.sort_order, f.folder_name', [$uid]));
}
require_method('POST');
$in = input();
$action = v_enum($in, 'action', ['create', 'rename', 'delete'], true);
if ($action === 'create') {
    $id = db_insert('INSERT INTO screen_folders (user_id, folder_name, color) VALUES (?, ?, ?)', [$uid, v_str($in, 'folder_name', true, 150), v_color($in, 'color')]);
    ok(db_one('SELECT * FROM screen_folders WHERE folder_id = ?', [$id]), 'Folder created', 201);
}
$f = owned_row('screen_folders', 'folder_id', v_int($in, 'folder_id', true, 1), $u);
if ($action === 'rename') {
    db_run('UPDATE screen_folders SET folder_name = ?, color = COALESCE(?, color) WHERE folder_id = ?', [v_str($in, 'folder_name', true, 150), v_color($in, 'color'), $f['folder_id']]);
    ok(db_one('SELECT * FROM screen_folders WHERE folder_id = ?', [$f['folder_id']]), 'Folder renamed');
}
db_run('DELETE FROM screen_folders WHERE folder_id = ?', [$f['folder_id']]);   // screens fall back to "no folder"
ok(null, 'Folder deleted');
