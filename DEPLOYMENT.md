# Electra production checklist

## HTTPS and hosting

Deploy behind Apache or Nginx with a real domain and a trusted TLS certificate (for example, Let's Encrypt). Enable the HTTPS redirect in `.htaccess` only after the certificate is active. Do not expose the MAMP development server publicly.

## Environment

Set `ELYS_DB_HOST`, `ELYS_DB_PORT`, `ELYS_DB_NAME`, `ELYS_DB_USER`, `ELYS_DB_PASS`, and `ELYS_ADMIN_TOKEN` in the hosting environment. Replace the local defaults in `api/config.php` before deployment and use a separate production database.

For email notifications, also set `ELYS_SMTP_HOST`, `ELYS_SMTP_PORT`, `ELYS_SMTP_USERNAME`, `ELYS_SMTP_PASSWORD`, `ELYS_SMTP_ENCRYPTION` (`tls`, `ssl`, or `none`), `ELYS_MAIL_FROM`, `ELYS_MAIL_FROM_NAME`, and `ELYS_ADMIN_EMAIL`. The local defaults target MailHog on port 1025 and do not send real email.

## Backups

Run `scripts/backup-database.sh` from a protected scheduled job. Store backups outside the web root and test restoring one regularly. Back up `uploads/`, `img/uploads/`, and the application source separately.

## Logging

PHP errors are written to `storage/logs/php-error.log` with display disabled. Keep `storage/` outside public access where possible and rotate logs on the host.

## Images

The repository's large campaign images should be resized to the display dimensions and converted to WebP or AVIF during the deployment build. Product uploads should also be resized and validated before being served.
