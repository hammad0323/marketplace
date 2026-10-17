<?php
/**
 * PREVIEW — shows the proposal exactly as the client sees it (views are not counted).
 *   ?embed=1  used inside the device preview (desktop / tablet / mobile)
 *   ?print=1  opens the print window
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/viewer.php';
require_login();
$p = pm_get((int) ($_GET['id'] ?? 0));
if (!$p) {
    http_response_code(404);
    exit('Proposal not found.');
}
render_viewer($p, pm_pages((int) $p['id']), 'admin', ['embed' => !empty($_GET['embed']), 'print' => !empty($_GET['print'])]);
