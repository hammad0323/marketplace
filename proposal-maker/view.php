<?php
/**
 * PUBLIC CLIENT PAGE — /proposal/view/{token}  (or view.php?token=...)
 *
 * No login needed. Each visit by a client is counted:
 * views + 1, first/last viewed date, and status Draft/Sent → Viewed.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/viewer.php';

$token = (string) ($_GET['token'] ?? '');
$p = pm_get_by_token($token);
if (!$p) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not found</title><div style="font-family:system-ui;text-align:center;padding:80px 20px;color:#334155"><h2>Proposal not found</h2><p>This link is invalid or the proposal has been removed.</p></div>';
    exit;
}
pm_refresh_expired();
$p = pm_get_by_token($token);

// Track views from clients (logged-in team members are not counted)
if (!is_logged_in() && empty($_GET['print'])) {
    $now = date('Y-m-d H:i:s');
    $key = 'viewed_' . $p['id'];
    $upd = ['last_viewed_at' => $now];
    if (empty($_SESSION[$key]) || $_SESSION[$key] < time() - 1800) {
        db_query('UPDATE proposals SET views = views + 1 WHERE id = ?', [(int) $p['id']]);
        $_SESSION[$key] = time();
        log_activity((int) $p['id'], 'viewed', 'Viewed by client from ' . client_ip(), 0);
    }
    if (!$p['first_viewed_at']) $upd['first_viewed_at'] = $now;
    if (in_array($p['status'], ['draft', 'sent'], true)) {
        $upd['status'] = 'viewed';
        if (!$p['sent_at']) $upd['sent_at'] = $now;
    }
    db_update('proposals', $upd, 'id = ?', [(int) $p['id']]);
    $p = pm_get((int) $p['id']);
}
render_viewer($p, pm_pages((int) $p['id']), 'public', ['print' => !empty($_GET['print'])]);
