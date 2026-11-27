<?php
/**
 * GET /api/map?type=mosques|organizations|all[&lat=&lng=&limit=]
 * Venues that have confirmed coordinates. With lat/lng: nearest first, with distance in km.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$type = str_param('type', 20);
$role = $type === 'organizations' ? 'all' : ($type === 'all' ? 'all' : 1);
$rows = venues_with_coords($role);
if ($type === 'organizations') {
    $rows = array_values(array_filter($rows, function ($r) { return $r['role_id'] !== 1; }));
}
$lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;
if ($lat !== null && $lng !== null && valid_lat_lng($lat, $lng)) {
    foreach ($rows as $i => $r) {
        $rows[$i]['km'] = round(distance_km($lat, $lng, $r['lat'], $r['lng']), 1);
    }
    usort($rows, function ($a, $b) { return $a['km'] < $b['km'] ? -1 : ($a['km'] > $b['km'] ? 1 : 0); });
    $limit = max(1, min(100, int_param('limit', 20)));
    $rows = array_slice($rows, 0, $limit);
}
echo json_encode(array('results' => $rows, 'count' => count($rows)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
