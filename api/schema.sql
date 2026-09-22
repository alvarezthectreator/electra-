CREATE DATABASE IF NOT EXISTS angel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE angel;

INSERT INTO bank_settings (id, bank_name, account_name, account_number)
VALUES (1, 'YOUR BANK NAME', 'ELYS BEAUTY EMPIRE', 'YOUR ACCOUNT NUMBER')
ON DUPLICATE KEY UPDATE bank_name = VALUES(bank_name), account_name = VALUES(account_name), account_number = VALUES(account_number);
