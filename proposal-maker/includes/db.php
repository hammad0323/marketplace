<?php
/**
 * DATABASE — small helper functions for MySQLi.
 *
 * All queries use "prepared statements": the ? marks in the SQL are filled
 * with your values safely, so nobody can inject SQL.
 *
 *   db_all('SELECT * FROM users')                     → list of rows
 *   db_row('SELECT * FROM users WHERE id = ?', [5])   → one row (or null)
 *   db_value('SELECT COUNT(*) FROM users')            → one value
 *   db_insert('users', ['name' => 'Ali'])              → new id
 *   db_update('users', ['name' => 'Ali'], 'id = ?', [5])
 */
function db()
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? (int) DB_PORT : 3306);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        exit('<h3 style="font-family:sans-serif">Database connection failed.</h3><p style="font-family:sans-serif">Check the credentials in config.php.</p>');
    }
    return $conn;
}

/** Run a query with bound params; returns mysqli_stmt. */
function db_query($sql, $params = [])
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

function db_all($sql, $params = [])
{
    $res = db_query($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_row($sql, $params = [])
{
    $res = db_query($sql, $params)->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_value($sql, $params = [])
{
    $row = db_row($sql, $params);
    return $row ? reset($row) : null;
}

function db_insert($table, $data)
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    db_query($sql, array_values($data));
    return (int) db()->insert_id;
}

function db_update($table, $data, $where, $whereParams = [])
{
    $set = [];
    foreach (array_keys($data) as $c) {
        $set[] = '`' . $c . '` = ?';
    }
    $stmt = db_query('UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . $where, array_merge(array_values($data), $whereParams));
    return $stmt->affected_rows;
}
