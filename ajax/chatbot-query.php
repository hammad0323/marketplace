<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../chatbot-functions.php';
header('Content-Type: application/json');
wh_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
wh_csrf_verify();

$businessId = wh_current_business_id();
$question = trim(wh_input_post('question'));

if ($question === '') {
    echo json_encode(['success' => false, 'message' => 'Please type a question.']);
    exit;
}

$result = wh_chatbot_answer($businessId, $question);

wh_insert('chatbot_logs', [
    'business_id' => $businessId,
    'admin_user_id' => $_SESSION['admin_id'],
    'question' => $question,
    'answer' => $result['answer'],
    'matched_intent' => $result['intent'],
]);

echo json_encode(['success' => true, 'answer' => $result['answer'], 'intent' => $result['intent']]);
