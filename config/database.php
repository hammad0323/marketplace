<?php
/**
 * The ONE place the database connection is created. Every page reaches
 * this indirectly through config/config.php and calls mp_db() instead
 * of ever opening its own connection.
 *
 * ============================================================
 *  TO DEPLOY: edit the four DB_* constants below to match your
 *  database, then import database.sql.
 * ============================================================
 *
 * Every query in this project goes through MySQLi prepared statements
 * (procedural mysqli_* API, no OOP) via the mp_db_* helpers below —
 * never string-concatenated SQL.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

/**
 * Returns the shared MySQLi connection, created on first use.
 */
function mp_db()
{
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if (!$conn) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #f3c2c5;background:#fcebec;border-radius:8px;color:#8a161d;">'
                . '<h2 style="margin-top:0;">Database connection failed</h2>'
                . '<p>Could not connect to MySQL using the credentials in <code>config/database.php</code>. '
                . 'Double check DB_HOST/DB_NAME/DB_USER/DB_PASS and that <code>database.sql</code> has been imported.</p>'
                . '<p style="color:#a34;font-size:13px;">' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8') . '</p></div>');
        }

        mysqli_set_charset($conn, 'utf8mb4');
    }

    return $conn;
}

/**
 * Builds a mysqli_stmt bind_param() type string ('i'/'d'/'s') from a
 * list of PHP values, so every query helper below can be called with
 * plain PHP values instead of hand-written type strings.
 */
function mp_db_types(array $values): string
{
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

/**
 * Runs a prepared statement and returns the mysqli_result (or true/false
 * for non-SELECT queries). Every other mp_db_* helper is built on this
 * one. $params values are auto-typed via mp_db_types() unless $types is
 * given explicitly.
 */
function mp_db_run(string $sql, array $params = [], ?string $types = null)
{
    $stmt = mysqli_prepare(mp_db(), $sql);
    if ($stmt === false) {
        throw new RuntimeException('Query prepare failed: ' . mysqli_error(mp_db()) . ' — ' . $sql);
    }

    if ($params) {
        mysqli_stmt_bind_param($stmt, $types ?? mp_db_types($params), ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $insertId = (int) mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);

    return [$result, $insertId];
}

function mp_db_execute(string $sql, array $params = [], ?string $types = null): void
{
    mp_db_run($sql, $params, $types);
}

function mp_db_fetch_one(string $sql, array $params = [], ?string $types = null): ?array
{
    [$result] = mp_db_run($sql, $params, $types);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return $row ?: null;
}

function mp_db_fetch_all(string $sql, array $params = [], ?string $types = null): array
{
    [$result] = mp_db_run($sql, $params, $types);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

/** First column of the first row — the MySQLi equivalent of PDO's fetchColumn(). */
function mp_db_fetch_value(string $sql, array $params = [], ?string $types = null)
{
    $row = mp_db_fetch_one($sql, $params, $types);
    return $row ? array_values($row)[0] : null;
}

/** First column of every row, as a flat list — for ID-list style queries. */
function mp_db_fetch_column(string $sql, array $params = [], ?string $types = null): array
{
    return array_map(static fn (array $row) => reset($row), mp_db_fetch_all($sql, $params, $types));
}

function mp_db_insert_id(string $sql, array $params = [], ?string $types = null): int
{
    [, $insertId] = mp_db_run($sql, $params, $types);
    return $insertId;
}

/**
 * Tables that are never scoped to a tenant — tenants itself (nothing
 * to scope it to) and platform_admins (platform-operator staff belong
 * to no tenant). Every other table gets tenant_id auto-injected below.
 */
const MP_TENANT_EXEMPT_TABLES = ['tenants', 'platform_admins'];

/**
 * Generic INSERT built from an associative array of column => value.
 * Auto-injects the current tenant_id (see mp_tenant_id(),
 * includes/middlewares/tenant.php) into every insert unless the
 * caller already supplied one explicitly or the table is exempt —
 * this is what lets every mp_insert_*() function across the project
 * stay unaware of tenancy entirely, the same way they're already
 * unaware of which MySQLi connection they're using.
 */
function mp_db_insert(string $table, array $data): int
{
    if (!array_key_exists('tenant_id', $data) && !in_array($table, MP_TENANT_EXEMPT_TABLES, true)) {
        $data['tenant_id'] = mp_tenant_id();
    }

    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";

    return mp_db_insert_id($sql, array_values($data));
}

/** Generic UPDATE built from an associative array of column => value plus a raw WHERE clause. */
function mp_db_update(string $table, array $data, string $whereSql, array $whereParams = []): void
{
    $assignments = implode(', ', array_map(static fn ($column) => "{$column} = ?", array_keys($data)));
    $sql = "UPDATE {$table} SET {$assignments} WHERE {$whereSql}";

    mp_db_execute($sql, array_merge(array_values($data), $whereParams));
}

/**
 * Real multi-statement transactions (procedural mysqli_* API) for
 * operations that must all-or-nothing succeed — e.g. mp_create_order()
 * writing an order, its line items, and the first status-history row
 * together. mysqli_report(MYSQLI_REPORT_OFF) (set in mp_db()) means
 * failures surface as false/empty results rather than exceptions, so
 * callers check each step and roll back explicitly.
 */
function mp_db_begin_transaction(): void
{
    mysqli_begin_transaction(mp_db());
}

function mp_db_commit(): void
{
    mysqli_commit(mp_db());
}

function mp_db_rollback(): void
{
    mysqli_rollback(mp_db());
}
