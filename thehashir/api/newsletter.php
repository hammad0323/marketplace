<?php
/**
 * POST /api/newsletter  email, csrf
 * Subscribers are kept in storage/subscribers.json (exported from Super Admin → Subscribers).
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(array('ok' => false, 'message' => 'Method not allowed.'), 405);
}
if (!csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
    json_out(array('ok' => false, 'message' => 'Please reload the page and try again.'), 400);
}
if (!empty($_POST['website'])) {
    json_out(array('ok' => true, 'message' => 'Thank you for subscribing.'));
}
$email = isset($_POST['email']) ? strtolower(trim((string)$_POST['email'])) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    json_out(array('ok' => false, 'message' => 'Please enter a valid email address.'), 422);
}
$now = time();
$_SESSION['nl_times'] = array_values(array_filter(isset($_SESSION['nl_times']) ? $_SESSION['nl_times'] : array(), function ($t) use ($now) { return $t > $now - 3600; }));
if (count($_SESSION['nl_times']) >= 5) {
    json_out(array('ok' => false, 'message' => 'Please try again later.'), 429);
}
$_SESSION['nl_times'][] = $now;
try {
    $list = storage_read('subscribers.json', array());
    if (!isset($list[$email])) {
        $list[$email] = array('date' => date('Y-m-d H:i:s'), 'city' => visitor_city_id(), 'page' => mb_substr((string)(isset($_POST['page']) ? $_POST['page'] : ''), 0, 200));
        storage_write('subscribers.json', $list);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    json_out(array('ok' => false, 'message' => 'We could not save your subscription right now. Please try again later.'), 500);
}
json_out(array('ok' => true, 'message' => 'Thank you for subscribing. We\'ll keep you posted on events and programs near you.'));
