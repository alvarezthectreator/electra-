<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'elys_beauty';
const DB_USER = 'root';
const DB_PASS = '';
const ADMIN_TOKEN = '';

function envValue(string $name, string $fallback): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $fallback : $value;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envValue('ELYS_DB_HOST', DB_HOST);
    $name = envValue('ELYS_DB_NAME', DB_NAME);
    $user = envValue('ELYS_DB_USER', DB_USER);
    $pass = envValue('ELYS_DB_PASS', DB_PASS);
    $server = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ensureSchema($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(40) NOT NULL UNIQUE,
        storefront VARCHAR(60) NOT NULL,
        customer_json JSON NOT NULL,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
        payment_method VARCHAR(40) NULL,
        receipt_path VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        product_id VARCHAR(120) NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_settings (
        id TINYINT UNSIGNED PRIMARY KEY,
        bank_name VARCHAR(120) NOT NULL,
        account_name VARCHAR(160) NOT NULL,
        account_number VARCHAR(80) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function requestJson(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function requireAdmin(): void
{
    $configured = envValue('ELYS_ADMIN_TOKEN', ADMIN_TOKEN);
    if ($configured !== '' && !hash_equals($configured, (string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''))) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}
