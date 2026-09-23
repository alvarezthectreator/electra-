<?php
declare(strict_types=1);

return [
    'host' => getenv('ELYS_SMTP_HOST') ?: 'mail.dropsontime.com',
    'port' => (int) (getenv('ELYS_SMTP_PORT') ?: 465),
    'username' => getenv('ELYS_SMTP_USERNAME') ?: 'support@dropsontime.com',
    'password' => getenv('ELYS_SMTP_PASSWORD') ?: '',
    'encryption' => getenv('ELYS_SMTP_ENCRYPTION') ?: 'ssl',
    'from_email' => getenv('ELYS_MAIL_FROM') ?: 'support@dropsontime.com',
    'from_name' => getenv('ELYS_MAIL_FROM_NAME') ?: 'Elys Beauty Empire',
    'admin_email' => getenv('ELYS_ADMIN_EMAIL') ?: 'wagwulageorge@gmail.com',
    'support_email' => getenv('ELYS_SUPPORT_EMAIL') ?: 'wagwulageorge@gmail.com',
];
