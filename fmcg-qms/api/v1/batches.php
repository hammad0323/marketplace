<?php
require_once __DIR__ . '/_auth.php';
$cid = api_authenticate();
[$page, $perPage, $offset] = api_paginate();

$total = db_count('batches', 'company_id=?', 'i', [$cid]);
$rows = db_all(
    "SELECT b.id, b.batch_number, p.name AS product, b.production_date, b.expiry_date, b.status, b.quantity_produced
     FROM batches b LEFT JOIN products p ON p.id=b.product_id WHERE b.company_id=? ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset",
    [$cid]
);
json_response(['success' => true, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'data' => $rows]);
