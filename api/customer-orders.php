<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$email = strtolower(trim((string) ($_GET['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'A valid checkout email is required'], 422);
}

$pdo = db();
$ordersQuery = $pdo->prepare('SELECT id, order_code, customer_name, total, payment_status, order_status, payment_method, receipt_path, created_at, payment_submitted_at, payment_approved_at FROM orders WHERE LOWER(customer_email) = ? ORDER BY created_at DESC');
$ordersQuery->execute([$email]);
$orders = $ordersQuery->fetchAll();
$itemQuery = $pdo->prepare('SELECT product_name, quantity, unit_price, line_total FROM order_items WHERE order_id = ? ORDER BY id ASC');
foreach ($orders as &$order) {
    $itemQuery->execute([(int) $order['id']]);
    $order['items'] = $itemQuery->fetchAll();
    $order['total'] = (float) $order['total'];
    unset($order['id'], $order['receipt_path']);
}
unset($order);
jsonResponse(['success' => true, 'data' => $orders]);
