<?php
/**
 * One-time installer: creates the tables + starter content from
 * database.sql and sets your admin login. Delete this file afterwards.
 */
require __DIR__ . '/config.php';

$done = false;
$error = '';
try {
    $installed = (bool) db()->query("SHOW TABLES LIKE 'admins'")->fetchColumn()
        && (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (PDOException $e) {
    $installed = false;
}

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $error = 'Please enter your name, a valid email and a password of at least 8 characters.';
    } else {
        try {
            $sql = (string) file_get_contents(__DIR__ . '/database.sql');
            $sql = preg_replace('~^\s*--.*$~m', '', $sql);
            foreach (array_filter(array_map('trim', preg_split('~;\s*$~m', $sql))) as $stmt) {
                db()->exec($stmt);
            }
            q('DELETE FROM admins');
            q('INSERT INTO admins (id, name, email, password_hash) VALUES (1, ?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $done = true;
        } catch (PDOException $e) {
            $error = 'Install failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install · Webanza Tech</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-page">
<div class="login-card">
  <img class="login-logo" src="<?= e(url('assets/img/logo.png')) ?>" alt="">
  <?php if ($installed && !$done): ?>
    <h1>Already installed</h1>
    <p class="muted">The database is ready. For security, <strong>delete install.php</strong> from your server.</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Go to admin login</a>
  <?php elseif ($done): ?>
    <h1>🎉 All set!</h1>
    <p class="muted">Your website is installed. Now <strong>delete install.php</strong> from your server, then log in to customise everything.</p>
    <a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Go to admin login</a>
    <a class="back" href="<?= e(url()) ?>">View website</a>
  <?php else: ?>
    <h1>Install your website</h1>
    <p class="muted">Database connection works. Create your admin login to finish.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <label class="field"><span>Your name</span><input name="name" required value="<?= e($_POST['name'] ?? 'Hammad Jamil') ?>"></label>
      <label class="field"><span>Admin email</span><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label class="field"><span>Password (min 8 characters)</span><input type="password" name="password" minlength="8" required></label>
      <button class="btn btn-primary btn-block" type="submit">Install now</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
