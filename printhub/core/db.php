<?php
/** MySQLi helpers. Every query with user data goes through a prepared statement. */

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $db->set_charset('utf8mb4');
        $db->query("SET time_zone = '" . (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P') . "'");
    }
    return $db;
}

function db_query(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...array_values($params));
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $res = db_query($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_row(string $sql, array $params = []): ?array
{
    $res = db_query($sql, $params)->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $res = db_query($sql, $params)->get_result();
    $row = $res ? $res->fetch_row() : null;
    return $row ? $row[0] : null;
}

/** Runs a write; returns affected rows. */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->affected_rows;
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_query($sql, array_values($data));
    return (int) db()->insert_id;
}

function db_update(string $table, array $data, string $where, array $params = []): int
{
    $set = implode(', ', array_map(fn($c) => '`' . $c . '` = ?', array_keys($data)));
    return db_exec('UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $where, array_merge(array_values($data), $params));
}

/** Placeholders for an IN (...) list. */
function db_in(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

function db_tx(callable $fn)
{
    db()->begin_transaction();
    try {
        $r = $fn();
        db()->commit();
        return $r;
    } catch (Throwable $e) {
        db()->rollback();
        throw $e;
    }
}
