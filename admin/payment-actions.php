<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';
if (empty($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: payments.php'); exit; }
$bankName = trim((string) ($_POST['bank_name'] ?? ''));
$accountName = trim((string) ($_POST['account_name'] ?? ''));
$accountNumber = trim((string) ($_POST['account_number'] ?? ''));
if ($bankName !== '' && $accountName !== '' && $accountNumber !== '') {
    $statement = db()->prepare('INSERT INTO bank_settings (id, bank_name, account_name, account_number) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE bank_name = VALUES(bank_name), account_name = VALUES(account_name), account_number = VALUES(account_number)');
    $statement->execute([$bankName, $accountName, $accountNumber]);
}
header('Location: payments.php?saved=1');
exit;
