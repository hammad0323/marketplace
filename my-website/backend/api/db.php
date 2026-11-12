<?php
// =========================================================
// Shared helpers used by every API file:
//   getDB()          → connects to MySQL (PDO)
//   sendJson()       → sends a JSON answer to React and stops
//   requireMethod()  → only allow GET or POST
// =========================================================

require_once __DIR__ . '/config.php';

// Every answer from this API is JSON
header('Content-Type: application/json; charset=utf-8');

function sendJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireMethod($method)
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendJson(['error' => "Only $method requests are allowed."], 405);
    }
}

function getDB()
{
    static $pdo = null;          // connect only once per request
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as ['name' => ...]
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        $message = DEBUG
            ? 'Database connection failed: ' . $e->getMessage()
            : 'Database connection failed.';
        sendJson(['error' => $message], 500);
    }
}
