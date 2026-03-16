<?php
/**
 * KawaiiEmoji — Database Connection Helper
 *
 * Connects to MySQL using PDO.
 * Uses environment variables from Docker Compose,
 * with fallback values for local development.
 */

$db_host = getenv('DB_HOST') ?: 'kawaii-db';
$db_name = getenv('MYSQL_DATABASE') ?: 'kawaiiemoji_db';
$db_user = getenv('MYSQL_USER') ?: 'kawaii_app';
$db_pass = getenv('MYSQL_PASSWORD') ?: 'app_password_123';

try {
    $pdo = new PDO(
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
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    error_log('DB Connection Error: ' . $e->getMessage());
    exit;
}
