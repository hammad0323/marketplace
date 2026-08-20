<?php
/**
 * db.php — single MySQLi connection, shared everywhere via tp_db().
 * No PDO, no ORM — plain MySQLi with prepared statements throughout.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

function tp_db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (TOOLS_ENV === 'development') {
            exit('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        exit('The site is temporarily unavailable. Please try again shortly.');
    }

    return $conn;
}

/**
 * Run a prepared statement and return all rows as an assoc array.
 * $types is the mysqli bind_param type string, e.g. "si".
 */
function tp_query(string $sql, string $types = '', array $params = []): array
{
    $db = tp_db();
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . $db->error . ' SQL: ' . $sql);
        return [];
    }
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** Same as tp_query() but returns the first row (or null). */
function tp_query_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = tp_query($sql, $types, $params);
    return $rows[0] ?? null;
}

/**
 * INSERT/UPDATE/DELETE helper. Returns the affected/insert id info.
 * @return array{success:bool, insert_id:int, affected_rows:int, error:?string}
 */
function tp_execute(string $sql, string $types = '', array $params = []): array
{
    $db = tp_db();
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'insert_id' => 0, 'affected_rows' => 0, 'error' => $db->error];
    }
    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $ok = $stmt->execute();
    $result = [
        'success' => $ok,
        'insert_id' => $stmt->insert_id,
        'affected_rows' => $stmt->affected_rows,
        'error' => $ok ? null : $stmt->error,
    ];
    $stmt->close();
    return $result;
}
