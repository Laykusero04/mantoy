<?php
$host = 'localhost';
$dbname = 'bdpt_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log(
        '[' . date('Y-m-d H:i:s') . '] [ERROR] Database connection failed: ' . $e->getMessage() . PHP_EOL,
        3,
        $logDir . '/app.log'
    );
    http_response_code(503);
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Service Unavailable</title>'
      . '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f8f9fa}'
      . '.msg{text-align:center;color:#495057}h1{font-size:1.5rem}</style></head>'
      . '<body><div class="msg"><h1>Service Unavailable</h1>'
      . '<p>Unable to connect to the database. Please try again later.</p></div></body></html>');
}
