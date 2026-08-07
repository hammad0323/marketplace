<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$op = $_POST['op'] ?? 'add';

if ($op === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($db, 'DELETE FROM faqs WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(true, [], 'FAQ removed.');
}

$question = clean($_POST['question'] ?? '');
$answer = clean($_POST['answer'] ?? '');
if ($question === '' || $answer === '') {
    json_response(false, ['errors' => ['question' => empty($question) ? 'Required.' : '']], 'Question and answer are required.');
}

$sortOrder = (int) mysqli_fetch_assoc(mysqli_query($db, 'SELECT COALESCE(MAX(sort_order),0)+1 c FROM faqs'))['c'];
$stmt = mysqli_prepare($db, 'INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'ssi', $question, $answer, $sortOrder);
mysqli_stmt_execute($stmt);
$id = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

json_response(true, ['id' => $id], 'FAQ added.');
