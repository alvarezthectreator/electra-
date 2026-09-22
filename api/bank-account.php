<?php

declare(strict_types=1);
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$row = db()->query('SELECT bank_name, account_name, account_number FROM bank_settings WHERE id = 1')->fetch();
jsonResponse(['success' => true, 'data' => $row ?: [
    'bank_name' => 'Elys Beauty Empire',
    'account_name' => 'Contact customer care',
    'account_number' => '08065970828 / 08124863873'
]]);
