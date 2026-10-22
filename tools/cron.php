<?php
/**
 * Optional housekeeping, e.g. cPanel » Cron Jobs, every 30 minutes:
 *   php /home/USER/public_html/tools/cron.php
 * - Cancels unpaid online orders after the configured timeout (releasing stock)
 * - Re-checks pending online payments with the gateways
 * - Removes stale guest carts, rate-limit rows and expired reset tokens
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
define('UC_ROOT', dirname(__DIR__));
require UC_ROOT . '/includes/bootstrap.php';

$verified = 0;
foreach (db_all("SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.status = 'pending' AND p.method <> 'cod'
    AND p.created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND p.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
    AND EXISTS (SELECT 1 FROM payment_transactions t WHERE t.payment_id = p.id AND t.event_type = 'initiate') LIMIT 50") as $p) {
    $res = payment_verify($p);
    $verified += !empty($res['paid']) ? 1 : 0;
}
$expired = orders_expire_unpaid();
$carts = db_exec('DELETE FROM carts WHERE customer_id IS NULL AND COALESCE(updated_at, created_at) < DATE_SUB(NOW(), INTERVAL 60 DAY)');
db_exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
db_exec('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
echo date('c') . " verified_paid=$verified expired_orders=$expired old_carts=$carts\n";
