<?php
/** Admin presentation preview (no tracking). ?embed=1 for device frames, ?print=1 to print. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/viewer.php';
require_login();
$p = pm_get((int) ($_GET['id'] ?? 0));
if (!$p) {
    http_response_code(404);
    exit('Proposal not found.');
}
render_viewer($p, pm_pages((int) $p['id']), 'admin', ['embed' => !empty($_GET['embed']), 'print' => !empty($_GET['print'])]);
