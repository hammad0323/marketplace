<?php
/** Shared order filters for the list and the CSV export. Returns [whereSql, params, filters]. */
function order_filters(): array
{
    $f = [
        'q' => input('q', '', 'get'), 'status' => input('status', '', 'get'), 'payment_status' => input('payment_status', '', 'get'),
        'payment_method' => input('payment_method', '', 'get'), 'date_from' => input('date_from', '', 'get'), 'date_to' => input('date_to', '', 'get'),
    ];
    $where = ['1=1'];
    $params = [];
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ? OR o.tracking_number LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if (in_array($f['status'], ORDER_STATUSES, true)) {
        $where[] = 'o.status = ?';
        $params[] = $f['status'];
    }
    if (in_array($f['payment_status'], PAYMENT_STATUSES, true)) {
        $where[] = 'o.payment_status = ?';
        $params[] = $f['payment_status'];
    }
    if (in_array($f['payment_method'], ['cod', 'easypaisa', 'jazzcash', 'card'], true)) {
        $where[] = 'o.payment_method = ?';
        $params[] = $f['payment_method'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_from'])) {
        $where[] = 'o.created_at >= ?';
        $params[] = $f['date_from'] . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date_to'])) {
        $where[] = 'o.created_at <= ?';
        $params[] = $f['date_to'] . ' 23:59:59';
    }
    return [implode(' AND ', $where), $params, $f];
}
