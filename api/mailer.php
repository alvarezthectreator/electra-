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

function sendEmail(string $recipient, string $subject, string $html, string $text): bool
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
        $mail->send();
        return true;
    } catch (Exception $exception) {
        error_log('Electra mail error: ' . $exception->getMessage());
        return false;
    }
}

function notifyOrderReceived(array $order): void
{
    $code = htmlspecialchars((string) $order['order_code'], ENT_QUOTES, 'UTF-8');
    $total = '₦' . number_format((float) $order['total'], 2);
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $content = '<p>Hello ' . $name . ',</p><p>We received your order <strong>' . $code . '</strong>.</p><p>Order total: <strong>' . $total . '</strong></p><p>We will send another update when your payment is approved.</p>';
    sendEmail((string) $order['customer_email'], 'Order received: ' . $order['order_code'], emailTemplate('ORDER RECEIVED', 'Thank you for your order', $content), strip_tags($content));
    $admin = (string) mailConfig()['admin_email'];
    if ($admin !== '') sendEmail($admin, 'New order: ' . $order['order_code'], emailTemplate('NEW ORDER', 'A new order was received', $content), strip_tags($content));
}

function notifyOrderStatus(array $order, string $status): void
{
    $label = ucwords(str_replace('_', ' ', $status));
    $code = htmlspecialchars((string) $order['order_code'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $content = '<p>Hello ' . $name . ',</p><p>Your order <strong>' . $code . '</strong> is now <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>Thank you for shopping with Elys Beauty Empire.</p>';
    sendEmail((string) $order['customer_email'], 'Order update: ' . $order['order_code'], emailTemplate('ORDER UPDATE', 'Your order status changed', $content), strip_tags($content));
    $admin = (string) mailConfig()['admin_email'];
    if ($admin !== '') sendEmail($admin, 'Order status: ' . $order['order_code'], emailTemplate('ORDER UPDATE', 'Order status changed', $content), strip_tags($content));
}
