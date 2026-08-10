<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false]);
    exit;
}

$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$text = trim((string) ($_POST['message_text'] ?? ''));
if (!$conversationId || $text === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

$userId = (int) current_user_id();
$role = current_user_role();
$conversation = db_select_one($conn, 'SELECT c.*, p.user_id AS provider_user_id FROM conversations c JOIN providers p ON p.id = c.provider_id WHERE c.id = ?', [$conversationId]);

$isParty = $conversation && (($role === 'customer' && (int) $conversation['customer_id'] === $userId) || ($role === 'provider' && (int) $conversation['provider_user_id'] === $userId));
if (!$isParty) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$messageId = db_insert_get_id($conn, 'INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)', [$conversationId, $userId, $text]);
db_execute($conn, 'UPDATE conversations SET last_message_at = NOW() WHERE id = ?', [$conversationId]);

$recipientId = $role === 'customer' ? (int) $conversation['provider_user_id'] : (int) $conversation['customer_id'];
$recipientLink = $role === 'customer' ? '/provider/messages.php?conversation_id=' . $conversationId : '/customer/messages.php?conversation_id=' . $conversationId;
db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "new_message", "New message", ?, ?)', [$recipientId, mb_substr($text, 0, 80), $recipientLink]);

$recipient = db_select_one($conn, 'SELECT name, email FROM users WHERE id = ?', [$recipientId]);
$sender = current_user($conn);
if ($recipient) {
    send_email($conn, $recipient['email'], $recipient['name'], 'new_message', ['name' => $recipient['name'], 'sender_name' => $sender['name']]);
}

echo json_encode([
    'ok' => true,
    'message' => [
        'id' => $messageId,
        'sender_id' => $userId,
        'message_text' => e($text),
        'created_at' => date('Y-m-d H:i:s'),
        'mine' => true,
    ],
]);
