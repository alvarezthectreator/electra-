<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/mailer.php';
if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = trim((string) ($_POST['recipient'] ?? ''));
    $admin = (string) mailConfig()['admin_email'];
    $html = emailTemplate('MAIL TEST', 'Electra email test', '<p>This is a test notification from Electra.</p><p>If you received this message, SMTP delivery is working.</p>');
    $customerSent = sendEmail($recipient, 'Electra customer email test', $html, 'This is a test notification from Electra.');
    $adminSent = $admin !== '' && sendEmail($admin, 'Electra admin email test', $html, 'This is a test notification from Electra.');
    $result = ['customer' => $customerSent, 'admin' => $adminSent, 'admin_email' => $admin];
}
function testEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mail test - Electra admin</title><link rel="stylesheet" href="admin.css"><style>.test-card{max-width:620px}.test-card form{display:grid;gap:14px}.test-card input{padding:13px;border:1px solid var(--line);border-radius:7px;font:inherit}.test-card button{width:max-content;padding:12px 17px;border:0;border-radius:7px;background:#11100e;color:#e6cd93;font:700 13px inherit;cursor:pointer}.result{margin-bottom:18px;padding:14px;background:var(--gold-pale);border:1px solid var(--gold);color:#795722}</style></head><body><div class="admin-shell"><aside class="sidebar"><a class="brand" href="overview.php"><span class="brand-mark">E</span><span>Electra</span></a><p class="nav-label">Store management</p><nav class="side-nav"><a href="overview.php"><span>Overview</span></a><a href="products.php"><span>Products</span></a><a href="orders.php"><span>Orders</span></a><a href="payments.php"><span>Payments</span></a><a href="inventory.php"><span>Inventory</span></a></nav><p class="nav-label bottom-label">General</p><nav class="side-nav"><a href="mail-test.php" class="active"><span>Mail test</span></a><a class="sign-out" href="logout.php"><span>Sign out</span></a></nav><div class="sidebar-foot"><i></i> Store online</div></aside><main class="workspace"><header class="workspace-header"><div><p class="kicker">Electra store</p><h1>Mail test</h1></div></header><?php if($result): ?><p class="result">Customer email: <?= $result['customer'] ? 'sent' : 'failed' ?>. Admin email: <?= $result['admin'] ? 'sent' : ($result['admin_email'] === '' ? 'not configured' : 'failed') ?>.</p><?php endif; ?><section class="card test-card"><h2>Test customer and admin notifications</h2><p class="muted">Enter a customer email. Electra sends one test to this address and one to the configured admin email.</p><form method="post"><label>Customer test email<input type="email" name="recipient" required placeholder="customer@example.com"></label><button type="submit">Send test emails</button></form></section></main></div></body></html>
