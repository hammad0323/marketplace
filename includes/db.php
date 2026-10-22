<?php
/**
 * MySQLi database layer. Every query goes through a prepared statement.
 *
 *   db_all('SELECT * FROM products WHERE status = ?', ['published']);
 *   db_row('SELECT * FROM products WHERE id = ?', [$id]);
 *   db_val('SELECT COUNT(*) FROM orders');
 *   db_exec('UPDATE products SET stock_qty = ? WHERE id = ?', [$qty, $id]);
 *   db_insert('pages', ['title' => 'About', 'slug' => 'about']);
 *   db_update('pages', ['title' => 'About us'], 'id = ?', [$id]);
 */

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? (int) DB_PORT : 3306);
            $conn->set_charset('utf8mb4');
            $offset = (new DateTime())->format('P');
            $conn->query("SET time_zone = '" . $conn->real_escape_string($offset) . "'");
        } catch (mysqli_sql_exception $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            exit('<div style="font-family:sans-serif;max-width:600px;margin:80px auto;line-height:1.6">'
                . '<h2>We will be right back</h2><p>The store is temporarily unavailable. Please try again shortly.</p>'
                . (APP_ENV !== 'production' ? '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>' : '')
                . '</div>');
        }
    }
    return $conn;
}

/**
 * Prepares and executes a statement. Parameter types are inferred:
 * int → i, float → d, everything else (including null) → s.
 */
function db_stmt(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        $values = [];
        foreach (array_values($params) as $p) {
            if (is_bool($p)) {
                $p = (int) $p;
            }
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            $values[] = $p;
        }
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db_stmt($sql, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db_stmt($sql, $params);
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $stmt = db_stmt($sql, $params);
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : null;
}

/** Returns a single column as a flat array. */
function db_col(string $sql, array $params = []): array
{
    return array_map(fn($r) => reset($r), db_all($sql, $params));
}

/** Executes a write statement and returns the number of affected rows. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db_stmt($sql, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

/** Column names must come from code, never from user input. */
function db_ident(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid identifier');
    }
    return '`' . $name . '`';
}

function db_insert(string $table, array $data): int
{
    $cols = implode(', ', array_map('db_ident', array_keys($data)));
    $marks = implode(', ', array_fill(0, count($data), '?'));
    db_exec('INSERT INTO ' . db_ident($table) . " ($cols) VALUES ($marks)", array_values($data));
    return (int) db()->insert_id;
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(', ', array_map(fn($c) => db_ident($c) . ' = ?', array_keys($data)));
    return db_exec('UPDATE ' . db_ident($table) . " SET $set WHERE $where", array_merge(array_values($data), $whereParams));
}

/** Builds "?, ?, ?" for IN () clauses. */
function db_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

function db_begin(): void
{
    db()->begin_transaction();
}

function db_commit(): void
{
    db()->commit();
}

function db_rollback(): void
{
    try {
        db()->rollback();
    } catch (Throwable $e) {
        // connection already gone; nothing to roll back
    }
}

/** Runs $fn inside a transaction, rolling back on any exception. */
function db_transaction(callable $fn)
{
    db_begin();
    try {
        $result = $fn();
        db_commit();
        return $result;
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}
