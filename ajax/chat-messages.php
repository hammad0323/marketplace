<?php
require __DIR__ . '/../config/config.php';

if (!is_logged_in() || !in_array(current_role(), ['patient', 'doctor'], true)) {
    json_response(false, [], 'Please log in to view messages.');
}

$db = db();
$role = current_role();
$profileId = current_profile_id();
$afterId = (int) ($_GET['after_id'] ?? 0);

if ($role === 'patient') {
    $doctorId = (int) ($_GET['doctor_id'] ?? 0);
    $stmt = mysqli_prepare($db, "SELECT d.id, d.chat_enabled, d.chat_visible_to_guests, d.chat_start_time, d.chat_end_time, u.id AS user_id, u.full_name, u.avatar, u.last_active_at
        FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ? AND d.verification_status = 'verified' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $partnerDoctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$partnerDoctor) {
        json_response(false, [], 'Doctor not found.');
    }

    $stmt = mysqli_prepare($db, 'SELECT id FROM chat_conversations WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $profileId, $doctorId);
    mysqli_stmt_execute($stmt);
    $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    $conversationId = $conv['id'] ?? null;

    $partner = [
        'name' => $partnerDoctor['full_name'],
        'avatar' => avatar_url($partnerDoctor['avatar'], $partnerDoctor['full_name']),
        'online' => doctor_chat_available($partnerDoctor),
        'hours' => doctor_chat_hours_label($partnerDoctor),
        'doctor_id' => (int) $partnerDoctor['id'],
    ];
} else {
    $patientId = (int) ($_GET['patient_id'] ?? 0);

    if (!$patientId) {
        // Backwards-compatible path for direct conversation_id lookups (e.g. notification links).
        $conversationId = (int) ($_GET['conversation_id'] ?? 0);
        $stmt = mysqli_prepare($db, "SELECT c.id, p.id AS patient_id, u.full_name, u.avatar FROM chat_conversations c
            JOIN patients p ON p.id = c.patient_id JOIN users u ON u.id = p.user_id
            WHERE c.id = ? AND c.doctor_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $profileId);
        mysqli_stmt_execute($stmt);
        $convRow = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$convRow) {
            json_response(false, [], 'Conversation not found.');
        }
        $patientId = (int) $convRow['patient_id'];
        $partner = ['name' => $convRow['full_name'], 'avatar' => avatar_url($convRow['avatar'], $convRow['full_name']), 'patient_id' => $patientId];
    } else {
        // A doctor may only message a patient who has (or has had) an appointment with them.
        $stmt = mysqli_prepare($db, "SELECT p.id, u.full_name, u.avatar FROM patients p JOIN users u ON u.id = p.user_id
            WHERE p.id = ? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = ?) LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $partnerPatient = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$partnerPatient) {
            json_response(false, [], 'You can only message patients who have booked an appointment with you.');
        }
        $partner = ['name' => $partnerPatient['full_name'], 'avatar' => avatar_url($partnerPatient['avatar'], $partnerPatient['full_name']), 'patient_id' => $patientId];
    }

    $stmt = mysqli_prepare($db, 'SELECT id FROM chat_conversations WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
    mysqli_stmt_execute($stmt);
    $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    $conversationId = $conv['id'] ?? null;
}

$messages = [];
if ($conversationId) {
    $sql = 'SELECT * FROM chat_messages WHERE conversation_id = ?' . ($afterId ? ' AND id > ?' : '') . ' ORDER BY id ASC';
    $stmt = mysqli_prepare($db, $sql);
    if ($afterId) {
        mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $afterId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $conversationId);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $myUserId = (int) $_SESSION['user_id'];
    foreach ($rows as $r) {
        $messages[] = [
            'id' => (int) $r['id'],
            'is_mine' => (int) $r['sender_id'] === $myUserId,
            'message' => $r['message'],
            'attachment_url' => $r['attachment_path'] ? UPLOAD_URL . '/' . $r['attachment_path'] : null,
            'message_type' => $r['message_type'],
            'created_at' => $r['created_at'],
            'time_label' => format_time12($r['created_at'] ? date('H:i:s', strtotime($r['created_at'])) : null),
        ];
    }

    $stmt = mysqli_prepare($db, 'UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0');
    mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $myUserId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

json_response(true, ['conversation_id' => $conversationId, 'messages' => $messages, 'partner' => $partner]);
