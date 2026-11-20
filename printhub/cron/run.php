<?php
/**
 * Scheduled tasks. Run hourly from cPanel → Cron Jobs:
 *   php /home/USER/public_html/cron/run.php
 * Safe to run more often: reminders and expiry notices are sent once per period.
 * Subscription expiry itself is enforced in real time (states are computed from dates), so a missed run never leaves a shop open.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/core/bootstrap.php';
$sub = subscription_daily_run();
$files = files_cleanup();
$msg = sprintf("[%s] reminders=%d expired=%d order-files-deleted=%d temp-deleted=%d\n", date('c'), $sub['reminded'], $sub['expired'], $files['orders'], $files['temp']);
echo $msg;
