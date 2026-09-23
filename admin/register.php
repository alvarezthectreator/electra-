<?php
declare(strict_types=1);
session_start();
require_once dirname(__DIR__) . '/api/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$name = trim((string) ($_POST['name'] ?? ''));
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$password = (string) ($_POST['password'] ?? '');

	if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
		$error = 'Enter your name, a valid email, and a password of at least 10 characters.';
	} else {
		try {
			$statement = db()->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
			$statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
			$_SESSION['admin_id'] = (int) db()->lastInsertId();
			$_SESSION['admin_name'] = $name;
			header('Location: index.php');
			exit;
		} catch (PDOException $exception) {
			$error = 'An admin account with that email already exists.';
		}
	}
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register admin — Electra</title><link rel="stylesheet" href="auth.css"></head>
<body><main class="auth-card"><p class="eyebrow">Electra admin</p><h1>Create your admin account</h1><p class="muted">Use a valid email and a password of at least 10 characters.</p><?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form method="post"><label>Name<input name="name" autocomplete="name" required></label><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><button type="submit">Create admin account</button></form><p class="auth-link">Already have an account? <a href="login.php">Sign in</a></p></main></body>
</html>