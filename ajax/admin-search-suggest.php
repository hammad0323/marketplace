<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$q = clean_input($_GET['q'] ?? '');
if (mb_strlen($q) < 2 || !in_array($type, ['customer', 'provider'], true)) {
    echo json_encode([]);
    exit;
}
$like = '%' . $q . '%';

$suggestions = [];
if ($type === 'customer') {
    foreach (db_select(
        $conn,
        'SELECT u.id, u.name, u.email, u.status FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.slug = "customer" AND (u.name LIKE ? OR u.email LIKE ?) ORDER BY u.name LIMIT 8',
        [$like, $like]
    ) as $c) {
        $suggestions[] = ['label' => $c['name'], 'sublabel' => $c['email'], 'status' => $c['status']];
    }
} else {
    foreach (db_select(
        $conn,
        'SELECT p.id, p.business_name, u.email, p.status FROM providers p JOIN users u ON u.id = p.user_id
         WHERE p.business_name LIKE ? OR u.email LIKE ? ORDER BY p.business_name LIMIT 8',
        [$like, $like]
    ) as $p) {
        $suggestions[] = ['label' => $p['business_name'], 'sublabel' => $p['email'], 'status' => $p['status']];
    }
}

echo json_encode($suggestions);
