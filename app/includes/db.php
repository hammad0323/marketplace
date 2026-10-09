<?php
/**
 * MySQLi database layer. Every query goes through a prepared statement.
 *
 *   db_all('SELECT * FROM products WHERE status = ?', ['published']);
 *   db_one('SELECT * FROM products WHERE id = ?', [$id]);
 *   db_val('SELECT COUNT(*) FROM orders');
 *   db_exec('UPDATE products SET name = ? WHERE id = ?', [$name, $id]);  // affected rows
 *   db_insert('products', ['name' => 'x', ...]);                          // insert id
 *   db_tx(function () { ... });                                           // transaction
 */

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) (defined('DB_PORT') ? DB_PORT : 3306));
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
        exit('The store is temporarily unavailable. Please try again shortly.');
    }
    return $conn;
}

/** Align MySQL's session time zone with PHP so NOW() and date() agree. */
function db_sync_timezone(): void
{
    $offset = (new DateTime('now'))->format('P');
    db()->query("SET time_zone = '" . db()->real_escape_string($offset) . "'");
}

/** Build the bind_param type string from PHP values. */
function db_types(array $params): string
{
    $types = '';
    foreach ($params as $p) {
        if (is_int($p) || is_bool($p)) {
            $types .= 'i';
        } elseif (is_float($p)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

function db_query(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        foreach ($params as $i => $p) {
            if (is_bool($p)) {
                $params[$i] = (int) $p;
            }
        }
        $stmt->bind_param(db_types($params), ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db_query($sql, $params);
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db_query($sql, $params);
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $stmt = db_query($sql, $params);
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : null;
}

/** Column of first values. */
function db_col(string $sql, array $params = []): array
{
    return array_map(fn($r) => reset($r), db_all($sql, $params));
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db_query($sql, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_ident(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid identifier');
    }
    return '`' . $name . '`';
}

function db_insert(string $table, array $data): int
{
    $cols = implode(',', array_map('db_ident', array_keys($data)));
    $marks = implode(',', array_fill(0, count($data), '?'));
    $stmt = db_query('INSERT INTO ' . db_ident($table) . " ($cols) VALUES ($marks)", array_values($data));
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = implode(',', array_map(fn($c) => db_ident($c) . ' = ?', array_keys($data)));
    return db_exec('UPDATE ' . db_ident($table) . " SET $sets WHERE $where", array_merge(array_values($data), $whereParams));
}

/** Placeholder list "?,?,?" for IN() clauses. */
function db_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

/**
 * Run $fn inside a transaction. Rolls back and rethrows on any exception.
 * Nested calls join the outer transaction.
 */
function db_tx(callable $fn)
{
    static $depth = 0;
    if ($depth > 0) {
        return $fn();
    }
    $depth++;
    db()->begin_transaction();
    try {
        $result = $fn();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        db()->rollback();
        throw $e;
    } finally {
        $depth--;
    }
}
