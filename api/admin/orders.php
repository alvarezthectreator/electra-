<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $orders = db()->query('SELECT id, order_code, storefront, customer_json, total, status, payment_method, receipt_path, created_at FROM orders ORDER BY created_at DESC')->fetchAll();
    foreach ($orders as &$order) {
        $order['customer'] = json_decode((string)$order['customer_json'], true);
        unset($order['customer_json']);
    }
    jsonResponse(['success' => true, 'data' => $orders]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = requestJson();
    $status = (string)($input['status'] ?? '');
    $allowed = ['pending_payment', 'payment_review', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($status, $allowed, true) || empty($input['order_code'])) {
        jsonResponse(['error' => 'Valid order_code and status are required'], 422);
    }
    $update = db()->prepare('UPDATE orders SET status = ? WHERE order_code = ?');
    $update->execute([$status, $input['order_code']]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
