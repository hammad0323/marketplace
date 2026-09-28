<?php
/** Every admin page (except login) requires this file first. */
define('BASE', '../');
require __DIR__ . '/../../config.php';

if (empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

/** Guard for POST actions: valid CSRF or bounce back. */
function admin_post_guard(string $back): void
{
    if (!csrf_ok()) {
        flash('error', 'Security token expired — please try again.');
        redirect($back);
    }
}

function status_badge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}
