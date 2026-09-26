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

$services = [
    'dreadlocks-maintenance' => ['name' => 'Dreadlocks & Maintenance', 'price' => 25000],
    'ghana-weaving-braids' => ['name' => 'Ghana Weaving & Braids', 'price' => 18000],
    'bobbing-haircuts' => ['name' => 'Bobbing & Haircuts', 'price' => 8000],
    'manicure-pedicure' => ['name' => 'Manicure & Pedicure', 'price' => 12000],
    'acrylics' => ['name' => 'Acrylics', 'price' => 20000],
    'lash-application-extensions' => ['name' => 'Lash Application & Extensions', 'price' => 15000],
    'wig-washing-maintenance' => ['name' => 'Wig Washing & Maintenance', 'price' => 12000],
    'wig-styling' => ['name' => 'Wig Styling', 'price' => 15000],
];

$pdo = db();
$pdo->beginTransaction();
$resolvedItems = [];
$orderTotal = 0.0;
foreach ($items as $item) {
    if (!is_array($item)) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Invalid item in your cart.'], 422);
    }
    $quantity = (int) ($item['quantity'] ?? 1);
    if ($quantity < 1 || $quantity > 20) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Each item quantity must be between 1 and 20.'], 422);
    }

    if (!empty($item['service_id'])) {
        $service = $services[(string) $item['service_id']] ?? null;
        if (!$service) {
            $pdo->rollBack();
            jsonResponse(['error' => 'One or more salon services are unavailable.'], 409);
        }
        $resolvedItems[] = [
            'id' => 'service-' . (string) $item['service_id'],
            'name' => $service['name'],
            'quantity' => $quantity,
            'price' => (float) $service['price'],
        ];
        $orderTotal += (float) $service['price'] * $quantity;
        continue;
    }

    $productId = (string) ($item['product_id'] ?? '');
    if ($productId === '') {
        $pdo->rollBack();
        jsonResponse(['error' => 'Each cart item must be a product or salon service.'], 422);
    }
    $product = $pdo->prepare('SELECT name, price, stock_quantity FROM products WHERE id = ? AND is_active = 1 FOR UPDATE');
    $product->execute([$productId]);
    $productRow = $product->fetch();
    if (!$productRow || (int) $productRow['stock_quantity'] < $quantity) {
        $pdo->rollBack();
        jsonResponse(['error' => 'One or more products are out of stock or have insufficient quantity.'], 409);
    }
    $unitPrice = (float) $productRow['price'];
    $resolvedItems[] = [
        'id' => $productId,
        'name' => (string) $productRow['name'],
        'quantity' => $quantity,
        'price' => $unitPrice,
    ];
    $orderTotal += $unitPrice * $quantity;
}

$orderCode = 'ELYS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$customerName = trim((string) ($customer['firstName'] ?? '') . ' ' . (string) ($customer['lastName'] ?? ''));
$customerEmail = trim((string) ($customer['email'] ?? ''));
$customerPhone = trim((string) ($customer['phone'] ?? ''));
$deliveryLocation = trim((string) ($customer['city'] ?? ''));
$deliveryAddress = trim((string) ($customer['address'] ?? ''));
$insert = $pdo->prepare('INSERT INTO orders (order_code, storefront, customer_name, customer_email, customer_phone, delivery_location, delivery_address, total, payment_status, order_status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$insert->execute([$orderCode, (string)($input['storefront'] ?? 'elys-beauty'), $customerName ?: 'Customer', $customerEmail, $customerPhone, $deliveryLocation, $deliveryAddress, $orderTotal, 'awaiting_payment', 'pending_payment', 'bank_transfer']);
$orderId = (int)$pdo->lastInsertId();
$itemInsert = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($resolvedItems as $item) {
    $itemInsert->execute([$orderId, $item['id'], $item['name'], $item['quantity'], $item['price'], $item['price'] * $item['quantity']]);
}
$pdo->commit();
$orderQuery = $pdo->prepare('SELECT order_code, customer_name, customer_email, total FROM orders WHERE id = ?');
$orderQuery->execute([$orderId]);
$createdOrder = $orderQuery->fetch();
if ($createdOrder) notifyOrderReceived($createdOrder);
jsonResponse(['success' => true, 'data' => ['order_id' => $orderId, 'order_code' => $orderCode, 'total' => $orderTotal]], 201);
