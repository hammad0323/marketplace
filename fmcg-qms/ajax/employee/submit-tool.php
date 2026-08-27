<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['employee']);
csrf_require();
$cid = require_company_id();
$uid = current_user_id();

$toolId = post_int('tool_id');
$tool = get_tool($toolId);
if (!$tool || !employee_can_use_tool($uid, $toolId)) {
    json_response(['success' => false, 'message' => 'This tool is not assigned to you.'], 403);
}

$fields = get_tool_fields($toolId);
$context = $_POST['context'] ?? [];
$rawValues = $_POST['values'] ?? [];
$values = [];

foreach ($fields as $f) {
    $fid = $f['id'];
    if (in_array($f['field_type'], ['file', 'image', 'signature'], true)) {
        $fileKey = "values[{$fid}]_file";
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed = $f['field_type'] === 'file' ? ALLOWED_FILE_EXT : ALLOWED_IMAGE_EXT;
            $saved = save_uploaded_file($_FILES[$fileKey], 'tool_submissions/' . $cid, $allowed);
            if ($saved) {
                $values[$fid] = $saved;
            }
        }
        continue;
    }
    if (isset($rawValues[$fid])) {
        $values[$fid] = is_array($rawValues[$fid]) ? clean_input($rawValues[$fid]) : clean_input($rawValues[$fid]);
    }
}

$context = [
    'department_id' => isset($context['department_id']) && $context['department_id'] !== '' ? (int)$context['department_id'] : null,
    'batch_id' => isset($context['batch_id']) && $context['batch_id'] !== '' ? (int)$context['batch_id'] : null,
    'production_line_id' => isset($context['production_line_id']) && $context['production_line_id'] !== '' ? (int)$context['production_line_id'] : null,
    'shift_id' => $_SESSION['shift_id'] ?? null,
];

$result = save_tool_submission($cid, $toolId, $uid, $context, $values);

$fieldValuesForAi = [];
foreach ($values as $fid => $v) {
    $label = null;
    foreach ($fields as $f) { if ($f['id'] == $fid) { $label = $f['label']; break; } }
    if ($label && !is_array($v)) $fieldValuesForAi[$label] = $v;
}
$aiSuggestion = ai_form_suggestion($cid, $uid, $tool['name'], $fieldValuesForAi, $result['deviations']);

json_response([
    'success' => true, 'submission_id' => $result['submission_id'], 'deviations' => $result['deviations'],
    'severity' => $result['severity'], 'ai_suggestion' => $aiSuggestion,
]);
