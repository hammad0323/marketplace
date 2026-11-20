<?php
/** Analytics for the Super Admin dashboard and shop owner reports. */

/** Platform-wide numbers for the admin dashboard. */
function admin_stats(): array
{
    $shops = db_all('SELECT * FROM shops WHERE deleted_at IS NULL');
    $counts = ['total' => count($shops), 'active' => 0, 'suspended' => 0, 'expired' => 0, 'grace' => 0, 'none' => 0, 'mrr' => 0.0];
    $expiring = [];
    foreach ($shops as $s) {
        $st = subscription_state((int) $s['id'], $s);
        if ($s['status'] === 'deactivated') {
            continue;
        }
        $state = $st['state'];
        $counts[$state === 'active' ? 'active' : ($state === 'suspended' ? 'suspended' : ($state === 'grace' ? 'grace' : ($state === 'none' ? 'none' : 'expired')))]++;
        if (in_array($state, ['active', 'grace'], true)) {
            $counts['mrr'] += $st['price'];
        }
        if (in_array($state, ['active', 'grace', 'expired'], true) && $st['days_left'] !== null && $st['days_left'] <= (int) setting('reminder_days_before', 5) && $st['days_left'] >= -30) {
            $expiring[] = ['shop' => $s, 'state' => $st];
        }
    }
    usort($expiring, fn($a, $b) => $a['state']['days_left'] <=> $b['state']['days_left']);
    $o = db_row("SELECT COUNT(*) total, SUM(status = 'completed') completed, SUM(status IN ('submitted','awaiting_approval','accepted','quoted','awaiting_payment','payment_confirmed','printing','ready')) pending FROM orders");
    return $counts + [
        'collected' => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'confirmed'"),
        'collected_month' => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'confirmed' AND paid_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        'pending_payments' => (int) db_val("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending'"),
        'pending_payments_amount' => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM subscription_payments WHERE status = 'pending'"),
        'orders_total' => (int) $o['total'], 'orders_completed' => (int) $o['completed'], 'orders_pending' => (int) $o['pending'],
        'new_shops_month' => (int) db_val("SELECT COUNT(*) FROM shops WHERE deleted_at IS NULL AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        'customers' => (int) db_val('SELECT COUNT(*) FROM customers'),
        'expiring' => array_slice($expiring, 0, 12),
    ];
}

/** Last $n months as ['2026-06' => label]. */
function last_months(int $n = 12): array
{
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $t = strtotime(date('Y-m-01') . " -$i months");
        $out[date('Y-m', $t)] = date('M y', $t);
    }
    return $out;
}

function admin_charts(): array
{
    $months = last_months(12);
    $from = array_key_first($months) . '-01';
    $series = function (string $sql) use ($months, $from) {
        $rows = array_column(db_all($sql, [$from]), 'v', 'm');
        return array_map(fn($m) => (float) ($rows[$m] ?? 0), array_keys($months));
    };
    $s = admin_stats();
    return [
        'labels' => array_values($months),
        'revenue' => $series("SELECT DATE_FORMAT(paid_on, '%Y-%m') m, SUM(amount) v FROM subscription_payments WHERE status = 'confirmed' AND paid_on >= ? GROUP BY m"),
        'shops' => $series("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) v FROM shops WHERE deleted_at IS NULL AND created_at >= ? GROUP BY m"),
        'orders' => $series("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) v FROM orders WHERE created_at >= ? GROUP BY m"),
        'completed' => $series("SELECT DATE_FORMAT(created_at, '%Y-%m') m, COUNT(*) v FROM orders WHERE status = 'completed' AND created_at >= ? GROUP BY m"),
        'status' => ['Active' => $s['active'], 'Grace period' => $s['grace'], 'Expired' => $s['expired'] + $s['none'], 'Suspended' => $s['suspended']],
    ];
}

/** Owner dashboard counters. Revenue counts completed or paid orders. */
function shop_dashboard(int $shopId): array
{
    $r = db_row("SELECT
        SUM(DATE(created_at) = CURDATE()) today,
        SUM(status IN ('submitted','awaiting_approval')) pending,
        SUM(status IN ('accepted','quoted','awaiting_payment','payment_confirmed')) accepted,
        SUM(status = 'printing') printing, SUM(status = 'ready') ready,
        SUM(status = 'completed') completed, SUM(status IN ('cancelled','rejected')) cancelled
        FROM orders WHERE shop_id = ?", [$shopId]);
    $rev = fn(string $where) => (float) db_val("SELECT COALESCE(SUM(COALESCE(final_amount, quoted_amount, estimated_amount)),0) FROM orders WHERE shop_id = ? AND (status = 'completed' OR payment_status = 'paid') AND $where", [$shopId]);
    return array_map('intval', $r ?: []) + [
        'revenue_today' => $rev('DATE(COALESCE(closed_at, updated_at, created_at)) = CURDATE()'),
        'revenue_month' => $rev("COALESCE(closed_at, updated_at, created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    ];
}

/** Owner reports for a period: day|week|month. */
function shop_report(int $shopId, string $period = 'month'): array
{
    $days = ['day' => 1, 'week' => 7, 'month' => 30, 'quarter' => 90][$period] ?? 30;
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $sum = db_row("SELECT COUNT(*) orders, SUM(status = 'completed') completed, SUM(status IN ('cancelled','rejected')) cancelled,
        COALESCE(SUM(CASE WHEN status = 'completed' OR payment_status = 'paid' THEN COALESCE(final_amount, quoted_amount, estimated_amount) END),0) revenue
        FROM orders WHERE shop_id = ? AND created_at >= ?", [$shopId, $from]);
    $done = max(1, (int) db_val("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND created_at >= ? AND (status = 'completed' OR payment_status = 'paid')", [$shopId, $from]));
    $daily = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $daily[date('Y-m-d', strtotime("-$i days"))] = ['orders' => 0, 'revenue' => 0.0];
    }
    foreach (db_all("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(CASE WHEN status = 'completed' OR payment_status = 'paid' THEN COALESCE(final_amount, quoted_amount, estimated_amount) END),0) r
        FROM orders WHERE shop_id = ? AND created_at >= ? GROUP BY d", [$shopId, $from]) as $row) {
        $daily[$row['d']] = ['orders' => (int) $row['n'], 'revenue' => (float) $row['r']];
    }
    $cats = db_all("SELECT COALESCE(c.name, 'Other') name, COUNT(*) n FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN categories c ON c.id = oi.category_id
        WHERE o.shop_id = ? AND o.created_at >= ? GROUP BY name ORDER BY n DESC LIMIT 8", [$shopId, $from]);
    return [
        'period' => $period, 'from' => substr($from, 0, 10), 'orders' => (int) $sum['orders'], 'completed' => (int) $sum['completed'], 'cancelled' => (int) $sum['cancelled'],
        'revenue' => (float) $sum['revenue'], 'average_order_value' => round((float) $sum['revenue'] / $done, 2),
        'daily' => array_map(fn($d, $v) => ['date' => $d] + $v, array_keys($daily), $daily),
        'top_categories' => array_map(fn($c) => ['name' => $c['name'], 'count' => (int) $c['n']], $cats),
    ];
}
