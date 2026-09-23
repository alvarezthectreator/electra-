<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (empty($_POST['order_id']) || empty($_FILES['receipt'])) {
    jsonResponse(['error' => 'Order reference and payment receipt are required'], 422);
}

$file = $_FILES['receipt'];
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
if ($file['error'] !== UPLOAD_ERR_OK || !in_array((string)$file['type'], $allowed, true) || $file['size'] > 5 * 1024 * 1024) {
    jsonResponse(['error' => 'Receipt must be a JPG, PNG, or PDF under 5 MB'], 422);
}

$pdo = db();
$find = $pdo->prepare('SELECT id FROM orders WHERE order_code = ?');
$find->execute([(string)$_POST['order_id']]);
$order = $find->fetch();
if (!$order) {
    jsonResponse(['error' => 'Order not found'], 404);
}

$directory = dirname(__DIR__) . '/uploads/receipts';
if (!is_dir($directory)) {
    mkdir($directory, 0750, true);
}
$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$filename = $_POST['order_id'] . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
$destination = $directory . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonResponse(['error' => 'Unable to save receipt'], 500);
}

$update = $pdo->prepare("UPDATE orders SET payment_status = 'payment_pending_confirmation', order_status = 'payment_review', payment_method = ?, receipt_path = ?, payment_submitted_at = NOW() WHERE id = ?");
$update->execute(['bank_transfer', 'uploads/receipts/' . $filename, $order['id']]);
jsonResponse(['success' => true]);
