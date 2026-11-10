<?php
/**
 * Procedural MySQLi helpers. EVERY query that takes user input goes through
 * a prepared statement via db_query()/db_all()/db_row()/db_val()/db_exec().
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    try {
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        mysqli_set_charset($conn, 'utf8mb4');
        mysqli_query($conn, "SET time_zone = '" . date('P') . "'");
        mysqli_query($conn, "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
    } catch (mysqli_sql_exception $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        http_response_code(503);
        $msg = APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : 'Please check the database settings in includes/config.php.';
        exit('<div style="font-family:system-ui;max-width:620px;margin:80px auto;padding:24px;border:1px solid #f5c2c7;background:#fff5f5;border-radius:12px">'
            . '<h2 style="margin-top:0;color:#842029">Database connection failed</h2><p>' . $msg . '</p></div>');
    }
    return $conn;
}

/** Build the bind type string for a list of params. */
function db_types(array $params): string
{
    $t = '';
    foreach ($params as $p) {
        if (is_int($p) || is_bool($p)) {
            $t .= 'i';
        } elseif (is_float($p)) {
            $t .= 'd';
        } else {
            $t .= 's';
        }
    }
    return $t;
}

/**
 * Run a prepared statement. Returns mysqli_stmt (already executed).
 */
function db_query(string $sql, array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare(db(), $sql);
    if ($params) {
        $params = array_values(array_map(function ($p) {
            return is_bool($p) ? (int) $p : $p;
        }, $params));
        mysqli_stmt_bind_param($stmt, db_types($params), ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db_query($sql, $params);
    $res = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db_query($sql, $params);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $stmt = db_query($sql, $params);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_row($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? $row[0] : null;
}

/** Column of values. */
function db_col(string $sql, array $params = []): array
{
    return array_map(function ($r) { return reset($r); }, db_all($sql, $params));
}

/** INSERT/UPDATE/DELETE - returns affected rows. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db_query($sql, $params);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return (int) $n;
}

/** INSERT - returns new id. */
function db_insert(string $sql, array $params = []): int
{
    $stmt = db_query($sql, $params);
    $id = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    return (int) $id;
}

/** Insert an associative array into a table (column names are whitelisted by caller). */
function db_insert_row(string $table, array $data): int
{
    $cols = array_keys($data);
    foreach ($cols as $c) {
        if (!preg_match('/^[a-z0-9_]+$/', $c)) {
            throw new InvalidArgumentException('Bad column');
        }
    }
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    return db_insert($sql, array_values($data));
}

function db_update_row(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    foreach (array_keys($data) as $c) {
        if (!preg_match('/^[a-z0-9_]+$/', $c)) {
            throw new InvalidArgumentException('Bad column');
        }
        $sets[] = '`' . $c . '` = ?';
    }
    $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
    return db_exec($sql, array_merge(array_values($data), $whereParams));
}

/** Placeholders for IN() lists: in_placeholders([1,2,3]) => "?,?,?" */
function in_placeholders(array $list): string
{
    return implode(',', array_fill(0, max(1, count($list)), '?'));
}

/**
 * Run $fn inside a transaction. Rolls back and re-throws on any exception.
 * Nested calls join the outer transaction.
 */
function db_tx(callable $fn)
{
    static $depth = 0;
    $conn = db();
    if ($depth === 0) {
        mysqli_begin_transaction($conn);
    }
    $depth++;
    try {
        $result = $fn();
        $depth--;
        if ($depth === 0) {
            mysqli_commit($conn);
        }
        return $result;
    } catch (Throwable $e) {
        $depth--;
        if ($depth === 0) {
            mysqli_rollback($conn);
        }
        throw $e;
    }
}

/** Escape a LIKE pattern fragment. */
function db_like(string $s): string
{
    return addcslashes($s, '%_\\');
}
