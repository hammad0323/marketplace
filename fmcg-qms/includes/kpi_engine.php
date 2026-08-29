<?php
/**
 * KPI Engine - configurable quality KPIs, RAG status, weighted company quality score.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/calculations.php';

function kpi_date_range(string $period = '30d'): array
{
    $to = date('Y-m-d 23:59:59');
    $days = match ($period) {
        '7d' => 7, '90d' => 90, '365d' => 365, default => 30,
    };
    $from = date('Y-m-d 00:00:00', strtotime("-$days days"));
    return [$from, $to];
}

function kpi_defect_rate(int $companyId, string $from, string $to): float
{
    $defects = db_count('quality_issues', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $submissions = db_count('tool_submissions', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    return calc_defect_rate($defects, max($submissions, 1));
}

function kpi_inspection_compliance(int $companyId, string $from, string $to): float
{
    $total = db_count('tool_submissions', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $missed = db_count('tool_submissions', "company_id=? AND status='missed' AND created_at BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]);
    if ($total <= 0) return 100.0;
    return round((($total - $missed) / $total) * 100, 2);
}

function kpi_employee_compliance(int $companyId, string $from, string $to): float
{
    return kpi_inspection_compliance($companyId, $from, $to);
}

function kpi_fpy(int $companyId, string $from, string $to): float
{
    $total = db_count('tool_submissions', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $withIssue = db_count('tool_submissions', "company_id=? AND has_deviation=1 AND created_at BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]);
    if ($total <= 0) return 100.0;
    return calc_fpy($total - $withIssue, $total);
}

function kpi_ncr_rate(int $companyId, string $from, string $to): float
{
    $ncr = db_count('ncr', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $batches = db_count('batches', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    return calc_defect_rate($ncr, max($batches, 1));
}

function kpi_capa_closure_rate(int $companyId, string $from, string $to): float
{
    $total = db_count('capa', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $closed = db_count('capa', "company_id=? AND status IN ('closed','effective') AND created_at BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]);
    if ($total <= 0) return 100.0;
    return round(($closed / $total) * 100, 2);
}

function kpi_complaint_rate(int $companyId, string $from, string $to): float
{
    $complaints = db_count('customer_complaints', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    $batches = db_count('batches', 'company_id=? AND created_at BETWEEN ? AND ?', 'iss', [$companyId, $from, $to]);
    return calc_defect_rate($complaints, max($batches, 1));
}

function kpi_audit_score(int $companyId, string $from, string $to): float
{
    $avg = db_fetch_value(
        "SELECT AVG(score/NULLIF(max_score,0)*100) FROM audits WHERE company_id=? AND status='completed' AND completed_date BETWEEN ? AND ?",
        'iss', [$companyId, $from, $to]
    );
    return $avg !== null ? round((float)$avg, 2) : 100.0;
}

function kpi_supplier_quality_score(int $companyId): float
{
    $avg = db_fetch_value("SELECT AVG(overall_score) FROM supplier_scorecards WHERE company_id=?", 'i', [$companyId]);
    return $avg !== null ? round((float)$avg, 2) : 100.0;
}

function kpi_oee(int $companyId, string $from, string $to): float
{
    $avg = db_fetch_value("SELECT AVG(oee) FROM oee_records WHERE company_id=? AND record_date BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]);
    return $avg !== null ? round((float)$avg, 2) : 0.0;
}

function kpi_scrap_rate(int $companyId, string $from, string $to): float
{
    $scrap = (float)(db_fetch_value("SELECT SUM(quantity) FROM scrap_records WHERE company_id=? AND record_date BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]) ?? 0);
    $produced = (float)(db_fetch_value("SELECT SUM(quantity_produced) FROM batches WHERE company_id=? AND production_date BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]) ?? 0);
    return calc_scrap_rate($scrap, max($produced, 1));
}

function kpi_rework_rate(int $companyId, string $from, string $to): float
{
    $rework = (float)(db_fetch_value("SELECT SUM(quantity) FROM rework_records WHERE company_id=? AND record_date BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]) ?? 0);
    $produced = (float)(db_fetch_value("SELECT SUM(quantity_produced) FROM batches WHERE company_id=? AND production_date BETWEEN ? AND ?", 'iss', [$companyId, $from, $to]) ?? 0);
    return calc_rework_rate($rework, max($produced, 1));
}

function get_kpi_snapshot_range(int $companyId, string $from, string $to): array
{
    return [
        'defect_rate' => kpi_defect_rate($companyId, $from, $to),
        'fpy' => kpi_fpy($companyId, $from, $to),
        'inspection_compliance' => kpi_inspection_compliance($companyId, $from, $to),
        'ncr_rate' => kpi_ncr_rate($companyId, $from, $to),
        'capa_closure_rate' => kpi_capa_closure_rate($companyId, $from, $to),
        'complaint_rate' => kpi_complaint_rate($companyId, $from, $to),
        'audit_score' => kpi_audit_score($companyId, $from, $to),
        'supplier_score' => kpi_supplier_quality_score($companyId),
        'oee' => kpi_oee($companyId, $from, $to),
        'scrap_rate' => kpi_scrap_rate($companyId, $from, $to),
        'rework_rate' => kpi_rework_rate($companyId, $from, $to),
    ];
}

function get_kpi_snapshot(int $companyId, string $period = '30d'): array
{
    [$from, $to] = kpi_date_range($period);
    return get_kpi_snapshot_range($companyId, $from, $to);
}

/**
 * Weighted overall company quality score from a pre-computed snapshot (current or a custom/previous period range).
 */
function get_company_quality_score_from_snapshot(int $companyId, array $snapshot): array
{
    $definitions = db_fetch_all(
        "SELECT * FROM kpi_definitions WHERE (company_id = ? OR company_id IS NULL) AND is_active = 1
         ORDER BY (company_id IS NOT NULL) DESC", 'i', [$companyId]
    );
    $seen = [];
    $components = [];
    foreach ($definitions as $def) {
        if (isset($seen[$def['kpi_key']])) continue; // company override takes precedence
        $seen[$def['kpi_key']] = true;
        if (!isset($snapshot[$def['kpi_key']])) continue;
        $value = $snapshot[$def['kpi_key']];
        $normalized = $def['direction'] === 'lower_better' ? max(0, 100 - $value) : $value;
        $components[$def['kpi_key']] = [
            'name' => $def['name'], 'value' => $value, 'normalized' => $normalized, 'weight' => (float)$def['weight'],
            'direction' => $def['direction'],
            'rag' => kpi_rag_status($value, (float)$def['green_threshold'], (float)$def['amber_threshold'], $def['direction']),
        ];
    }
    $weightedInputs = array_map(fn($c) => ['value' => $c['normalized'], 'weight' => $c['weight']], $components);
    $score = calc_weighted_score($weightedInputs);
    return ['score' => $score, 'rag' => $score >= 85 ? 'green' : ($score >= 70 ? 'amber' : 'red'), 'components' => $components, 'snapshot' => $snapshot];
}

/**
 * Weighted overall company quality score using kpi_definitions (company-specific override, else global default).
 */
function get_company_quality_score(int $companyId, string $period = '30d'): array
{
    return get_company_quality_score_from_snapshot($companyId, get_kpi_snapshot($companyId, $period));
}

function kpi_rag_status(float $value, float $greenThreshold, float $amberThreshold, string $direction): string
{
    if ($direction === 'lower_better') {
        if ($value <= $greenThreshold) return 'green';
        if ($value <= $amberThreshold) return 'amber';
        return 'red';
    }
    if ($value >= $greenThreshold) return 'green';
    if ($value >= $amberThreshold) return 'amber';
    return 'red';
}

function get_department_heatmap(int $companyId, string $period = '30d'): array
{
    [$from, $to] = kpi_date_range($period);
    $departments = db_fetch_all("SELECT id, name FROM departments WHERE company_id = ? AND status='active'", 'i', [$companyId]);
    $rows = [];
    foreach ($departments as $dept) {
        $openIssues = db_count('quality_issues', "company_id=? AND department_id=? AND status<>'closed'", 'ii', [$companyId, $dept['id']]);
        $totalIssues = db_count('quality_issues', 'company_id=? AND department_id=? AND created_at BETWEEN ? AND ?', 'iiss', [$companyId, $dept['id'], $from, $to]);
        $totalSubmissions = db_count('tool_submissions', 'company_id=? AND department_id=? AND created_at BETWEEN ? AND ?', 'iiss', [$companyId, $dept['id'], $from, $to]);
        $missed = db_count('tool_submissions', "company_id=? AND department_id=? AND status='missed' AND created_at BETWEEN ? AND ?", 'iiss', [$companyId, $dept['id'], $from, $to]);
        $compliance = $totalSubmissions > 0 ? round((($totalSubmissions - $missed) / $totalSubmissions) * 100, 1) : 100.0;
        $defectRate = calc_defect_rate($totalIssues, max($totalSubmissions, 1));
        $qualityScore = round(max(0, 100 - $defectRate - ($openIssues * 2)), 1);
        $rag = $qualityScore >= 85 && $compliance >= 90 ? 'green' : (($qualityScore >= 65 && $compliance >= 70) ? 'amber' : 'red');
        $rows[] = [
            'id' => (int)$dept['id'], 'department' => $dept['name'], 'quality_score' => $qualityScore, 'defect_rate' => $defectRate,
            'open_issues' => $openIssues, 'compliance' => $compliance, 'rag' => $rag,
        ];
    }
    return $rows;
}
