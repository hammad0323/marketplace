<?php
/**
 * Admin bootstrap: every admin page starts with
 *   require __DIR__ . '/../includes/init.php';   (or '/includes/init.php' from /admin)
 *   admin_require('products');
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'init.php') {
    http_response_code(404);
    exit;
}
define('UC_ROOT', dirname(__DIR__, 2));
define('UC_ADMIN', true);
require UC_ROOT . '/includes/bootstrap.php';
require __DIR__ . '/functions.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

/**
 * Requires a signed-in admin with the given permission (server-side check on every request).
 * POST requests are CSRF-checked here so individual pages cannot forget.
 */
function admin_require(?string $perm = null): array
{
    $admin = current_admin();
    if (!$admin) {
        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php');
    }
    if ((int) $admin['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'account.php') {
        flash('warning', 'Please choose a new password before continuing.');
        redirect('admin/account.php');
    }
    if ($perm && !admin_can($perm)) {
        http_response_code(403);
        admin_header('Access denied');
        echo '<div class="a-empty"><i class="bi bi-shield-lock"></i><h2>Access denied</h2><p>Your role does not include permission for this area.</p><a class="btn btn-dark" href="' . e(url('admin/')) . '">Back to dashboard</a></div>';
        admin_footer();
        exit;
    }
    if (is_post()) {
        csrf_check();
    }
    return $admin;
}
