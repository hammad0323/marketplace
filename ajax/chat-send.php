<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
if (!is_logged_in() || !in_array(current_role(), ['patient', 'doctor'], true)) {
    json_response(false, ['auth_required' => true], 'Please log in to send messages.');
}

$db = db();
$role = current_role();
$profileId = current_profile_id();
$myUserId = (int) $_SESSION['user_id'];
$message = mb_substr(clean($_POST['message'] ?? ''), 0, 2000);

if ($message === '' && empty($_FILES['attachment']['name'])) {
    json_response(false, [], 'Write a message or attach a file.');
}

if ($role === 'patient') {
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $stmt = mysqli_prepare($db, "SELECT d.id, d.chat_enabled, d.chat_start_time, d.chat_end_time, u.id AS user_id, u.full_name FROM doctors d JOIN users u ON u.id = d.user_id
        WHERE d.id = ? AND d.verification_status = 'verified' AND u.status = 'active' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$doctor) {
        json_response(false, [], 'Doctor not found.');
    }

    $stmt = mysqli_prepare($db, 'SELECT id, is_blocked FROM chat_conversations WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $profileId, $doctorId);
    mysqli_stmt_execute($stmt);
    $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($conv) {
        if ((int) $conv['is_blocked'] === 1) {
            json_response(false, [], 'This doctor is not available to message right now.');
        }
        $conversationId = (int) $conv['id'];
    } else {
        if (!doctor_chat_can_start($doctor)) {
            json_response(false, [], $doctor['chat_enabled']
                ? 'This doctor is only accepting new messages during their set hours (' . doctor_chat_hours_label($doctor) . ').'
                : 'This doctor is not currently accepting messages.');
        }
        $stmt = mysqli_prepare($db, 'INSERT INTO chat_conversations (patient_id, doctor_id) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ii', $profileId, $doctorId);
        mysqli_stmt_execute($stmt);
        $conversationId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);
    }
    $recipientUserId = (int) $doctor['user_id'];
    $recipientLink = '/doctor/messages?patient_id=' . $profileId;
} else {
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $conversationIdParam = (int) ($_POST['conversation_id'] ?? 0);
    $conv = null;

    if ($patientId) {
        $stmt = mysqli_prepare($db, 'SELECT c.id, c.patient_id, p.user_id AS patient_user_id FROM chat_conversations c JOIN patients p ON p.id = c.patient_id WHERE c.patient_id = ? AND c.doctor_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $conv = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
    } elseif ($conversationIdParam) {
        // Backwards-compatible path for direct conversation_id sends (e.g. old notification links).
        $stmt = mysqli_prepare($db, 'SELECT c.id, c.patient_id, p.user_id AS patient_user_id FROM chat_conversations c JOIN patients p ON p.id = c.patient_id WHERE c.id = ? AND c.doctor_id = ? LIMIT 1');
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
        // The conversation already exists — reply freely, no appointment check needed.
        $conversationId = (int) $conv['id'];
        $recipientUserId = (int) $conv['patient_user_id'];
    } else {
        // No conversation yet: a doctor may only START one with a patient who has (or has had)
        // an appointment with them. Replying to an existing conversation never hits this branch.
        $stmt = mysqli_prepare($db, 'SELECT p.id, u.id AS user_id FROM patients p JOIN users u ON u.id = p.user_id
            WHERE p.id = ? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = ?) LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $patientRow = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$patientRow) {
            json_response(false, [], 'You can only start a new conversation with a patient who has booked an appointment with you.');
        }
        $recipientUserId = (int) $patientRow['user_id'];

        $stmt = mysqli_prepare($db, 'INSERT INTO chat_conversations (patient_id, doctor_id) VALUES (?, ?)');
        mysqli_stmt_bind_param($stmt, 'ii', $patientId, $profileId);
        mysqli_stmt_execute($stmt);
        $conversationId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);
    }
    $recipientLink = '/patient/messages?doctor_id=' . $profileId;
}

$attachmentPath = null;
$messageType = 'text';
if (!empty($_FILES['attachment']['name'])) {
    [$ok, $result] = handle_upload('attachment', 'reports', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 8 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $attachmentPath = $result;
    $messageType = in_array(pathinfo($result, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image' : 'file';
}

$stmt = mysqli_prepare($db, 'INSERT INTO chat_messages (conversation_id, sender_id, message, attachment_path, message_type) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'iisss', $conversationId, $myUserId, $message, $attachmentPath, $messageType);
mysqli_stmt_execute($stmt);
$messageId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'UPDATE chat_conversations SET last_message_at = NOW() WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $conversationId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$senderName = current_user()['full_name'];
$preview = $message !== '' ? excerpt($message, 120) : 'Sent an attachment.';
notify_user($recipientUserId, 'message', 'New message from ' . $senderName, $preview, $recipientLink);
log_activity($myUserId, $role, 'chat_send', "Sent message in conversation #$conversationId");

json_response(true, ['conversation_id' => $conversationId, 'message_id' => $messageId], 'Message sent.');
