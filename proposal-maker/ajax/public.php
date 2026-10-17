<?php
/**
 * CLIENT ANSWERS (AJAX) from the public page: accept (with signature) or reject.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$p = pm_get_by_token((string) ($_POST['token'] ?? ''));
if (!$p) json_out(['ok' => false, 'message' => 'Proposal not found.'], 404);
$settings = pm_merge_settings($p['settings']);
$action = $_POST['action'] ?? '';

if (empty($settings['allow_accept'])) json_out(['ok' => false, 'message' => 'Online responses are disabled for this proposal.'], 403);
if (db_value('SELECT id FROM proposal_responses WHERE proposal_id = ?', [(int) $p['id']])) json_out(['ok' => false, 'message' => 'This proposal has already been answered.'], 409);
if ($p['status'] === 'expired' || ($p['expiry_date'] && $p['expiry_date'] < date('Y-m-d'))) json_out(['ok' => false, 'message' => 'This proposal has expired.'], 410);

$name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 190);
$email = trim((string) ($_POST['email'] ?? ''));
$comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 3000);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'message' => 'Please enter your name and a valid email.'], 422);

$row = [
    'proposal_id' => (int) $p['id'], 'name' => $name, 'email' => $email, 'comment' => $comment,
    'ip_address' => client_ip(), 'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), 'created_at' => date('Y-m-d H:i:s'),
];

if ($action === 'accept') {
    $type = (string) ($_POST['signature_type'] ?? '');
    $sig = (string) ($_POST['signature'] ?? '');
    $row['response'] = 'accepted';
    $row['signature_type'] = '';
    if ($type === 'typed' && $sig !== '') {
        $row['signature_type'] = 'typed';
        $row['signature_text'] = mb_substr($sig, 0, 190);
    } elseif (in_array($type, ['drawn', 'uploaded'], true) && $sig !== '') {
        $path = save_data_png($sig);
        if (!$path) json_out(['ok' => false, 'message' => 'The signature image could not be read.'], 422);
        $row['signature_type'] = $type;
        $row['signature_data'] = $path;
    }
    if (!empty($settings['require_signature']) && $row['signature_type'] === '') json_out(['ok' => false, 'message' => 'A signature is required.'], 422);
    $status = 'accepted';
} elseif ($action === 'reject') {
    if ($comment === '') json_out(['ok' => false, 'message' => 'Please tell us why you are rejecting the proposal.'], 422);
    $row['response'] = 'rejected';
    $status = 'rejected';
} else {
    json_out(['ok' => false, 'message' => 'Unknown action.'], 400);
}

db_insert('proposal_responses', $row);
db_update('proposals', ['status' => $status, 'responded_at' => date('Y-m-d H:i:s')], 'id = ?', [(int) $p['id']]);
log_activity((int) $p['id'], $status, ucfirst($status) . ' by ' . $name . ' <' . $email . '>', 0);
require_once APP_ROOT . '/includes/mailer.php';
pm_notify_response($p, $status, $name, $comment);
json_out(['ok' => true]);
