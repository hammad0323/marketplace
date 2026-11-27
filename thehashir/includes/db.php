<?php
/**
 * Database helpers (MySQLi, prepared statements only).
 * Public pages only read. Writes happen only in the back office, through db_exec().
 */

function db()
{
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conn->set_charset('utf8mb4');
        // Existing rows use 0000-00-00 for "no date": allow it for this session regardless of the server's sql_mode
        $conn->query("SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '')");
    } catch (mysqli_sql_exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>Temporarily unavailable</title>'
            . '<p style="font-family:sans-serif;padding:2rem">The Hashir is temporarily unavailable. Please try again in a few minutes.</p>';
        exit;
    }
    return $conn;
}

/**
 * Run a prepared query and return all rows as associative arrays.
 * $types follows mysqli bind_param syntax ("i", "s", "si" ...).
 */
function db_all($sql, $types = '', $params = array())
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && count($params) > 0) {
        $refs = array();
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        call_user_func_array(array($stmt, 'bind_param'), $refs);
    }
    $stmt->execute();
    $rows = stmt_fetch_all($stmt);
    $stmt->close();
    return $rows;
}

function db_one($sql, $types = '', $params = array())
{
    $rows = db_all($sql, $types, $params);
    return count($rows) ? $rows[0] : null;
}

function db_val($sql, $types = '', $params = array())
{
    $row = db_one($sql, $types, $params);
    if (!$row) {
        return null;
    }
    $vals = array_values($row);
    return $vals[0];
}

/** Fetch rows from a statement; works with or without the mysqlnd driver. */
function stmt_fetch_all($stmt)
{
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res === false) {
            return array();
        }
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
        return $rows;
    }
    $meta = $stmt->result_metadata();
    if (!$meta) {
        return array();
    }
    $row = array();
    $bind = array();
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $bind[] = &$row[$field->name];
    }
    call_user_func_array(array($stmt, 'bind_result'), $bind);
    $rows = array();
    while ($stmt->fetch()) {
        $copy = array();
        foreach ($row as $k => $v) {
            $copy[$k] = $v;
        }
        $rows[] = $copy;
    }
    return $rows;
}

/** Build "?,?,?" placeholders and the matching type string for an IN() list of integers. */
function db_in_ints($ids)
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        $ids = array(0);
    }
    return array(implode(',', array_fill(0, count($ids), '?')), str_repeat('i', count($ids)), $ids);
}

/**
 * Run an INSERT / UPDATE / DELETE with a prepared statement. Returns affected rows.
 * Used only by the back office (Super Admin & portal). The public pages never write.
 */
function db_exec($sql, $types = '', $params = array())
{
    $stmt = db()->prepare($sql);
    if ($types !== '' && count($params) > 0) {
        $refs = array();
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        call_user_func_array(array($stmt, 'bind_param'), $refs);
    }
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function db_last_id()
{
    return (int)db()->insert_id;
}

function db_begin()
{
    db()->begin_transaction();
}

function db_commit()
{
    db()->commit();
}

function db_rollback()
{
    db()->rollback();
}
