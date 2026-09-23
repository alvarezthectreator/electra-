<?php

declare(strict_types=1);
require __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

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
$pdo->beginTransaction();
$orderCode = 'ELYS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$customerName = trim((string) ($customer['firstName'] ?? '') . ' ' . (string) ($customer['lastName'] ?? ''));
$customerEmail = trim((string) ($customer['email'] ?? ''));
$customerPhone = trim((string) ($customer['phone'] ?? ''));
$deliveryLocation = trim((string) ($customer['city'] ?? ''));
$deliveryAddress = trim((string) ($customer['address'] ?? ''));
$insert = $pdo->prepare('INSERT INTO orders (order_code, storefront, customer_name, customer_email, customer_phone, delivery_location, delivery_address, total, payment_status, order_status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([$orderCode, (string)($input['storefront'] ?? 'elys-beauty'), $customerName ?: 'Customer', $customerEmail, $customerPhone, $deliveryLocation, $deliveryAddress, max(0, (float)($input['total'] ?? 0)), 'awaiting_payment', 'pending_payment', 'bank_transfer']);
$orderId = (int)$pdo->lastInsertId();
$itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($items as $item) {
    $productId = (string) ($item['product_id'] ?? '');
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $product = $pdo->prepare('SELECT name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
    $product->execute([$productId]);
    $productRow = $product->fetch();
    if (!$productRow || (int) $productRow['stock_quantity'] < $quantity) {
        $pdo->rollBack();
        jsonResponse(['error' => 'One or more products are out of stock or have insufficient quantity.'], 409);
    }
    $unitPrice = (float) $productRow['price'];
    $itemInsert->execute([$orderId, $productId, (string) $productRow['name'], $quantity, $unitPrice, $unitPrice * $quantity]);
}
$pdo->commit();
$orderQuery = $pdo->prepare('SELECT order_code, customer_name, customer_email, total FROM orders WHERE id = ?');
$orderQuery->execute([$orderId]);
$createdOrder = $orderQuery->fetch();
if ($createdOrder) notifyOrderReceived($createdOrder);
jsonResponse(['success' => true, 'data' => ['order_id' => $orderId, 'order_code' => $orderCode]], 201);
