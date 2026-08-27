<?php
/**
 * NCR / CAPA Workflow Engine.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/email.php';

const CAPA_STATUSES = ['open', 'assigned', 'in_progress', 'pending_verification', 'effective', 'closed', 'rejected'];
const NCR_STATUSES = ['open', 'investigation', 'containment', 'root_cause', 'corrective_action', 'closed'];

function create_ncr(int $companyId, int $userId, array $data): int
{
    $number = generate_sequenced_number($companyId, 'ncr', 'ncr_number', 'NCR');
    $id = (int)db_execute(
        "INSERT INTO ncr (company_id, ncr_number, product_id, batch_id, department_id, process, issue_id, severity,
         description, containment, root_cause, corrective_action, preventive_action, responsible_person, due_date, status, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'open',NOW())",
        'isiiisissssssis',
        [
            $companyId, $number, $data['product_id'] ?: null, $data['batch_id'] ?: null, $data['department_id'] ?: null,
            $data['process'] ?? '', $data['issue_id'] ?: null, $data['severity'] ?? 'medium', $data['description'] ?? '',
            $data['containment'] ?? '', $data['root_cause'] ?? '', $data['corrective_action'] ?? '', $data['preventive_action'] ?? '',
            $data['responsible_person'] ?: null, $data['due_date'] ?: null,
        ]
    );
    log_activity($companyId, $userId, 'create', 'ncr', $id, "Created $number");
    if (!empty($data['responsible_person'])) {
        notify($companyId, (int)$data['responsible_person'], 'ncr', 'NCR Assigned: ' . $number, $data['description'] ?? '', base_url('manager/ncr-view.php?id=' . $id), 'warning');
    }
    return $id;
}

function update_ncr_status(int $ncrId, int $companyId, string $status, int $userId): bool
{
    if (!in_array($status, NCR_STATUSES, true)) return false;
    $sql = "UPDATE ncr SET status=?" . ($status === 'closed' ? ", closed_at=NOW()" : "") . " WHERE id=? AND company_id=?";
    $ok = db_execute($sql, 'sii', [$status, $ncrId, $companyId]) !== false;
    if ($ok) log_activity($companyId, $userId, 'update', 'ncr', $ncrId, "Status changed to $status");
    return $ok;
}

function get_ncr_list(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $where = ['n.company_id = ?']; $types = 'i'; $params = [$companyId];
    foreach (['status' => 'n.status', 'severity' => 'n.severity', 'department_id' => 'n.department_id'] as $k => $col) {
        if (!empty($filters[$k])) { $where[] = "$col = ?"; $types .= is_numeric($filters[$k]) ? 'i' : 's'; $params[] = $filters[$k]; }
    }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT n.*, p.name AS product_name, b.batch_number, d.name AS department_name, u.name AS responsible_name
            FROM ncr n LEFT JOIN products p ON p.id=n.product_id LEFT JOIN batches b ON b.id=n.batch_id
            LEFT JOIN departments d ON d.id=n.department_id LEFT JOIN users u ON u.id=n.responsible_person
            WHERE " . implode(' AND ', $where) . " ORDER BY n.created_at DESC LIMIT $limit OFFSET " . (int)$offset;
    return db_fetch_all($sql, $types, $params);
}

function create_capa(int $companyId, int $userId, array $data): int
{
    $number = generate_sequenced_number($companyId, 'capa', 'capa_number', 'CAPA');
    $id = (int)db_execute(
        "INSERT INTO capa (company_id, capa_number, source_type, source_id, problem_statement, root_cause, correction,
         corrective_action, preventive_action, responsible_person, due_date, status, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,'open',NOW())",
        'ississsssis',
        [
            $companyId, $number, $data['source_type'] ?? 'manual', $data['source_id'] ?: null, $data['problem_statement'] ?? '',
            $data['root_cause'] ?? '', $data['correction'] ?? '', $data['corrective_action'] ?? '', $data['preventive_action'] ?? '',
            $data['responsible_person'] ?: null, $data['due_date'] ?: null,
        ]
    );
    log_activity($companyId, $userId, 'create', 'capa', $id, "Created $number");
    if (!empty($data['responsible_person'])) {
        notify($companyId, (int)$data['responsible_person'], 'capa', 'CAPA Assigned: ' . $number, $data['problem_statement'] ?? '', base_url('manager/capa-view.php?id=' . $id), 'warning');
        $resp = db_fetch_one("SELECT name, email FROM users WHERE id=?", 'i', [$data['responsible_person']]);
        if ($resp) {
            send_event_email($companyId, $resp['email'], $resp['name'], 'action_assigned', [
                'employee_name' => $resp['name'], 'issue_name' => $number, 'deadline' => fmt_date($data['due_date'] ?? null),
            ]);
        }
    }
    return $id;
}

function update_capa_status(int $capaId, int $companyId, string $status, int $userId, array $extra = []): bool
{
    if (!in_array($status, CAPA_STATUSES, true)) return false;
    $sets = ['status = ?']; $types = 's'; $params = [$status];
    if ($status === 'closed' || $status === 'effective') { $sets[] = 'closed_at = NOW()'; }
    if (isset($extra['verification_notes'])) { $sets[] = 'verification_notes = ?'; $types .= 's'; $params[] = $extra['verification_notes']; }
    if (isset($extra['effectiveness_notes'])) { $sets[] = 'effectiveness_notes = ?'; $types .= 's'; $params[] = $extra['effectiveness_notes']; }
    $types .= 'ii'; $params[] = $capaId; $params[] = $companyId;
    $ok = db_execute("UPDATE capa SET " . implode(', ', $sets) . " WHERE id = ? AND company_id = ?", $types, $params) !== false;
    if ($ok) log_activity($companyId, $userId, 'update', 'capa', $capaId, "Status changed to $status");
    return $ok;
}

function add_capa_action(int $capaId, int $companyId, string $actionText, ?int $responsibleUser, ?string $dueDate, int $userId): int
{
    return (int)db_execute(
        "INSERT INTO capa_actions (capa_id, company_id, action_text, responsible_user, due_date, status, created_by, created_at)
         VALUES (?,?,?,?,?,'pending',?,NOW())",
        'iisisi', [$capaId, $companyId, $actionText, $responsibleUser, $dueDate, $userId]
    );
}

function get_capa_list(int $companyId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $where = ['c.company_id = ?']; $types = 'i'; $params = [$companyId];
    if (!empty($filters['status'])) { $where[] = 'c.status = ?'; $types .= 's'; $params[] = $filters['status']; }
    if (!empty($filters['overdue'])) { $where[] = "c.due_date < CURDATE() AND c.status NOT IN ('closed','rejected','effective')"; }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT c.*, u.name AS responsible_name FROM capa c LEFT JOIN users u ON u.id=c.responsible_person
            WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC LIMIT $limit OFFSET " . (int)$offset;
    return db_fetch_all($sql, $types, $params);
}

function count_overdue_capa(int $companyId): int
{
    return db_count('capa', "company_id=? AND due_date < CURDATE() AND status NOT IN ('closed','rejected','effective')", 'i', [$companyId]);
}
