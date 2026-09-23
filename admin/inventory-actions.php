<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';
if (empty($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: inventory.php'); exit; }
$id = trim((string) ($_POST['id'] ?? ''));
$stock = filter_var($_POST['stock_quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
$threshold = filter_var($_POST['low_stock_threshold'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($id !== '' && $stock !== false && $threshold !== false) {
    $statement = db()->prepare('UPDATE products SET stock_quantity = ?, low_stock_threshold = ? WHERE id = ?');
    $statement->execute([$stock, $threshold, $id]);
}
header('Location: inventory.php?saved=1');
exit;
