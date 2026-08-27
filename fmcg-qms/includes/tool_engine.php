<?php
/**
 * Dynamic Quality Tool Engine - powers hundreds of quality tools from configuration
 * (tools + tool_fields + tool_field_options) instead of one-off CRUD pages.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/calculations.php';

function get_tool_categories(): array
{
    return db_fetch_all("SELECT * FROM tool_categories ORDER BY sort_order, name");
}

function get_tools_for_company(int $companyId, array $filters = []): array
{
    $where = ["(t.company_id IS NULL OR t.company_id = ?)", "t.status = 'active'"];
    $types = 'i';
    $params = [$companyId];
    if (!empty($filters['category_id'])) {
        $where[] = 't.category_id = ?'; $types .= 'i'; $params[] = (int)$filters['category_id'];
    }
    if (!empty($filters['department_id'])) {
        $where[] = 't.department_id = ?'; $types .= 'i'; $params[] = (int)$filters['department_id'];
    }
    if (!empty($filters['search'])) {
        $where[] = 't.name LIKE ?'; $types .= 's'; $params[] = '%' . db_escape_like($filters['search']) . '%';
    }
    $sql = "SELECT t.*, tc.name AS category_name FROM tools t
            LEFT JOIN tool_categories tc ON tc.id = t.category_id
            WHERE " . implode(' AND ', $where) . " ORDER BY tc.sort_order, t.name";
    return db_fetch_all($sql, $types, $params);
}

function get_tool(int $toolId): ?array
{
    return db_fetch_one("SELECT t.*, tc.name AS category_name FROM tools t LEFT JOIN tool_categories tc ON tc.id=t.category_id WHERE t.id = ?", 'i', [$toolId]);
}

function get_tool_fields(int $toolId): array
{
    $fields = db_fetch_all("SELECT * FROM tool_fields WHERE tool_id = ? ORDER BY sort_order ASC, id ASC", 'i', [$toolId]);
    foreach ($fields as &$field) {
        if (in_array($field['field_type'], ['dropdown', 'multiselect', 'radio', 'checkbox'], true)) {
            $field['field_options'] = db_fetch_all("SELECT * FROM tool_field_options WHERE tool_field_id = ? ORDER BY sort_order", 'i', [$field['id']]);
        } else {
            $field['field_options'] = [];
        }
    }
    return $fields;
}

function get_assigned_tools_for_user(int $userId): array
{
    return db_fetch_all(
        "SELECT ta.*, t.name, t.slug, t.icon, t.description, t.frequency, t.tool_type, t.category_id
         FROM tool_assignments ta JOIN tools t ON t.id = ta.tool_id
         WHERE ta.user_id = ? AND ta.status = 'active' AND t.status = 'active' ORDER BY t.name", 'i', [$userId]
    );
}

function assign_tool_to_user(int $companyId, int $toolId, int $userId, int $assignedBy): int
{
    $existing = db_fetch_one("SELECT id FROM tool_assignments WHERE company_id=? AND tool_id=? AND user_id=?", 'iii', [$companyId, $toolId, $userId]);
    if ($existing) {
        db_execute("UPDATE tool_assignments SET status='active' WHERE id=?", 'i', [$existing['id']]);
        return (int)$existing['id'];
    }
    return (int)db_execute(
        "INSERT INTO tool_assignments (company_id, tool_id, user_id, assigned_by, status, assigned_at) VALUES (?,?,?,?,'active',NOW())",
        'iiii', [$companyId, $toolId, $userId, $assignedBy]
    );
}

function unassign_tool(int $assignmentId, int $companyId): bool
{
    return db_execute("UPDATE tool_assignments SET status='removed' WHERE id=? AND company_id=?", 'ii', [$assignmentId, $companyId]) !== false;
}

/** Frequency-aware period key so "one submission per period" can be enforced/checked. */
function tool_period_key(string $frequency, ?int $shiftId = null): string
{
    return match ($frequency) {
        'hourly' => date('Y-m-d H'),
        'per_shift' => date('Y-m-d') . '-shift' . ($shiftId ?: 0),
        'weekly' => date('o-\WW'),
        'monthly' => date('Y-m'),
        'per_batch', 'per_production_run', 'on_demand' => uniqid('', true),
        default => date('Y-m-d'), // daily
    };
}

/**
 * Persists a dynamic tool submission, evaluates thresholds, and triggers the Issue Engine on deviation.
 * $values = [tool_field_id => raw_value]
 */
function save_tool_submission(int $companyId, int $toolId, int $userId, array $context, array $values): array
{
    $tool = get_tool($toolId);
    $fields = get_tool_fields($toolId);
    $periodKey = tool_period_key($tool['frequency'] ?? 'daily', $context['shift_id'] ?? null);

    $submissionId = (int)db_execute(
        "INSERT INTO tool_submissions
         (company_id, tool_id, user_id, department_id, shift_id, batch_id, production_line_id, period_key, status, has_deviation, deviation_severity, submitted_at, created_at)
         VALUES (?,?,?,?,?,?,?,?, 'submitted', 0, NULL, NOW(), NOW())",
        'iiiiiiis',
        [$companyId, $toolId, $userId, $context['department_id'] ?? null, $context['shift_id'] ?? null,
         $context['batch_id'] ?? null, $context['production_line_id'] ?? null, $periodKey]
    );

    $deviations = [];
    $worstSeverity = null;
    $severityRank = ['observation' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    foreach ($fields as $field) {
        $raw = $values[$field['id']] ?? null;
        if ($raw === null || $raw === '') {
            continue;
        }
        [$valueText, $valueNumber, $valueDate, $filePath] = normalize_field_value($field, $raw);
        db_execute(
            "INSERT INTO tool_submission_values (submission_id, tool_field_id, value_text, value_number, value_date, file_path)
             VALUES (?,?,?,?,?,?)",
            'iisdss', [$submissionId, $field['id'], $valueText, $valueNumber, $valueDate, $filePath]
        );

        if ($valueNumber !== null && is_field_numeric_threshold($field)) {
            $status = evaluate_threshold(
                $valueNumber,
                $field['min_value'] !== null ? (float)$field['min_value'] : null,
                $field['max_value'] !== null ? (float)$field['max_value'] : null,
                $field['warning_threshold_low'] !== null ? (float)$field['warning_threshold_low'] : null,
                $field['warning_threshold_high'] !== null ? (float)$field['warning_threshold_high'] : null,
                $field['critical_threshold_low'] !== null ? (float)$field['critical_threshold_low'] : null,
                $field['critical_threshold_high'] !== null ? (float)$field['critical_threshold_high'] : null
            );
            if ($status !== 'normal') {
                $severity = match ($status) {
                    'critical' => 'critical',
                    'out_of_spec' => 'high',
                    'warning' => 'medium',
                    default => 'low',
                };
                $range = trim(($field['min_value'] ?? '') !== '' ? ($field['min_value'] . ' - ' . $field['max_value'] . ' ' . $field['unit']) : '');
                $deviations[] = [
                    'field' => $field['label'], 'value' => $valueNumber, 'status' => $status, 'severity' => $severity, 'range' => $range ?: 'configured spec',
                ];
                if ($worstSeverity === null || $severityRank[$severity] > $severityRank[$worstSeverity]) {
                    $worstSeverity = $severity;
                }
            }
        } elseif (in_array($field['field_type'], ['pass_fail'], true) && strtolower((string)$valueText) === 'fail') {
            $deviations[] = ['field' => $field['label'], 'value' => 'Fail', 'status' => 'out_of_spec', 'severity' => 'high', 'range' => 'Pass'];
            if ($worstSeverity === null || $severityRank['high'] > $severityRank[$worstSeverity]) {
                $worstSeverity = 'high';
            }
        }
    }

    if (!empty($deviations)) {
        db_execute("UPDATE tool_submissions SET has_deviation = 1, deviation_severity = ? WHERE id = ?", 'si', [$worstSeverity, $submissionId]);
        require_once __DIR__ . '/issue_engine.php';
        create_issue_from_deviation($companyId, $submissionId, $toolId, $userId, $context, $deviations, $worstSeverity);
    }

    return ['submission_id' => $submissionId, 'deviations' => $deviations, 'severity' => $worstSeverity];
}

function is_field_numeric_threshold(array $field): bool
{
    return in_array($field['field_type'], ['number', 'decimal', 'measurement', 'percentage'], true)
        && ($field['min_value'] !== null || $field['max_value'] !== null || $field['warning_threshold_low'] !== null
            || $field['warning_threshold_high'] !== null || $field['critical_threshold_low'] !== null || $field['critical_threshold_high'] !== null);
}

function normalize_field_value(array $field, $raw): array
{
    $valueText = null; $valueNumber = null; $valueDate = null; $filePath = null;
    switch ($field['field_type']) {
        case 'number': case 'decimal': case 'measurement': case 'percentage': case 'rating':
            $valueNumber = is_numeric($raw) ? (float)$raw : null;
            $valueText = (string)$raw;
            break;
        case 'date': case 'datetime': case 'time':
            $valueDate = (string)$raw;
            $valueText = (string)$raw;
            break;
        case 'file': case 'image': case 'signature':
            $filePath = is_array($raw) ? ($raw['path'] ?? null) : (string)$raw;
            $valueText = $filePath;
            break;
        case 'multiselect': case 'checkbox':
            $valueText = is_array($raw) ? implode(', ', $raw) : (string)$raw;
            break;
        default:
            $valueText = is_array($raw) ? json_encode($raw) : (string)$raw;
    }
    return [$valueText, $valueNumber, $valueDate, $filePath];
}

/** For employee dashboard: today's (or current-period) status of every assigned tool. */
function get_employee_tool_status(int $userId): array
{
    $assignments = get_assigned_tools_for_user($userId);
    $rows = [];
    foreach ($assignments as $a) {
        $periodKey = tool_period_key($a['frequency']);
        $submission = db_fetch_one(
            "SELECT * FROM tool_submissions WHERE tool_id=? AND user_id=? AND period_key=? ORDER BY id DESC LIMIT 1",
            'iis', [$a['tool_id'], $userId, $periodKey]
        );
        $status = 'pending';
        if ($submission) {
            $status = $submission['has_deviation'] ? 'issue_detected' : $submission['status'];
        }
        $rows[] = ['assignment' => $a, 'status' => $status, 'submission' => $submission, 'period_key' => $periodKey];
    }
    return $rows;
}

function get_tool_submissions(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $where = ['ts.company_id = ?'];
    $types = 'i'; $params = [$companyId];
    foreach (['tool_id' => 'ts.tool_id', 'user_id' => 'ts.user_id', 'department_id' => 'ts.department_id', 'batch_id' => 'ts.batch_id'] as $key => $col) {
        if (!empty($filters[$key])) { $where[] = "$col = ?"; $types .= 'i'; $params[] = (int)$filters[$key]; }
    }
    if (!empty($filters['status'])) { $where[] = 'ts.status = ?'; $types .= 's'; $params[] = $filters['status']; }
    if (!empty($filters['date_from'])) { $where[] = 'ts.created_at >= ?'; $types .= 's'; $params[] = $filters['date_from'] . ' 00:00:00'; }
    if (!empty($filters['date_to'])) { $where[] = 'ts.created_at <= ?'; $types .= 's'; $params[] = $filters['date_to'] . ' 23:59:59'; }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT ts.*, t.name AS tool_name, u.name AS user_name, d.name AS department_name
            FROM tool_submissions ts
            JOIN tools t ON t.id = ts.tool_id
            JOIN users u ON u.id = ts.user_id
            LEFT JOIN departments d ON d.id = ts.department_id
            WHERE " . implode(' AND ', $where) . " ORDER BY ts.created_at DESC LIMIT $limit OFFSET " . (int)$offset;
    return db_fetch_all($sql, $types, $params);
}

function get_submission_detail(int $submissionId): ?array
{
    $submission = db_fetch_one(
        "SELECT ts.*, t.name AS tool_name, u.name AS user_name FROM tool_submissions ts
         JOIN tools t ON t.id=ts.tool_id JOIN users u ON u.id=ts.user_id WHERE ts.id = ?", 'i', [$submissionId]
    );
    if (!$submission) return null;
    $values = db_fetch_all(
        "SELECT tsv.*, tf.label, tf.field_type, tf.unit FROM tool_submission_values tsv
         JOIN tool_fields tf ON tf.id = tsv.tool_field_id WHERE tsv.submission_id = ? ORDER BY tf.sort_order", 'i', [$submissionId]
    );
    $submission['values'] = $values;
    return $submission;
}
