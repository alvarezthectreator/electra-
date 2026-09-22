# Elys Beauty API

The storefront uses these same-origin endpoints:

- `POST /api/orders.php` creates an order and returns an `order_code`.
- `GET /api/bank-account.php` returns the configured transfer account.
- `POST /api/confirm_payment.php` accepts a receipt upload and marks the order for payment review.
- `GET /api/admin/orders.php` lists orders.
- `PATCH /api/admin/orders.php` updates an order status with `{ "order_code": "...", "status": "paid" }`.

MAMP defaults are configured in `config.php`: MySQL host `127.0.0.1`, database `elys_beauty`, user `root`, and an empty password. Set `ELYS_DB_HOST`, `ELYS_DB_NAME`, `ELYS_DB_USER`, `ELYS_DB_PASS`, and `ELYS_ADMIN_TOKEN` in the server environment for production. The database and tables are created automatically on the first API request.

Before accepting real payments, replace the placeholder bank account row in `schema.sql` or insert it into `bank_settings`.
