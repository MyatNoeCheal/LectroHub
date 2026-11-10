<?php
/**
 * Copy to connection.php (git-ignored) and fill in your real values.
 * Never commit connection.php.
 */
$db_host = 'localhost';
$db_name = 'lectrohub_db';
$db_user = 'root';
$db_pass = '';

try {
    $conn = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[DB] Connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection error.');
}
