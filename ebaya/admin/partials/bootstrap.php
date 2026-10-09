<?php
/** Loaded first by every admin page. */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require __DIR__ . '/helpers.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
