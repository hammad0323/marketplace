<?php
/**
 * Consistent JSON responses:
 *   {"status": bool, "message": string, "data": mixed, "code"?: string, "errors"?: {}, "meta"?: {}}
 */

function json_out(bool $status, string $message, $data = null, int $http = 200, array $extra = []): never
{
    if (db_in_transaction()) {
        db()->rollback();
        $GLOBALS['__in_txn'] = false;
    }
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $body = ['status' => $status, 'message' => $message, 'data' => $data] + $extra;
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ok($data = null, string $message = 'OK', int $http = 200, array $extra = []): never
{
    json_out(true, $message, $data, $http, $extra);
}

function fail(string $message, int $http = 400, string $code = 'ERROR', array $errors = []): never
{
    $extra = ['code' => $code];
    if ($errors) $extra['errors'] = $errors;
    json_out(false, $message, null, $http, $extra);
}

function not_found(string $what = 'Resource'): never
{
    fail("{$what} not found", 404, 'NOT_FOUND');
}

function paged(array $rows, int $total, int $page, int $perPage, string $message = 'OK'): never
{
    ok($rows, $message, 200, ['meta' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total,
        'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
    ]]);
}
