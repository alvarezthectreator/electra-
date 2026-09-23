<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_GET['view'])) {
    header('Location: overview.php');
    exit;
}
if (($_GET['view'] ?? '') === 'orders') {
    header('Location: orders.php');
    exit;
}
if (($_GET['view'] ?? '') === 'payments') {
    header('Location: payments.php');
    exit;
}

$db = db();
$views = ['overview', 'orders', 'payments', 'settings'];
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, $views, true)) $view = 'overview';
$orders = $db->query('SELECT id, order_code, storefront, customer_name, customer_email, customer_phone, delivery_location, total, payment_status, payment_method, receipt_path, created_at FROM orders ORDER BY created_at DESC LIMIT 50')->fetchAll();
foreach ($orders as &$order) {
    $order['customer'] = ['name' => $order['customer_name'], 'email' => $order['customer_email'], 'phone' => $order['customer_phone']];
    $order['status'] = $order['payment_status'];
}
unset($order);
$countOrders = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$countCustomers = (int) $db->query('SELECT COUNT(DISTINCT customer_email) FROM orders')->fetchColumn();
$revenue = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'payment_approved'")->fetchColumn();
$pending = (int) $db->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('awaiting_payment', 'payment_pending_confirmation')")->fetchColumn();
$bank = $db->query('SELECT id, bank_name, account_name, account_number FROM bank_settings WHERE id = 1')->fetch() ?: ['id' => 1, 'bank_name' => '', 'account_name' => '', 'account_number' => ''];

function adminEscape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function statusLabel(string $status): array { return match ($status) { 'payment_approved' => ['approved', 'Paid'], 'payment_pending_confirmation' => ['pending', 'Receipt submitted'], 'payment_declined' => ['cancelled', 'Declined'], default => ['awaiting', 'Awaiting payment'] }; }
function orderCustomer(array $order, string $key): string { return (string) ($order['customer'][$key] ?? 'Not provided'); }
function icon(string $name): string { $paths = ['overview' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>', 'products' => '<path d="M4 7.5 8 4h8l4 3.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7.5Z"/><path d="M4 9h16M9 4l1 5m5-5-1 5"/>', 'orders' => '<path d="M3 4h2l2 12h10l2-8H7"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>', 'payments' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 10h18M7 15h3"/>', 'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 15a7 7 0 0 0 0-6M9 19a7 7 0 0 0 6 0M5 15a7 7 0 0 1 0-6M9 5a7 7 0 0 1 6 0"/>', 'logout' => '<path d="M10 17l5-5-5-5M15 12H3M21 3v18"/>']; return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>'; }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= ucfirst($view) ?> - Electra admin</title><link rel="stylesheet" href="admin.css"></head><body>
<div class="admin-shell"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">E</span><span>Electra</span></a><p class="nav-label">Store management</p><nav class="side-nav"><?php foreach (['overview' => 'Overview', 'products' => 'Products', 'orders' => 'Orders', 'payments' => 'Payments'] as $slug => $label): ?><a class="<?= $view === $slug ? 'active' : '' ?>" href="<?= $slug === 'products' ? 'products.php' : ($slug === 'orders' ? 'orders.php' : '?view=' . $slug) ?>"><?= icon($slug) ?><span><?= $label ?></span></a><?php endforeach; ?></nav><p class="nav-label bottom-label">General</p><nav class="side-nav"><a class="<?= $view === 'settings' ? 'active' : '' ?>" href="?view=settings"><?= icon('settings') ?><span>Settings</span></a><a class="sign-out" href="logout.php"><?= icon('logout') ?><span>Sign out</span></a></nav><div class="sidebar-foot"><i></i> Store online</div></aside>
<main class="workspace"><header class="workspace-header"><div><p class="kicker">Electra store</p><h1><?= ucfirst($view) ?></h1></div><div class="profile"><span class="avatar"><?= adminEscape(strtoupper(substr((string) $_SESSION['admin_name'], 0, 1))) ?></span><span><strong><?= adminEscape((string) $_SESSION['admin_name']) ?></strong><small>Store manager</small></span></div></header>
<?php if ($view === 'overview'): ?><section class="page-heading"><div><p class="section-kicker">Your store at a glance</p><h2>Good to see you back.</h2><p>Keep an eye on orders and payment activity.</p></div><a class="primary-button" href="?view=orders">Review orders</a></section><section class="stat-grid"><article class="stat-card"><span>Total revenue</span><strong>₦<?= number_format($revenue, 2) ?></strong><small>Confirmed order value</small></article><article class="stat-card"><span>Total customers</span><strong><?= number_format($countCustomers) ?></strong><small>Unique customer emails</small></article><article class="stat-card"><span>Total orders</span><strong><?= number_format($countOrders) ?></strong><small><?= $pending ? $pending . ' awaiting payment' : 'All clear' ?></small></article><article class="stat-card"><span>Store status</span><strong>Online</strong><small>Electra storefront active</small></article></section><section class="content-grid"><article class="card"><div class="card-heading"><div><h2>Latest orders</h2><p>Most recent customer activity</p></div><a class="view-all" href="?view=orders">View all</a></div><?php if ($orders): foreach (array_slice($orders, 0, 6) as $order): ?><div class="recent-order"><span class="order-avatar"><?= adminEscape(strtoupper(substr(orderCustomer($order, 'name'), 0, 1))) ?></span><span><strong><?= adminEscape(orderCustomer($order, 'name')) ?></strong><small><?= adminEscape((string) $order['order_code']) ?> · <?= adminEscape(statusLabel((string) $order['status'])[1]) ?></small></span><b>₦<?= number_format((float) $order['total'], 2) ?></b></div><?php endforeach; else: ?><div class="empty-state"><strong>No orders yet</strong><p>Customer orders will appear here after checkout.</p></div><?php endif; ?></article><article class="card"><div class="card-heading"><div><h2>Payment queue</h2><p>Orders needing attention</p></div></div><div class="queue-number"><?= $pending ?></div><p class="muted">orders awaiting payment or receipt review</p><a class="primary-button" href="?view=orders">Open orders</a></article></section>
<?php elseif ($view === 'orders'): ?><section class="page-heading"><div><p class="section-kicker">Transactions</p><h2>Orders</h2><p>Every customer order from the Electra database.</p></div><span class="select-pill"><?= $countOrders ?> orders</span></section><section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): [$style, $label] = statusLabel((string) $order['status']); ?><tr><td><strong><?= adminEscape((string) $order['order_code']) ?></strong><small><?= adminEscape((string) ($order['payment_method'] ?: 'No method')) ?></small></td><td><strong><?= adminEscape(orderCustomer($order, 'name')) ?></strong><small><?= adminEscape(orderCustomer($order, 'email')) ?></small></td><td><?= date('M j, Y', strtotime((string) $order['created_at'])) ?></td><td><strong>₦<?= number_format((float) $order['total'], 2) ?></strong></td><td><span class="status <?= $style ?>"><?= adminEscape($label) ?></span></td></tr><?php endforeach; ?></tbody></table><?php if (!$orders): ?><div class="empty-state"><strong>No orders to review yet.</strong><p>Orders will appear here after a customer completes checkout.</p></div><?php endif; ?></div></section>
<?php elseif ($view === 'payments'): ?><section class="page-heading"><div><p class="section-kicker">Payments</p><h2>Payment account</h2><p>This is the bank account returned by Electra checkout.</p></div></section><section class="card payment-card"><h2><?= adminEscape((string) ($bank['bank_name'] ?: 'No bank account configured')) ?></h2><dl><dt>Account name</dt><dd><?= adminEscape((string) $bank['account_name']) ?></dd><dt>Account number</dt><dd><?= adminEscape((string) $bank['account_number']) ?></dd></dl><p class="muted">Update this account in the <code>bank_settings</code> table in the Electra database.</p></section>
<?php else: ?><section class="page-heading"><div><p class="section-kicker">Workspace</p><h2>Settings</h2><p>Electra admin account and database connection.</p></div></section><section class="card settings-card"><h2>Connected</h2><p>This admin uses the database configured in <code>api/config.php</code>: <strong><?= adminEscape(DB_NAME) ?></strong>.</p><p class="muted">Orders, payment settings, and admin credentials are stored in the Electra database.</p></section><?php endif; ?></main></div></body></html>