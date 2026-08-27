<?php
require_once __DIR__ . '/_auth.php';
$cid = api_authenticate();
$quality = get_company_quality_score($cid);
json_response(['success' => true, 'quality_score' => $quality['score'], 'rag' => $quality['rag'], 'snapshot' => $quality['snapshot']]);
