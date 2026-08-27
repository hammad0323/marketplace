<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$labels = report_type_labels();
$type = get_param('type', 'quality_issues');
if (!isset($labels[$type])) $type = 'quality_issues';
$dateFrom = get_param('date_from', date('Y-m-d', strtotime('-30 days')));
$dateTo = get_param('date_to', date('Y-m-d'));

$rows = report_rows($type, $cid, $dateFrom, $dateTo);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['No data for selected filters']);
}
fclose($out);
log_activity($cid, current_user_id(), 'export', 'report', null, "Exported $type report as CSV");
exit;
