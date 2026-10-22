<?php
/** Shared bootstrap for AJAX endpoints: JSON responses, POST + CSRF required for writes. */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_init.php') {
    http_response_code(404);
    exit;
}
define('UC_ROOT', dirname(__DIR__));
require UC_ROOT . '/includes/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function ajax_require_post(): void
{
    if (!is_post()) {
        json_response(['ok' => false, 'message' => 'Method not allowed'], 405);
    }
    csrf_check();
}

/** Renders a partial into a string. */
function render_partial(string $file, array $vars = []): string
{
    ob_start();
    partial($file, $vars);
    return (string) ob_get_clean();
}
