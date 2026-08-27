<?php
/**
 * KPI Engine - daily snapshot job. Run once per day via system cron:
 *   php /path/to/fmcg-qms/cron/kpi-recalc.php
 * Stores today's KPI values into kpi_records for trend charts/history.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$companies = db_all("SELECT id FROM companies WHERE status='active'", []);
$today = date('Y-m-d');
$count = 0;

foreach ($companies as $company) {
    $cid = (int)$company['id'];
    $snapshot = get_kpi_snapshot($cid, '30d');
    foreach ($snapshot as $key => $value) {
        $existing = db_one("SELECT id FROM kpi_records WHERE company_id=? AND kpi_key=? AND period_date=?", [$cid, $key, $today]);
        if ($existing) {
            db_exec("UPDATE kpi_records SET value=? WHERE id=?", [$value, $existing['id']]);
        } else {
            db_exec("INSERT INTO kpi_records (company_id, kpi_key, period_date, value, created_at) VALUES (?,?,?,?,NOW())", [$cid, $key, $today, $value]);
        }
        $count++;
    }

    $overdueCapa = count_overdue_capa($cid);
    if ($overdueCapa > 0) {
        notify_company_managers($cid, 'capa_overdue', 'Overdue CAPA Alert', "$overdueCapa CAPA action(s) are overdue.", base_url('manager/capa.php?overdue=1'), 'warning');
    }
}

echo "KPI recalculation complete. $count record(s) written for " . count($companies) . " companies.\n";
