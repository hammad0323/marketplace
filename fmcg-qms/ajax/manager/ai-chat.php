<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_manager();
csrf_require();
$cid = require_company_id();

$question = post('question');
if ($question === '') {
    json_response(['success' => false, 'message' => 'Please enter a question.'], 422);
}

$limit = check_company_limit($cid, 'ai_usage_limit', 'ai_logs');
if (!$limit['allowed']) {
    json_response(['success' => false, 'message' => 'AI usage limit reached for your subscription plan.'], 422);
}

$answer = ai_chat_query($cid, current_user_id(), $question);
json_response(['success' => true, 'answer' => $answer]);
