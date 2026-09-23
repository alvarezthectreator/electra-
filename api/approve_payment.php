<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$orderCode = trim((string) ($_GET['order'] ?? $_POST['order'] ?? ''));
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = $orderCode !== '' && hash_equals(paymentApprovalToken($orderCode), $token);

if (!$validToken) {
    http_response_code(403);
    echo approvalPage('Invalid approval link', 'This payment approval link is invalid or expired.');
    exit;
}

$pdo = db();
$find = $pdo->prepare('SELECT id, order_code, customer_name, customer_email, total, payment_status, order_status, stock_deducted FROM orders WHERE order_code = ?');
$find->execute([$orderCode]);
$order = $find->fetch();
if (!$order) {
    http_response_code(404);
    echo approvalPage('Order not found', 'The order connected to this approval link could not be found.');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int) $order['stock_deducted'] === 1 || $order['payment_status'] === 'payment_approved') {
        echo approvalPage('Already approved', 'This order has already been approved.');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([(int) $order['id']]);
        $updateStock = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
        foreach ($items->fetchAll() as $item) {
            $quantity = (int) $item['quantity'];
            $updateStock->execute([$quantity, (string) $item['product_id'], $quantity]);
            if ($updateStock->rowCount() !== 1) {
                throw new RuntimeException('Insufficient stock.');
            }
        }
        $update = $pdo->prepare("UPDATE orders SET payment_status = 'payment_approved', order_status = 'processing', stock_deducted = 1, payment_approved_at = NOW() WHERE id = ?");
        $update->execute([(int) $order['id']]);
        $pdo->commit();
        notifyOrderStatus($order, 'processing');
        echo approvalPage('Payment approved', 'Order ' . $orderCode . ' is approved and processing has started.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo approvalPage('Approval failed', 'The order could not be approved. Check stock and try again from the admin panel.');
    }
    exit;
}

$formAction = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
echo approvalPage('Approve order?', '<p>Order <strong>' . htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') . '</strong></p><p>Customer: ' . htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8') . '<br>Total: <strong>₦' . number_format((float) $order['total'], 2) . '</strong></p><p>Confirm that the money was received before approving this order.</p><form method="post" action="' . $formAction . '"><input type="hidden" name="order" value="' . htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '"><button type="submit">Approve order</button></form>');

function approvalPage(string $title, string $message): string
{
    return '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>body{margin:0;padding:40px 18px;background:#f6f1e8;color:#151210;font:16px Arial,sans-serif}.card{max-width:520px;margin:auto;padding:30px;background:#fff;border-top:4px solid #c9a35f;box-shadow:0 12px 30px #15121018}h1{margin:0 0 18px;font-size:25px}p{line-height:1.6;color:#6d665d}button{border:0;padding:14px 18px;background:#c9a35f;color:#151210;font-weight:700;cursor:pointer}</style><main class="card"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $message . '</main></html>';
}