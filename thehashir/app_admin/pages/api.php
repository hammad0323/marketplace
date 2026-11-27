<?php
/**
 * /manage/api?do=venues|venue-cards|details|upload — JSON helpers for the back office (admin session required).
 */
require_admin('dashboard');
$do = str_param('do', 20);
if ($do === 'venues') {
    $q = str_param('q', 80);
    $role = str_param('role', 5);
    $ids = isset($_GET['ids']) ? array_filter(array_map('intval', explode(',', (string)$_GET['ids']))) : array();
    $where = '1=1';
    $types = '';
    $params = array();
    if ($ids) {
        list($ph, $t, $vals) = db_in_ints($ids);
        $where = "v.venue_id IN ($ph)";
        $types = $t;
        $params = $vals;
    } else {
        if ($q !== '') {
            $where .= ' AND (v.venue_title LIKE ? OR v.venue_aka LIKE ? OR c.city_title LIKE ? OR v.venue_id = ?)';
            $types .= 'sssi';
            array_push($params, '%' . $q . '%', '%' . $q . '%', $q . '%', ctype_digit($q) ? (int)$q : 0);
        }
        if ($role === '1') {
            $where .= ' AND v.role_id = 1';
        } elseif ($role === 'org') {
            $where .= ' AND v.role_id <> 1';
        }
    }
    $rows = db_all("SELECT v.venue_id, v.venue_title, v.role_id, v.verify_status, v.profile_image, v.address_iframe, v.street_address, c.city_title, s.state_title
        FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id
        WHERE $where ORDER BY (v.venue_title LIKE ?) DESC, v.verify_status DESC, v.venue_title LIMIT 30", $types . 's', array_merge($params, array($q . '%')));
    $out = array();
    foreach ($rows as $r) {
        $c = venue_coords($r);
        $out[] = array('id' => (int)$r['venue_id'], 'text' => trim($r['venue_title']) . ($r['city_title'] ? ' — ' . $r['city_title'] : '') . ((int)$r['verify_status'] !== 1 ? ' (hidden)' : ''),
            'title' => trim($r['venue_title']), 'city' => trim($r['city_title'] . ', ' . $r['state_title'], ', '), 'address' => trim((string)$r['street_address']),
            'logo' => venue_logo($r), 'lat' => $c ? $c['lat'] : null, 'lng' => $c ? $c['lng'] : null, 'published' => (int)$r['verify_status'] === 1, 'mosque' => (int)$r['role_id'] === 1);
    }
    json_out(array('results' => $out));
}
if ($do === 'details') {
    $q = str_param('q', 80);
    $gc = int_param('gc');
    $rows = db_all('SELECT d.detail_id, d.details_title, v.venue_title FROM th_details d LEFT JOIN th_venue v ON v.venue_id = d.venue_id WHERE d.grand_category_id = ? AND d.status_verify = 1 AND d.details_title LIKE ? ORDER BY d.details_title LIMIT 30', 'is', array($gc, '%' . $q . '%'));
    $out = array();
    foreach ($rows as $r) {
        $out[] = array('id' => (int)$r['detail_id'], 'text' => trim($r['details_title']) . ' — ' . trim((string)$r['venue_title']));
    }
    json_out(array('results' => $out));
}
if ($do === 'upload') {
    require_post_csrf();
    require_admin('blogs');
    require_once __DIR__ . '/../../includes/uploads.php';
    $up = upload_image('file', 'blogs');
    if (!$up['ok'] || $up['file'] === '') {
        json_out(array('ok' => false, 'message' => $up['ok'] ? 'Choose an image.' : $up['error']), 422);
    }
    log_me('media.upload', 'media', null, null, array('file' => 'images/blogs/' . $up['file']));
    json_out(array('ok' => true, 'path' => 'images/blogs/' . $up['file'], 'url' => media('images/blogs/' . $up['file'])));
}
json_out(array('ok' => false, 'message' => 'Unknown action'), 404);
