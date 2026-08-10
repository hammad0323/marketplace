<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$afterId = (int) ($_GET['after_id'] ?? 0);
$userId = (int) current_user_id();
$role = current_user_role();

$conversation = db_select_one($conn, 'SELECT c.*, p.user_id AS provider_user_id FROM conversations c JOIN providers p ON p.id = c.provider_id WHERE c.id = ?', [$conversationId]);
$isParty = $conversation && (($role === 'customer' && (int) $conversation['customer_id'] === $userId) || ($role === 'provider' && (int) $conversation['provider_user_id'] === $userId));
if (!$isParty) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$rows = db_select($conn, 'SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC', [$conversationId, $afterId]);
db_execute($conn, 'UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?', [$conversationId, $userId]);

$items = [];
foreach ($rows as $m) {
    $items[] = [
        'id' => (int) $m['id'],
        'message_text' => e($m['message_text']),
        'created_at' => $m['created_at'],
        'mine' => (int) $m['sender_id'] === $userId,
    ];
}
echo json_encode(['ok' => true, 'items' => $items]);
