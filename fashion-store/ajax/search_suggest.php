<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$stmt = mysqli_prepare($mysqli, "SELECT id, name, slug, regular_price, sale_price FROM products WHERE status = 'active' AND (name LIKE CONCAT('%',?,'%') OR sku LIKE CONCAT('%',?,'%')) LIMIT 8");
mysqli_stmt_bind_param($stmt, 'ss', $q, $q);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$out = [];
while ($p = mysqli_fetch_assoc($res)) {
    $out[] = [
        'name' => $p['name'],
        'slug' => $p['slug'],
        'price' => format_price($p['sale_price'] ?: $p['regular_price']),
        'image' => product_primary_image($mysqli, $p['id']),
    ];
}
echo json_encode($out);
