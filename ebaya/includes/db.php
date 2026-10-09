<?php
/**
 * MySQLi helpers. Every query with user data goes through a prepared
 * statement: db_all($sql, [$a, $b]) binds parameters automatically.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (mysqli_sql_exception $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        http_response_code(503);
        exit('The store is temporarily unavailable. Please try again shortly.');
    }
    return $conn;
}

/** Infer bind types: i for ints/bools, d for floats, s otherwise. */
function db_types(array $params): string
{
    $t = '';
    foreach ($params as $p) {
        if (is_int($p) || is_bool($p)) $t .= 'i';
        elseif (is_float($p)) $t .= 'd';
        else $t .= 's';
    }
    return $t;
}

function db_run(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        foreach ($params as $k => $v) {
            if (is_bool($v)) $params[$k] = (int)$v;
        }
        $stmt->bind_param(db_types($params), ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db_run($sql, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_one(string $sql, array $params = []): ?array
{
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

function db_val(string $sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

function db_col(string $sql, array $params = []): array
{
    return array_map(fn($r) => reset($r), db_all($sql, $params));
}

/** INSERT/UPDATE/DELETE. Returns affected rows. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_insert(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

/** Build "?, ?, ?" for IN() lists. */
function db_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

/** Run $fn inside a transaction; rolls back and rethrows on any exception. */
function db_tx(callable $fn)
{
    $c = db();
    $c->begin_transaction();
    try {
        $out = $fn();
        $c->commit();
        return $out;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}
