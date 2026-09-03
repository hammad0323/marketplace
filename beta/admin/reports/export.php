<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$type = $_GET['type'] ?? '';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '-export.csv"');
$out = fopen('php://output', 'w');

if ($type === 'orders') {
    fputcsv($out, ['Order Number', 'Customer', 'Total', 'Status', 'Payment Status', 'Date']);
    foreach (db_fetch_all("SELECT o.*, c.first_name, c.last_name FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.created_at DESC") as $r) {
        fputcsv($out, [$r['order_number'], $r['first_name'] . ' ' . $r['last_name'], $r['grand_total'], $r['order_status'], $r['payment_status'], $r['created_at']]);
    }
} elseif ($type === 'customers') {
    fputcsv($out, ['Name', 'Email', 'Phone', 'Status', 'Joined']);
    foreach (db_fetch_all("SELECT * FROM customers ORDER BY created_at DESC") as $r) {
        fputcsv($out, [$r['first_name'] . ' ' . $r['last_name'], $r['email'], $r['phone'], $r['status'], $r['created_at']]);
    }
} elseif ($type === 'shops') {
    fputcsv($out, ['Shop Name', 'Owner', 'City', 'Status', 'Rating']);
    foreach (db_fetch_all("SELECT s.*, o.name as owner_name FROM shops s JOIN shop_owners o ON o.id=s.owner_id") as $r) {
        fputcsv($out, [$r['shop_name'], $r['owner_name'], $r['city'], $r['status'], $r['rating_avg']]);
    }
} elseif ($type === 'products') {
    fputcsv($out, ['Name', 'SKU', 'Shop', 'Price', 'Stock', 'Status']);
    foreach (db_fetch_all("SELECT p.*, s.shop_name FROM products p JOIN shops s ON s.id=p.shop_id") as $r) {
        fputcsv($out, [$r['name'], $r['sku'], $r['shop_name'], $r['regular_price'], $r['stock_quantity'], $r['status']]);
    }
} elseif ($type === 'commissions') {
    fputcsv($out, ['Invoice', 'Shop', 'Revenue', 'Commission', 'Due Date', 'Status']);
    foreach (db_fetch_all("SELECT cm.*, s.shop_name FROM commissions cm JOIN shops s ON s.id=cm.shop_id") as $r) {
        fputcsv($out, [$r['invoice_number'], $r['shop_name'], $r['revenue'], $r['commission_amount'], $r['due_date'], $r['payment_status']]);
    }
}
fclose($out);
