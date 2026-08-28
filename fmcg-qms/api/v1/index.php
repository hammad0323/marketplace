<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
json_response([
    'success' => true,
    'name' => app_name() . ' API',
    'version' => 'v1',
    'authentication' => 'Authorization: Bearer <company_api_key> (generate in Manager > Settings)',
    'endpoints' => [
        'GET /api/v1/batches' => 'List batches (paginated)',
        'GET /api/v1/issues' => 'List quality issues (paginated)',
        'GET /api/v1/kpis' => 'Latest KPI snapshot for the authenticated company',
    ],
]);
