<?php
/** GET /api/cities?q=Missi  — city search for the location picker and filters (read-only). */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$q = str_param('q', 60);
$q = preg_replace('/\b(city|division|district|municipality)\b/i', '', $q);
$q = trim($q);
if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(array('results' => array()));
    exit;
}

if (!empty($_GET['all'])) {
    // Every city (back office), with the number of published listings
    $rows = db_all('SELECT c.city_id, c.city_title, s.state_title, co.country_title,
            (SELECT COUNT(*) FROM th_venue v WHERE v.city_id = c.city_id AND v.verify_status = 1) n
        FROM th_city c LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
        WHERE c.city_title LIKE ? ORDER BY (c.city_title LIKE ?) DESC, c.city_title LIMIT 20', 'ss', array('%' . $q . '%', $q . '%'));
} elseif (!empty($_GET['exact'])) {
    $rows = db_all('SELECT c.city_id, c.city_title, s.state_title, co.country_title, COUNT(v.venue_id) n
        FROM th_city c LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
        JOIN th_venue v ON v.city_id = c.city_id AND v.verify_status = 1
        WHERE c.city_title = ? GROUP BY c.city_id ORDER BY n DESC LIMIT 5', 's', array($q));
} else {
    $rows = search_cities($q, 15);
}

$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'city_id' => (int)$r['city_id'],
        'city_title' => $r['city_title'],
        'state_title' => (string)$r['state_title'],
        'country_title' => (string)$r['country_title'],
        'n' => (int)$r['n'],
    );
}
echo json_encode(array('results' => $out), JSON_UNESCAPED_UNICODE);
