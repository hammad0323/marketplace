<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$q = clean_input($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}
$like = '%' . $q . '%';

$suggestions = [];
foreach (db_select($conn, 'SELECT name, slug FROM cities WHERE is_active = 1 AND name LIKE ? LIMIT 4', [$like]) as $c) {
    $suggestions[] = ['type' => 'City', 'icon' => 'bi-geo-alt', 'label' => $c['name'], 'url' => url('/pages/city.php') . '?slug=' . $c['slug']];
}
foreach (db_select($conn, 'SELECT name, slug FROM categories WHERE is_active = 1 AND name LIKE ? LIMIT 3', [$like]) as $cat) {
    $suggestions[] = ['type' => 'Category', 'icon' => 'bi-grid', 'label' => $cat['name'], 'url' => url('/pages/category.php') . '?slug=' . $cat['slug']];
}
foreach (db_select($conn, 'SELECT title, slug FROM services WHERE status = "approved" AND title LIKE ? LIMIT 5', [$like]) as $s) {
    $suggestions[] = ['type' => 'Service', 'icon' => 'bi-search', 'label' => $s['title'], 'url' => url('/pages/service.php') . '?slug=' . $s['slug']];
}

echo json_encode($suggestions);
