<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/mailer.php';

if (empty($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}
$db = db();
$action = (string) ($_POST['action'] ?? '');
$orderId = (int) ($_POST['order_id'] ?? 0);
if ($orderId < 1) { header('Location: orders.php'); exit; }

$find = $db->prepare('SELECT receipt_path, order_code, customer_name, customer_email, total, stock_deducted FROM orders WHERE id = ?');
$find->execute([$orderId]);
$order = $find->fetch();
if (!$order) { header('Location: orders.php'); exit; }

if ($action === 'approve') {
    if (deductOrderStock($db, $orderId, $order)) notifyOrderStatus($order, 'processing');
} elseif ($action === 'status') {
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['pending_payment', 'payment_review', 'processing', 'shipped', 'completed', 'delivered', 'cancelled'];
    if (in_array($status, $allowed, true)) {
        if (in_array($status, ['processing', 'shipped', 'completed', 'delivered'], true)) {
            if (deductOrderStock($db, $orderId, $order)) notifyOrderStatus($order, $status);
        } else {
            $paymentStatus = $status === 'cancelled' ? 'payment_declined' : ($status === 'payment_review' ? 'payment_pending_confirmation' : 'awaiting_payment');
            $update = $db->prepare('UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?');
            $update->execute([$status, $paymentStatus, $orderId]);
            notifyOrderStatus($order, $status);
        }
    }
} elseif ($action === 'delete') {
    $receipt = dirname(__DIR__) . '/' . ltrim((string) ($order['receipt_path'] ?? ''), '/');
    $db->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    if (is_file($receipt) && str_starts_with($receipt, dirname(__DIR__) . '/uploads/receipts/')) @unlink($receipt);
}
header('Location: orders.php');
exit;

function deductOrderStock(PDO $db, int $orderId, array $order): bool
{
    if ((int) $order['stock_deducted'] === 1) return true;
    try {
        $db->beginTransaction();
        $items = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([$orderId]);
        $updateStock = $db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
        foreach ($items->fetchAll() as $item) {
            $quantity = (int) $item['quantity'];
            $updateStock->execute([$quantity, (string) $item['product_id'], $quantity]);
            if ($updateStock->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }
        }
        $db->prepare("UPDATE orders SET payment_status = 'payment_approved', order_status = 'processing', stock_deducted = 1, payment_approved_at = NOW() WHERE id = ?")->execute([$orderId]);
        $db->commit();
        return true;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        return false;
    }
}
