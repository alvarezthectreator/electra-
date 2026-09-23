<?php
declare(strict_types=1);

return [
    'host' => getenv('ELYS_SMTP_HOST') ?: 'das131.truehost.cloud',
    'port' => (int) (getenv('ELYS_SMTP_PORT') ?: 587),
    'username' => getenv('ELYS_SMTP_USERNAME') ?: 'admin@aurahuz.com.ng',
    'password' => getenv('ELYS_SMTP_PASSWORD') ?: '',
    'encryption' => getenv('ELYS_SMTP_ENCRYPTION') ?: 'tls',
    'from_email' => getenv('ELYS_MAIL_FROM') ?: 'admin@aurahuz.com.ng',
    'from_name' => getenv('ELYS_MAIL_FROM_NAME') ?: 'Elys Beauty Empire',
    'admin_email' => getenv('ELYS_ADMIN_EMAIL') ?: 'wagwulageorge@gmail.com',
    'support_email' => getenv('ELYS_SUPPORT_EMAIL') ?: 'wagwulageorge@gmail.com',
];
