<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$l = owned_row('locations', 'location_id', v_int(input(), 'location_id', true, 1), $u);
db_run('DELETE FROM locations WHERE location_id = ?', [$l['location_id']]);
ok(null, 'Location deleted');
