<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$m = owned_row('media_library', 'media_id', v_int($in, 'media_id', true, 1), $u);
db_run('UPDATE media_library SET original_name = ? WHERE media_id = ?', [v_str($in, 'original_name', true, 255), $m['media_id']]);
ok(present_media(db_one('SELECT * FROM media_library WHERE media_id = ?', [$m['media_id']])), 'Renamed');
