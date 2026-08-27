<?php
/**
 * Database Layer - MySQLi with prepared statements only.
 */
require_once __DIR__ . '/config.php';

function db_connect(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        error_log('DB Connection failed: ' . $conn->connect_error);
        http_response_code(500);
        include __DIR__ . '/../500.php';
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Runs a prepared statement.
 * $types example: 'sis' (string,int,string) ; pass [] and '' for no params.
 * Returns mysqli_stmt (caller should close it) on success, false on failure.
 */
function db_run(string $sql, string $types = '', array $params = [])
{
    $conn = db_connect();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('DB Prepare failed: ' . $conn->error . ' SQL: ' . $sql);
        return false;
    }
    if ($types !== '' && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        error_log('DB Execute failed: ' . $stmt->error . ' SQL: ' . $sql);
        $stmt->close();
        return false;
    }
    return $stmt;
}

function db_fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($sql, $types, $params);
    if (!$stmt) {
        return [];
    }
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_fetch_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function db_fetch_value(string $sql, string $types = '', array $params = [])
{
    $row = db_fetch_one($sql, $types, $params);
    if (!$row) {
        return null;
    }
    return array_values($row)[0];
}

/**
 * INSERT/UPDATE/DELETE. Returns insert_id (int) for INSERT, affected_rows for others, or false on failure.
 */
function db_execute(string $sql, string $types = '', array $params = [])
{
    $conn = db_connect();
    $stmt = db_run($sql, $types, $params);
    if (!$stmt) {
        return false;
    }
    $isInsert = stripos(ltrim($sql), 'INSERT') === 0;
    $result = $isInsert ? $conn->insert_id : $stmt->affected_rows;
    $stmt->close();
    return $result;
}

function db_escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}

function db_count(string $table, string $where = '1', string $types = '', array $params = []): int
{
    $sql = "SELECT COUNT(*) AS c FROM `$table` WHERE $where";
    $val = db_fetch_value($sql, $types, $params);
    return (int)($val ?? 0);
}

/**
 * Type-inferring convenience wrappers (db_exec/db_all/db_one/db_val) - infer 'i'/'d'/'s' from PHP value types
 * so callers don't have to hand-build bind_param type strings (a common source of mismatch bugs).
 * NULL values are always bound as 's' (mysqli accepts NULL regardless of declared bind type).
 */
function db_infer_types(array $params): string
{
    $types = '';
    foreach ($params as $p) {
        if ($p === null) $types .= 's';
        elseif (is_int($p)) $types .= 'i';
        elseif (is_float($p)) $types .= 'd';
        else $types .= 's';
    }
    return $types;
}

function db_exec(string $sql, array $params = [])
{
    return db_execute($sql, db_infer_types($params), $params);
}

function db_all(string $sql, array $params = []): array
{
    return db_fetch_all($sql, db_infer_types($params), $params);
}

function db_one(string $sql, array $params = []): ?array
{
    return db_fetch_one($sql, db_infer_types($params), $params);
}

function db_val(string $sql, array $params = [])
{
    return db_fetch_value($sql, db_infer_types($params), $params);
}
