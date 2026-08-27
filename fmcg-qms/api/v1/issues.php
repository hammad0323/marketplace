<?php
require_once __DIR__ . '/_auth.php';
$cid = api_authenticate();
[$page, $perPage, $offset] = api_paginate();

$total = db_count('quality_issues', 'company_id=?', 'i', [$cid]);
$rows = db_all(
    "SELECT id, issue_number, severity, status, defect_type, description, created_at, closed_at
     FROM quality_issues WHERE company_id=? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    [$cid]
);
json_response(['success' => true, 'page' => $page, 'per_page' => $perPage, 'total' => $total, 'data' => $rows]);
