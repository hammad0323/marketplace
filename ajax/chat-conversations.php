<?php
require __DIR__ . '/../config/config.php';

if (!is_logged_in() || !in_array(current_role(), ['patient', 'doctor'], true)) {
    json_response(false, [], 'Please log in to view messages.');
}

$db = db();
$role = current_role();
$profileId = current_profile_id();
$myUserId = (int) $_SESSION['user_id'];

if ($role === 'patient') {
    $sql = "SELECT c.id AS conversation_id, c.last_message_at, d.id AS doctor_id, d.chat_enabled, d.chat_start_time, d.chat_end_time,
            u.full_name, u.avatar, u.last_active_at,
            (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
        FROM chat_conversations c
        JOIN doctors d ON d.id = c.doctor_id
        JOIN users u ON u.id = d.user_id
        WHERE c.patient_id = ?
        ORDER BY c.last_message_at DESC";
} else {
    $sql = "SELECT c.id AS conversation_id, c.last_message_at, p.id AS patient_id,
            u.full_name, u.avatar,
            (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
            (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count
        FROM chat_conversations c
        JOIN patients p ON p.id = c.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE c.doctor_id = ?
        ORDER BY c.last_message_at DESC";
}

$stmt = mysqli_prepare($db, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $myUserId, $profileId);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$conversations = array_map(function ($r) use ($role) {
    $item = [
        'conversation_id' => (int) $r['conversation_id'],
        'name' => $r['full_name'],
        'avatar' => avatar_url($r['avatar'], $r['full_name']),
        'last_message' => $r['last_message'] ? excerpt($r['last_message'], 60) : 'No messages yet',
        'last_message_at' => $r['last_message_at'] ? time_ago($r['last_message_at']) : '',
        'unread_count' => (int) $r['unread_count'],
    ];
    if ($role === 'patient') {
        $item['doctor_id'] = (int) $r['doctor_id'];
        $item['online'] = doctor_chat_available($r);
    } else {
        $item['patient_id'] = (int) $r['patient_id'];
    }
    return $item;
}, $rows);

json_response(true, ['conversations' => $conversations]);
