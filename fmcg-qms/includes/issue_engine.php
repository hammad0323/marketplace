<?php
/**
 * Issue Engine + Workflow Engine
 * Detected -> Assigned -> Investigation -> Containment -> Root Cause ->
 * Corrective Action -> Preventive Action -> Verification -> Closed
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/email.php';

const ISSUE_WORKFLOW_STEPS = [
    'detected', 'assigned', 'investigation', 'containment', 'root_cause',
    'corrective_action', 'preventive_action', 'verification', 'closed',
];

function create_issue_from_deviation(int $companyId, int $submissionId, int $toolId, int $userId, array $context, array $deviations, string $severity): int
{
    $tool = db_fetch_one("SELECT name FROM tools WHERE id = ?", 'i', [$toolId]);
    $descLines = array_map(fn($d) => "{$d['field']}: {$d['value']} (expected {$d['range']}) - {$d['status']}", $deviations);
    $description = "Automatic deviation detected on \"" . ($tool['name'] ?? 'Tool') . "\" submission.\n" . implode("\n", $descLines);
    $issueNumber = generate_sequenced_number($companyId, 'quality_issues', 'issue_number', 'QI');

    $issueId = (int)db_execute(
        "INSERT INTO quality_issues
         (company_id, issue_number, source_type, source_id, tool_id, department_id, product_id, batch_id, shift_id,
          defect_type, severity, status, description, detected_by, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,'detected',?,?,NOW())",
        'issiiiiiisssi',
        [
            $companyId, $issueNumber, 'tool_submission', $submissionId, $toolId, $context['department_id'] ?? null,
            $context['product_id'] ?? null, $context['batch_id'] ?? null, $context['shift_id'] ?? null,
            $deviations[0]['field'] ?? 'Deviation', $severity, $description, $userId,
        ]
    );

    require_once __DIR__ . '/ai.php';
    $aiNote = ai_root_cause_suggestion($companyId, $userId, 'Automatic Deviation', $description, ['deviations' => $deviations]);
    db_execute("UPDATE quality_issues SET ai_recommendation = ? WHERE id = ?", 'si', [$aiNote, $issueId]);

    notify_company_managers(
        $companyId, 'quality_issue',
        ($severity === 'critical' ? 'Critical' : ucfirst($severity)) . ' Quality Issue Detected',
        "$issueNumber - " . ($tool['name'] ?? 'Tool') . ": {$deviations[0]['field']} out of specification.",
        base_url('manager/issue-view.php?id=' . $issueId),
        $severity === 'critical' || $severity === 'high' ? 'danger' : 'warning'
    );

    $managers = db_fetch_all("SELECT name, email FROM users WHERE company_id=? AND role='manager' AND status='active'", 'i', [$companyId]);
    foreach ($managers as $m) {
        send_event_email($companyId, $m['email'], $m['name'], $severity === 'critical' ? 'critical_issue' : 'quality_issue', [
            'employee_name' => $m['name'], 'issue_name' => $issueNumber, 'tool_name' => $tool['name'] ?? '', 'date' => date('d M Y H:i'),
        ]);
    }

    log_activity($companyId, $userId, 'create', 'quality_issue', $issueId, "Auto-created issue $issueNumber (severity: $severity)");
    return $issueId;
}

function create_manual_issue(int $companyId, int $userId, array $data): int
{
    $issueNumber = generate_sequenced_number($companyId, 'quality_issues', 'issue_number', 'QI');
    $issueId = (int)db_execute(
        "INSERT INTO quality_issues (company_id, issue_number, source_type, department_id, product_id, batch_id, shift_id,
         defect_type, severity, status, description, detected_by, created_at)
         VALUES (?,?,'manual',?,?,?,?,?,?,'detected',?,?,NOW())",
        'isiiiisssi',
        [$companyId, $issueNumber, $data['department_id'] ?: null, $data['product_id'] ?: null, $data['batch_id'] ?: null,
         $data['shift_id'] ?: null, $data['defect_type'], $data['severity'], $data['description'], $userId]
    );
    notify_company_managers($companyId, 'quality_issue', 'New Quality Issue: ' . $issueNumber, $data['description'], base_url('manager/issue-view.php?id=' . $issueId));
    log_activity($companyId, $userId, 'create', 'quality_issue', $issueId, "Manually created issue $issueNumber");
    return $issueId;
}

function issue_next_status(string $current): ?string
{
    $idx = array_search($current, ISSUE_WORKFLOW_STEPS, true);
    if ($idx === false || $idx >= count(ISSUE_WORKFLOW_STEPS) - 1) return null;
    return ISSUE_WORKFLOW_STEPS[$idx + 1];
}

function transition_issue_status(int $issueId, int $companyId, string $newStatus, int $userId, string $comment = ''): bool
{
    if (!in_array($newStatus, ISSUE_WORKFLOW_STEPS, true)) return false;
    $issue = db_fetch_one("SELECT * FROM quality_issues WHERE id = ? AND company_id = ?", 'ii', [$issueId, $companyId]);
    if (!$issue) return false;

    $fields = ['status' => $newStatus];
    $sql = "UPDATE quality_issues SET status = ?" . ($newStatus === 'closed' ? ", closed_at = NOW()" : "") . " WHERE id = ? AND company_id = ?";
    db_execute($sql, 'sii', [$newStatus, $issueId, $companyId]);

    if ($comment !== '') {
        db_execute(
            "INSERT INTO issue_comments (issue_id, user_id, comment, status_at_comment, created_at) VALUES (?,?,?,?,NOW())",
            'iiss', [$issueId, $userId, $comment, $newStatus]
        );
    }
    log_activity($companyId, $userId, 'update', 'quality_issue', $issueId, "Status changed to $newStatus" . ($comment ? ": $comment" : ''));

    if ($newStatus === 'closed') {
        notify_company_managers($companyId, 'issue_resolved', 'Issue Resolved: ' . $issue['issue_number'], 'The issue has been closed.', base_url('manager/issue-view.php?id=' . $issueId), 'success');
    }
    return true;
}

function assign_issue(int $issueId, int $companyId, int $assigneeId, int $actingUserId): bool
{
    $ok = db_execute("UPDATE quality_issues SET assigned_to = ?, status = IF(status='detected','assigned',status) WHERE id=? AND company_id=?",
        'iii', [$assigneeId, $issueId, $companyId]) !== false;
    if ($ok) {
        notify($companyId, $assigneeId, 'action_assigned', 'Issue Assigned To You', 'A quality issue has been assigned to you for investigation.', base_url('manager/issue-view.php?id=' . $issueId), 'info');
        log_activity($companyId, $actingUserId, 'update', 'quality_issue', $issueId, "Assigned to user #$assigneeId");
    }
    return $ok;
}

function get_issues(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $where = ['qi.company_id = ?']; $types = 'i'; $params = [$companyId];
    foreach (['severity' => 'qi.severity', 'status' => 'qi.status', 'department_id' => 'qi.department_id', 'product_id' => 'qi.product_id', 'batch_id' => 'qi.batch_id'] as $k => $col) {
        if (!empty($filters[$k])) { $where[] = "$col = ?"; $types .= is_numeric($filters[$k]) ? 'i' : 's'; $params[] = $filters[$k]; }
    }
    if (!empty($filters['search'])) { $where[] = '(qi.issue_number LIKE ? OR qi.description LIKE ?)'; $types .= 'ss'; $like = '%' . db_escape_like($filters['search']) . '%'; $params[] = $like; $params[] = $like; }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT qi.*, d.name AS department_name, p.name AS product_name, b.batch_number
            FROM quality_issues qi
            LEFT JOIN departments d ON d.id = qi.department_id
            LEFT JOIN products p ON p.id = qi.product_id
            LEFT JOIN batches b ON b.id = qi.batch_id
            WHERE " . implode(' AND ', $where) . " ORDER BY qi.created_at DESC LIMIT $limit OFFSET " . (int)$offset;
    return db_fetch_all($sql, $types, $params);
}
