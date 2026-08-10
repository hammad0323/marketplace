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
    $conversationIdParam = (int) ($_GET['conversation_id'] ?? 0);
    $conv = null;

    if ($patientId) {
        $stmt = mysqli_prepare($db, 'SELECT id, is_blocked FROM chat_conversations WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
    } elseif ($conversationIdParam) {
        // Backwards-compatible path for direct conversation_id lookups (e.g. old notification links).
        $stmt = mysqli_prepare($db, 'SELECT id, patient_id, is_blocked FROM chat_conversations WHERE id = ? AND doctor_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $conversationIdParam, $profileId);
        mysqli_stmt_execute($stmt);
        $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if ($conv) {
            $patientId = (int) $conv['patient_id'];
        }
    }

    if (!$patientId) {
        json_response(false, [], 'Conversation not found.');
    }

    if ($conv) {
        // The conversation already exists (the patient reached out, or the doctor started it
        // earlier) — that's sufficient authorization on its own, regardless of booking history.
        $conversationId = (int) $conv['id'];
        $isBlocked = (bool) $conv['is_blocked'];
    } else {
        // No conversation yet: a doctor may only START one with a patient who has (or has had)
        // an appointment with them. Replying to an existing conversation never hits this branch.
        $stmt = mysqli_prepare($db, 'SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $hasAppointment = (bool) mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$hasAppointment) {
            json_response(false, [], 'You can only start a new conversation with a patient who has booked an appointment with you.');
        }
        $conversationId = null;
        $isBlocked = false;
    }

    $stmt = mysqli_prepare($db, 'SELECT u.full_name, u.avatar FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $patientId);
    mysqli_stmt_execute($stmt);
    $patientRow = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$patientRow) {
        json_response(false, [], 'Patient not found.');
    }
    $partner = [
        'name' => $patientRow['full_name'], 'avatar' => avatar_url($patientRow['avatar'], $patientRow['full_name']),
        'patient_id' => $patientId, 'is_blocked' => $isBlocked,
    ];
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
