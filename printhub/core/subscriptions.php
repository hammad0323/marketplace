<?php
/**
 * Monthly subscriptions. One row per paid period; a shop's state is computed from the latest period:
 * active → (end date) → grace (still works, with a warning) → expired (owner tools restricted, ordering closed).
 * A suspended period or a suspended shop is restricted immediately.
 */

function subscription_default_price(): float
{
    return (float) setting('default_subscription_price', 1500);
}

function subscription_price_for(array $shop): float
{
    return $shop['subscription_price'] !== null ? (float) $shop['subscription_price'] : subscription_default_price();
}

function subscription_current(int $shopId): ?array
{
    return db_row("SELECT * FROM subscriptions WHERE shop_id = ? AND status <> 'cancelled' ORDER BY end_date DESC, id DESC LIMIT 1", [$shopId]);
}

/** ['state' => active|grace|expired|suspended|none, 'end_date', 'days_left', 'grace_until', 'price', 'label'] */
function subscription_state(int $shopId, ?array $shop = null): array
{
    $shop = $shop ?? shop_by_id($shopId);
    $sub = subscription_current($shopId);
    $price = $shop ? subscription_price_for($shop) : subscription_default_price();
    $base = ['subscription' => $sub, 'price' => $price, 'end_date' => $sub['end_date'] ?? null, 'grace_until' => null, 'days_left' => null];
    if ($shop && $shop['status'] === 'suspended') {
        return $base + ['state' => 'suspended', 'label' => 'Suspended by admin'];
    }
    if (!$sub) {
        return $base + ['state' => 'none', 'label' => 'No subscription'];
    }
    if ($sub['status'] === 'suspended') {
        return $base + ['state' => 'suspended', 'label' => 'Subscription suspended'];
    }
    $today = strtotime(date('Y-m-d'));
    $end = strtotime($sub['end_date']);
    $grace = strtotime('+' . (int) $sub['grace_days'] . ' days', $end);
    $base['grace_until'] = date('Y-m-d', $grace);
    $base['days_left'] = (int) floor(($end - $today) / 86400);
    if ($today <= $end) {
        return $base + ['state' => 'active', 'label' => 'Active'];
    }
    if ($today <= $grace) {
        return $base + ['state' => 'grace', 'label' => 'Expired — grace period until ' . date('j M', $grace)];
    }
    return $base + ['state' => 'expired', 'label' => 'Expired'];
}

function subscription_allows_work(int $shopId, ?array $shop = null): bool
{
    return in_array(subscription_state($shopId, $shop)['state'], ['active', 'grace'], true);
}

/**
 * Adds a new period. When the current period is still running (or in grace) the new one starts
 * the day after it ends, so renewing early never loses days.
 */
function subscription_add(int $shopId, int $months, ?string $startDate, ?int $adminId, ?string $notes = null, ?float $price = null): int
{
    $shop = shop_by_id($shopId);
    $cur = subscription_current($shopId);
    $start = $startDate ?: date('Y-m-d');
    if ($cur && $cur['status'] === 'active' && strtotime($cur['end_date']) >= strtotime(date('Y-m-d')) - (int) $cur['grace_days'] * 86400 && !$startDate) {
        $start = date('Y-m-d', strtotime($cur['end_date'] . ' +1 day'));
    }
    $end = date('Y-m-d', strtotime($start . ' +' . $months . ' months -1 day'));
    $id = db_insert('subscriptions', ['shop_id' => $shopId, 'price' => $price ?? subscription_price_for($shop), 'months' => $months, 'start_date' => $start,
        'end_date' => $end, 'grace_days' => (int) setting('default_grace_days', 3), 'status' => 'active', 'notes' => $notes, 'created_by' => $adminId]);
    activity($adminId ? 'admin' : 'system', $adminId, 'subscription_added', $shopId, 'subscription', $id, ['months' => $months, 'start' => $start, 'end' => $end]);
    notify('shop', $shopId, $shopId, null, 'subscription', 'Subscription active until ' . date('j M Y', strtotime($end)), 'Thank you! Your ' . platform_name() . ' subscription has been renewed.', 'owner/subscription.php');
    return $id;
}

/**
 * Records a manual payment (cash, bank transfer, JazzCash, Easypaisa). With $extendMonths > 0 the
 * subscription is renewed for that many months and the payment is linked to the new period.
 */
function subscription_record_payment(int $shopId, array $p, int $adminId): int
{
    return db_tx(function () use ($shopId, $p, $adminId) {
        $subId = null;
        if (($p['status'] ?? 'confirmed') === 'confirmed' && (int) ($p['extend_months'] ?? 0) > 0) {
            $subId = subscription_add($shopId, (int) $p['extend_months'], $p['start_date'] ?? null, $adminId, 'Renewed with payment', isset($p['monthly_price']) ? (float) $p['monthly_price'] : null);
        }
        $id = db_insert('subscription_payments', ['shop_id' => $shopId, 'subscription_id' => $subId, 'amount' => $p['amount'], 'method' => $p['method'],
            'reference' => $p['reference'] ?? null, 'paid_on' => $p['paid_on'], 'months_covered' => max(1, (int) ($p['extend_months'] ?? 1)),
            'proof_path' => $p['proof_path'] ?? null, 'status' => $p['status'] ?? 'confirmed', 'notes' => $p['notes'] ?? null, 'recorded_by' => $adminId]);
        activity('admin', $adminId, 'subscription_payment', $shopId, 'subscription_payment', $id, ['amount' => $p['amount'], 'method' => $p['method']]);
        return $id;
    });
}

/** Daily job: reminders before expiry, notices on expiry. Returns counts. */
function subscription_daily_run(): array
{
    $days = (int) setting('reminder_days_before', 5);
    $reminded = $expired = 0;
    foreach (db_all("SELECT s.*, sh.name shop_name, sh.email, sh.id sid FROM subscriptions s JOIN shops sh ON sh.id = s.shop_id
        WHERE s.status = 'active' AND sh.deleted_at IS NULL AND s.id = (SELECT s2.id FROM subscriptions s2 WHERE s2.shop_id = s.shop_id AND s2.status <> 'cancelled' ORDER BY s2.end_date DESC, s2.id DESC LIMIT 1)") as $s) {
        $left = (int) floor((strtotime($s['end_date']) - strtotime(date('Y-m-d'))) / 86400);
        if ($left >= 0 && $left <= $days && !$s['reminder_sent_at']) {
            notify('shop', (int) $s['sid'], (int) $s['sid'], null, 'subscription', 'Subscription ends on ' . date('j M', strtotime($s['end_date'])), 'Please renew to keep receiving online orders.', 'owner/subscription.php');
            notify('admin', null, (int) $s['sid'], null, 'subscription', $s['shop_name'] . ' expires in ' . $left . ' day' . ($left === 1 ? '' : 's'), 'Subscription ends ' . date('j M Y', strtotime($s['end_date'])), 'admin/shop.php?id=' . $s['sid']);
            if ($s['email']) {
                send_mail($s['email'], platform_name() . ' subscription reminder', "Your subscription for {$s['shop_name']} ends on " . date('j M Y', strtotime($s['end_date'])) . ". Please renew to keep receiving online orders.");
            }
            db_exec('UPDATE subscriptions SET reminder_sent_at = NOW() WHERE id = ?', [(int) $s['id']]);
            $reminded++;
        }
        $graceEnd = strtotime($s['end_date'] . ' +' . (int) $s['grace_days'] . ' days');
        if (strtotime(date('Y-m-d')) > $graceEnd && !$s['expired_notified_at']) {
            notify('shop', (int) $s['sid'], (int) $s['sid'], null, 'subscription', 'Subscription expired', 'Online ordering is paused until your subscription is renewed.', 'owner/subscription.php');
            notify('admin', null, (int) $s['sid'], null, 'subscription', $s['shop_name'] . ' subscription expired', 'Ordering is closed for this shop.', 'admin/shop.php?id=' . $s['sid']);
            db_exec('UPDATE subscriptions SET expired_notified_at = NOW() WHERE id = ?', [(int) $s['id']]);
            activity('system', null, 'subscription_expired', (int) $s['sid'], 'subscription', (int) $s['id']);
            $expired++;
        }
    }
    return ['reminded' => $reminded, 'expired' => $expired];
}

function payment_methods(): array
{
    return ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'other' => 'Other'];
}
