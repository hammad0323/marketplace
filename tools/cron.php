<?php
/**
 * Scheduled maintenance. Run from cPanel → Cron Jobs every 15 minutes:
 *   php /home/USER/public_html/tools/cron.php
 *
 *  - Releases stock held by online-payment orders that were never paid
 *    (after "unpaid_order_timeout_hours"), first asking the provider once.
 *  - Purges expired rate-limit rows, password-reset tokens and abandoned guest carts.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$hours = max(1, (int) setting('unpaid_order_timeout_hours', '2'));
// Last chance: ask providers about stale online payments before cancelling them.
foreach (db_all("SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.method <> 'cod' AND p.status IN ('pending','processing') AND o.status = 'pending' AND p.created_at < ?", [date('Y-m-d H:i:s', time() - $hours * 3600)]) as $pay) {
    if (gateway_is_configured($pay['provider'])) {
        echo $pay['reference'] . ': ' . payment_verify_with_provider($pay) . PHP_EOL;
    }
}
$released = cancel_stale_unpaid_orders();
db_exec('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
db_exec('DELETE FROM password_resets WHERE expires_at < ? OR used_at IS NOT NULL', [date('Y-m-d H:i:s', time() - 86400)]);
$carts = db_exec('DELETE FROM carts WHERE customer_id IS NULL AND updated_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 45)]);
echo date('c') . " cron: released $released unpaid order(s), removed $carts stale guest cart(s)\n";
