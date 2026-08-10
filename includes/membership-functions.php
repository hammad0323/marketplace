<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function activate_membership($conn, $providerId, $planId, $paymentId = null)
{
    $plan = db_select_one($conn, 'SELECT * FROM membership_plans WHERE id = ?', [(int) $planId]);
    if (!$plan) {
        return false;
    }

    $endsAt = null;
    if ($plan['billing_cycle'] === 'monthly') {
        $endsAt = date('Y-m-d H:i:s', strtotime('+1 month'));
    } elseif ($plan['billing_cycle'] === 'yearly') {
        $endsAt = date('Y-m-d H:i:s', strtotime('+1 year'));
    }

    db_execute($conn, 'UPDATE provider_memberships SET status = "expired" WHERE provider_id = ? AND status = "active"', [(int) $providerId]);
    db_insert_get_id(
        $conn,
        'INSERT INTO provider_memberships (provider_id, plan_id, starts_at, ends_at, status, payment_id) VALUES (?, ?, NOW(), ?, "active", ?)',
        [(int) $providerId, (int) $planId, $endsAt, $paymentId]
    );
    db_execute($conn, 'UPDATE providers SET membership_plan_id = ? WHERE id = ?', [(int) $planId, (int) $providerId]);

    return true;
}
