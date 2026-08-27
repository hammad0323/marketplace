<?php
/**
 * Reporting Engine - shared query builder used by both the on-screen report viewer and CSV export.
 */
require_once __DIR__ . '/db.php';

function report_rows(string $type, int $cid, string $dateFrom, string $dateTo): array
{
    $range = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
    switch ($type) {
        case 'ncr':
            return db_all("SELECT ncr_number, severity, status, description, due_date, created_at FROM ncr WHERE company_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC", [$cid, ...$range]);
        case 'capa':
            return db_all("SELECT capa_number, status, problem_statement, due_date, created_at FROM capa WHERE company_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC", [$cid, ...$range]);
        case 'audits':
            return db_all("SELECT title, audit_type, score, max_score, status, scheduled_date FROM audits WHERE company_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC", [$cid, ...$range]);
        case 'complaints':
            return db_all("SELECT complaint_number, customer_name, complaint_type, severity, status, created_at FROM customer_complaints WHERE company_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC", [$cid, ...$range]);
        case 'oee':
            return db_all("SELECT record_date, availability, performance, quality, oee FROM oee_records WHERE company_id=? AND record_date BETWEEN ? AND ? ORDER BY record_date DESC", [$cid, $dateFrom, $dateTo]);
        case 'suppliers':
            return db_all("SELECT s.name, ss.period, ss.overall_score FROM supplier_scorecards ss JOIN suppliers s ON s.id=ss.supplier_id WHERE ss.company_id=? ORDER BY ss.period DESC", [$cid]);
        case 'scrap_rework':
            return db_all("SELECT 'Scrap' type, sr.record_date, p.name product, sr.quantity, sr.reason, sr.cost FROM scrap_records sr LEFT JOIN products p ON p.id=sr.product_id WHERE sr.company_id=? AND sr.record_date BETWEEN ? AND ?
                UNION ALL SELECT 'Rework', rr.record_date, p.name, rr.quantity, rr.reason, rr.cost FROM rework_records rr LEFT JOIN products p ON p.id=rr.product_id WHERE rr.company_id=? AND rr.record_date BETWEEN ? AND ?
                ORDER BY record_date DESC", [$cid, $dateFrom, $dateTo, $cid, $dateFrom, $dateTo]);
        case 'cost_of_quality':
            return db_all("SELECT period, prevention_cost, appraisal_cost, internal_failure_cost, external_failure_cost,
                (prevention_cost+appraisal_cost+internal_failure_cost+external_failure_cost) AS total FROM cost_of_quality WHERE company_id=? ORDER BY period DESC", [$cid]);
        case 'employee_compliance':
            return db_all("SELECT u.name, COUNT(ts.id) total, SUM(ts.status='missed') missed,
                ROUND(SUM(ts.status<>'missed')/COUNT(ts.id)*100,1) compliance
                FROM tool_submissions ts JOIN users u ON u.id=ts.user_id WHERE ts.company_id=? AND ts.created_at BETWEEN ? AND ? GROUP BY u.id ORDER BY compliance ASC", [$cid, ...$range]);
        default:
            return db_all("SELECT issue_number, severity, status, defect_type, description, created_at FROM quality_issues WHERE company_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC", [$cid, ...$range]);
    }
}

function report_type_labels(): array
{
    return [
        'quality_issues' => 'Quality Issues Report', 'ncr' => 'NCR Report', 'capa' => 'CAPA Report',
        'audits' => 'Audit Report', 'complaints' => 'Complaint Report', 'oee' => 'OEE Report',
        'suppliers' => 'Supplier Report', 'scrap_rework' => 'Scrap & Rework Report', 'cost_of_quality' => 'Cost of Quality Report',
        'employee_compliance' => 'Employee Compliance Report',
    ];
}
