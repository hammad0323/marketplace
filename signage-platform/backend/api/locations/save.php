<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$vals = [v_str($in, 'location_name', true, 150), v_str($in, 'address', false, 255), v_str($in, 'city', false, 100), v_str($in, 'country', false, 100),
    v_num($in, 'latitude', false, -90, 90), v_num($in, 'longitude', false, -180, 180), v_timezone($in, 'timezone')];
if ($id = v_int($in, 'location_id', false, 1)) {
    owned_row('locations', 'location_id', $id, $u);
    db_run('UPDATE locations SET location_name = ?, address = ?, city = ?, country = ?, latitude = ?, longitude = ?, timezone = ? WHERE location_id = ?', array_merge($vals, [$id]));
} else {
    $id = db_insert('INSERT INTO locations (location_name, address, city, country, latitude, longitude, timezone, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [$u['user_id']]));
}
ok(db_one('SELECT * FROM locations WHERE location_id = ?', [$id]), 'Location saved');
