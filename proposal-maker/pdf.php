<?php
/** PDF download: pdf.php?id=… (admin) or pdf.php?token=… (client) */
require __DIR__ . '/includes/bootstrap.php';

if (!empty($_GET['token'])) {
    $p = pm_get_by_token((string) $_GET['token']);
    if (!$p || empty(pm_merge_settings($p['settings'])['allow_download'])) {
        http_response_code(404);
        exit('Not available.');
    }
    if (!is_logged_in()) log_activity((int) $p['id'], 'downloaded', 'PDF downloaded by client', 0);
} else {
    require_login();
    $p = pm_get((int) ($_GET['id'] ?? 0));
    if (!$p) {
        http_response_code(404);
        exit('Proposal not found.');
    }
}

try {
    $pdf = pm_pdf($p, pm_pages((int) $p['id']));
} catch (Throwable $e) {
    http_response_code(500);
    exit('PDF generation failed: ' . e($e->getMessage()));
}
$name = pm_filename($p);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf;
