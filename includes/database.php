<?php
/**
 * Plain PDO connection helper — one function, no connection class.
 * Every query file below calls db() to get the shared connection.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/config.php';
        $dbConfig = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['name'],
            $dbConfig['charset']
        );

        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
