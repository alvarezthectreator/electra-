<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = requestJson();
$customer = $input['customer'] ?? [];
$items = $input['items'] ?? [];
if (!is_array($customer) || !is_array($items) || !$items) {
    jsonResponse(['error' => 'Customer details and cart items are required'], 422);
}

$pdo = db();
$orderCode = 'ELYS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$insert = $pdo->prepare('INSERT INTO orders (order_code, storefront, customer_json, total) VALUES (?, ?, ?, ?)');
$insert->execute([$orderCode, (string)($input['storefront'] ?? 'elys-beauty'), json_encode($customer), max(0, (float)($input['total'] ?? 0))]);
$orderId = (int)$pdo->lastInsertId();
$itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)');
foreach ($items as $item) {
    $itemInsert->execute([$orderId, (string)($item['product_id'] ?? ''), max(1, (int)($item['quantity'] ?? 1))]);
}
jsonResponse(['success' => true, 'data' => ['order_id' => $orderId, 'order_code' => $orderCode]], 201);
