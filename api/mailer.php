<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function mailConfig(): array
{
    static $config;
    return $config ??= require dirname(__DIR__) . '/config/mail.php';
}

function emailTemplate(string $eyebrow, string $title, string $content): string
{
    return '<!doctype html><html lang="en"><body style="margin:0;background:#f6f1e8;color:#151210;font-family:Arial,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px"><tr><td align="center"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#fff;border-top:4px solid #c9a35f"><tr><td style="background:#0d0c0b;padding:26px;color:#e3c88a"><strong style="font-size:20px">Elys Beauty Empire</strong><div style="margin-top:14px;font-size:11px;letter-spacing:1.4px">' . htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') . '</div></td></tr><tr><td style="padding:28px 24px"><h1 style="margin:0 0 16px;font-size:22px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><div style="font-size:14px;line-height:1.7;color:#6d665d">' . $content . '</div></td></tr></table></td></tr></table></body></html>';
}

function sendEmail(string $recipient, string $subject, string $html, string $text, array $attachments = []): bool
{
    $config = mailConfig();
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $config['host'] === '' || $config['from_email'] === '') {
        error_log('Electra mail configuration or recipient is invalid.');
        return false;
    }
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $config['host'];
        $mail->Port = (int) $config['port'];
        $mail->SMTPAuth = (string) $config['username'] !== '';
        $mail->Username = (string) $config['username'];
        $mail->Password = (string) $config['password'];
        $mail->SMTPSecure = $config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($config['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : '');
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
        $mail->addAddress($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        foreach ($attachments as $attachment) {
            if (is_string($attachment) && is_file($attachment)) {
                $mail->addAttachment($attachment);
            }
        }
        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('Electra mail error: ' . $exception->getMessage());
        return false;
    }
}

function paymentApprovalToken(string $orderCode): string
{
    $secret = (string) (getenv('ELYS_APPROVAL_SECRET') ?: 'local-elys-approval-secret');
    return hash_hmac('sha256', $orderCode, $secret);
}

function paymentApprovalUrl(string $orderCode): string
{
    $baseUrl = rtrim((string) (getenv('ELYS_APP_URL') ?: 'http://127.0.0.1:8888/electra'), '/');
    return $baseUrl . '/api/approve_payment.php?order=' . rawurlencode($orderCode) . '&token=' . paymentApprovalToken($orderCode);
}

function notifyPaymentSubmitted(array $order): void
{
    $code = htmlspecialchars((string) $order['order_code'], ENT_QUOTES, 'UTF-8');
    $total = '₦' . number_format((float) $order['total'], 2);
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $approvalUrl = htmlspecialchars(paymentApprovalUrl((string) $order['order_code']), ENT_QUOTES, 'UTF-8');
    $content = '<p>A customer has submitted a payment receipt for order <strong>' . $code . '</strong>.</p><p>Customer: <strong>' . $name . '</strong><br>Order total: <strong>' . $total . '</strong></p><p>The receipt is attached for your records. Please review the payment in your bank account before updating the order.</p><p><a href="' . $approvalUrl . '" style="display:inline-block;padding:13px 20px;background:#c9a35f;color:#151210;text-decoration:none;font-weight:bold">Approve order</a></p>';
    $text = 'A payment receipt was submitted for order ' . $order['order_code'] . '. Customer: ' . $order['customer_name'] . '. Order total: ' . $total . '. Open the order review page: ' . paymentApprovalUrl((string) $order['order_code']);
    $receipt = dirname(__DIR__) . '/' . ltrim((string) ($order['receipt_path'] ?? ''), '/');
    $admin = (string) mailConfig()['admin_email'];
    if ($admin !== '') {
        sendEmail($admin, 'Order review: ' . $order['order_code'], emailTemplate('ORDER REVIEW', 'Receipt submitted for review', $content), $text, [$receipt]);
    }
}

function notifyOrderReceived(array $order): void
{
    $code = htmlspecialchars((string) $order['order_code'], ENT_QUOTES, 'UTF-8');
    $total = '₦' . number_format((float) $order['total'], 2);
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $content = '<p>Hello ' . $name . ',</p><p>We received your order <strong>' . $code . '</strong>.</p><p>Order total: <strong>' . $total . '</strong></p><p>We will send another update when your payment is approved.</p>';
    sendEmail((string) $order['customer_email'], 'Order received: ' . $order['order_code'], emailTemplate('ORDER RECEIVED', 'Thank you for your order', $content), strip_tags($content));
}

function notifyOrderStatus(array $order, string $status): void
{
    $label = ucwords(str_replace('_', ' ', $status));
    $code = htmlspecialchars((string) $order['order_code'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $title = $status === 'processing' ? 'Your order has been approved' : 'Your order status changed';
    $statusMessage = $status === 'processing' ? 'Your payment has been confirmed and your order is now being prepared.' : 'Your order is now <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>.';
    $content = '<p>Hello ' . $name . ',</p><p>Order <strong>' . $code . '</strong> has been approved.</p><p>' . $statusMessage . '</p><p>Thank you for shopping with Elys Beauty Empire.</p>';
    sendEmail((string) $order['customer_email'], 'Order approved: ' . $order['order_code'], emailTemplate('ORDER APPROVED', $title, $content), strip_tags($content));
    $admin = (string) mailConfig()['admin_email'];
    if ($admin !== '') sendEmail($admin, 'Order approved: ' . $order['order_code'], emailTemplate('ORDER APPROVED', $title, $content), strip_tags($content));
}
