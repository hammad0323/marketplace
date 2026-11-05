<?php
// Daily: expire subscriptions past end_date (users fall back to the default plan).
require __DIR__ . '/_cli.php';
$rows = db_all("SELECT subscription_id, user_id FROM user_subscriptions WHERE status = 'active' AND end_date IS NOT NULL AND end_date < UTC_DATE()");
foreach ($rows as $r) {
    db_run("UPDATE user_subscriptions SET status = 'expired' WHERE subscription_id = ?", [$r['subscription_id']]);
    notify((int)$r['user_id'], 'Plan expired', 'Your plan has expired and limits have returned to the free plan. Contact your administrator to renew.', 'warning', '/profile');
}
db_run("UPDATE emergency_messages SET status = 'expired' WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at < UTC_TIMESTAMP()");
out(count($rows) . ' subscription(s) expired');
