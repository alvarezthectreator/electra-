<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        throw new RuntimeException('Method not allowed.');
    }

    $storefront = trim((string) ($_GET['storefront'] ?? ''));
    $sql = 'SELECT id, name, short_description, description, price, image, tag, stock_quantity, low_stock_threshold
            FROM products
            WHERE is_active = 1';
    $parameters = [];
    if ($storefront !== '') {
        $sql .= ' AND storefront = ?';
        $parameters[] = $storefront;
    }
    $sql .= ' ORDER BY sort_order ASC, created_at DESC';

    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    $products = array_map(static function (array $product): array {
        $product['price'] = (float) $product['price'];
        $product['stock_quantity'] = (int) $product['stock_quantity'];
        $product['low_stock_threshold'] = (int) $product['low_stock_threshold'];
        $product['in_stock'] = $product['stock_quantity'] > 0;
        return $product;
    }, $statement->fetchAll());

    echo json_encode(['data' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['error' => 'Unable to load products.']);
}