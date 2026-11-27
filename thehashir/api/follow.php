<?php
/**
 * POST /api/follow  venue_id, follow (1|0), csrf
 * Uses the existing mosque_followers table (user_type 'device'), the same table the app uses.
 * A random browser id (cookie th_dev) identifies the visitor; no personal data is stored.
 * GET /api/follow?venue_id=  -> current state + follower count
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$venueId = isset($_REQUEST['venue_id']) && ctype_digit((string)$_REQUEST['venue_id']) ? (int)$_REQUEST['venue_id'] : 0;
if (!$venueId || !db_val('SELECT venue_id FROM th_venue WHERE venue_id = ? AND verify_status = 1', 'i', array($venueId))) {
    json_out(array('ok' => false, 'message' => 'Unknown profile.'), 404);
}
$dev = isset($_COOKIE['th_dev']) && preg_match('/^web-[a-f0-9]{32}$/', $_COOKIE['th_dev']) ? $_COOKIE['th_dev'] : '';

function follow_state($venueId, $dev)
{
    $count = (int)db_val('SELECT COUNT(*) FROM mosque_followers WHERE venue_id = ? AND follow_status = 1', 's', array((string)$venueId));
    $mine = $dev !== '' && (int)db_val('SELECT follow_status FROM mosque_followers WHERE venue_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1', 'ss', array((string)$venueId, $dev)) === 1;
    return array('ok' => true, 'following' => $mine, 'count' => $count);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(follow_state($venueId, $dev));
}
if (!csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
    json_out(array('ok' => false, 'message' => 'Please reload the page and try again.'), 400);
}
// Max 30 follow changes per session per hour
$now = time();
$_SESSION['follow_times'] = array_values(array_filter(isset($_SESSION['follow_times']) ? $_SESSION['follow_times'] : array(), function ($t) use ($now) { return $t > $now - 3600; }));
if (count($_SESSION['follow_times']) >= 30) {
    json_out(array('ok' => false, 'message' => 'Please try again later.'), 429);
}
$_SESSION['follow_times'][] = $now;

if ($dev === '') {
    $dev = 'web-' . bin2hex(random_bytes(16));
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('th_dev', $dev, time() + 86400 * 730, BASE_PATH . '/', '', $secure, true);
}
$follow = !empty($_POST['follow']) ? 1 : 0;
$existing = db_val('SELECT id FROM mosque_followers WHERE venue_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1', 'ss', array((string)$venueId, $dev));
if ($existing) {
    db_exec('UPDATE mosque_followers SET follow_status = ? WHERE id = ?', 'ii', array($follow, (int)$existing));
} elseif ($follow) {
    db_exec("INSERT INTO mosque_followers (venue_id, user_id, user_type, device_info, follow_status) VALUES (?, ?, 'device', 'web browser', 1)", 'ss', array((string)$venueId, $dev));
}
json_out(follow_state($venueId, $dev));
