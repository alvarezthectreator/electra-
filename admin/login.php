<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$statement = db()->prepare('SELECT id, name, password_hash FROM admins WHERE email = ? LIMIT 1');
	$statement->execute([strtolower(trim((string) ($_POST['email'] ?? '')))]);
	$admin = $statement->fetch();
	if (!$admin || !password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
		$error = 'Incorrect email or password.';
	} else {
		session_regenerate_id(true);
		$_SESSION['admin_id'] = (int) $admin['id'];
		$_SESSION['admin_name'] = $admin['name'];
		header('Location: index.php');
		exit;
	}
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login — Electra</title><link rel="stylesheet" href="auth.css"></head>
<body><main class="auth-card"><p class="eyebrow">Electra admin</p><h1>Welcome back</h1><?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form method="post"><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Sign in</button></form><p class="auth-link">Need an account? <a href="register.php">Register</a></p></main></body>
</html>