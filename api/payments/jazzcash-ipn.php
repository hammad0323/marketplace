<?php
/**
 * JazzCash IPN (server-to-server). Configure this URL in the JazzCash merchant portal:
 *   https://your-domain/api/payments/jazzcash-ipn.php
 */
require dirname(__DIR__) . '/_init.php';
$data = $_POST ?: (json_decode((string) file_get_contents('php://input'), true) ?: []);
$res = jazzcash_handle_response($data, 'ipn');
header('Content-Type: application/json');
echo json_encode(['pp_ResponseCode' => $res['state'] === 'invalid' ? '999' : '000', 'pp_ResponseMessage' => $res['state'] === 'invalid' ? 'Invalid hash' : 'Received']);
