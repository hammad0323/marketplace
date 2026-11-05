<?php
/**
 * MySQLi connection (single shared instance per request).
 * - Errors are thrown as mysqli_sql_exception and handled by the global handler.
 * - Integers/floats come back as native PHP types.
 * - Session runs in UTC with utf8mb4.
 */
function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) return $conn;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $conn->real_connect(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        (int)DB_PORT,
        DB_SOCKET !== '' ? DB_SOCKET : null
    );
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+00:00'");
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $conn;
}
