<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '8889';
const DB_NAME = 'angel';
const DB_USER = 'root';
const DB_PASS = 'root';
const ADMIN_TOKEN = '';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php-error.log');
if (!is_dir(dirname(__DIR__) . '/storage/logs')) {
    @mkdir(dirname(__DIR__) . '/storage/logs', 0750, true);
}

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
    $port = envValue('ELYS_DB_PORT', DB_PORT);
    $name = envValue('ELYS_DB_NAME', DB_NAME);
    $user = envValue('ELYS_DB_USER', DB_USER);
    $pass = envValue('ELYS_DB_PASS', DB_PASS);
    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ensureSchema($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id VARCHAR(100) PRIMARY KEY,
        storefront VARCHAR(40) NOT NULL DEFAULT 'elys-beauty',
        name VARCHAR(180) NOT NULL,
        short_description TEXT NOT NULL,
        description TEXT NOT NULL,
        benefits JSON NOT NULL,
        ingredients TEXT NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        old_price DECIMAL(12,2) NULL,
        category VARCHAR(80) NULL,
        stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
        low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
        image VARCHAR(255) NOT NULL,
        tag VARCHAR(80) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(40) NOT NULL UNIQUE,
        storefront VARCHAR(60) NOT NULL,
        customer_name VARCHAR(180) NOT NULL,
        customer_email VARCHAR(190) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        delivery_location TEXT NOT NULL,
        delivery_address TEXT NOT NULL,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_status VARCHAR(40) NOT NULL DEFAULT 'awaiting_payment',
        order_status VARCHAR(30) NOT NULL DEFAULT 'pending_payment',
        payment_method VARCHAR(40) NULL,
        stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
        receipt_path VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        product_id VARCHAR(120) NOT NULL,
        product_name VARCHAR(180) NOT NULL,
        quantity INT UNSIGNED NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_settings (
        id TINYINT UNSIGNED PRIMARY KEY,
        bank_name VARCHAR(120) NOT NULL,
        account_name VARCHAR(160) NOT NULL,
        account_number VARCHAR(80) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    ensureColumn($pdo, 'products', 'category', 'VARCHAR(80) NULL AFTER old_price');
    ensureColumn($pdo, 'products', 'stock_quantity', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER category');
    ensureColumn($pdo, 'products', 'low_stock_threshold', 'INT UNSIGNED NOT NULL DEFAULT 5 AFTER stock_quantity');
    ensureColumn($pdo, 'orders', 'order_status', "VARCHAR(30) NOT NULL DEFAULT 'pending_payment' AFTER payment_status");
    ensureColumn($pdo, 'orders', 'stock_deducted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_method');
    ensureColumn($pdo, 'order_items', 'product_name', "VARCHAR(180) NOT NULL DEFAULT '' AFTER product_id");
    ensureColumn($pdo, 'order_items', 'unit_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity');
    ensureColumn($pdo, 'order_items', 'line_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price');
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $statement->execute([$table, $column]);
    if ((int) $statement->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
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
