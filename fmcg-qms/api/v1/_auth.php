<?php
/**
 * API v1 - simple Bearer token authentication scoped to one company.
 * Each company has a generated API key stored in company_settings (key: api_key).
 * Prepared for future ERP/MES/BI integrations per the platform's API-ready design.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

function api_authenticate(): int
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        json_response(['success' => false, 'message' => 'Missing Authorization: Bearer <api_key> header.'], 401);
    }
    $token = $m[1];
    $row = db_one("SELECT company_id FROM company_settings WHERE setting_key='api_key' AND setting_value=?", [$token]);
    if (!$row) {
        json_response(['success' => false, 'message' => 'Invalid API key.'], 401);
    }
    $company = db_one("SELECT status FROM companies WHERE id=?", [$row['company_id']]);
    if (!$company || $company['status'] !== 'active') {
        json_response(['success' => false, 'message' => 'Company account is inactive.'], 403);
    }
    return (int)$row['company_id'];
}

function api_paginate(): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 25)));
    return [$page, $perPage, ($page - 1) * $perPage];
}
