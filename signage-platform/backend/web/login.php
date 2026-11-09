<?php
require __DIR__ . '/_bootstrap.php';
if (web_user()) { header('Location: ' . web_url('index.php')); exit; }
$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    web_check_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    // Same brute-force protection as the API (shared rate_limits table).
    $win = gmdate('Y-m-d H:i:s', intdiv(time(), 900) * 900);
    foreach (['login:ip:' . client_ip() => 30, 'login:email:' . $email => 10] as $bucket => $max) {
        db_run('INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1', [$bucket, $win]);
        if ((int)db_val('SELECT hits FROM rate_limits WHERE bucket_key = ? AND window_start = ?', [$bucket, $win]) > $max) $error = 'Too many attempts. Please wait 15 minutes and try again.';
    }
    if (!$error) {
        $u = db_one('SELECT u.*, r.role_key FROM users u JOIN roles r USING (role_id) WHERE u.email = ? AND u.deleted_at IS NULL', [$email]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            $error = 'Incorrect email or password';
            log_activity($u['user_id'] ?? null, 'auth.login_failed', 'user', $u['user_id'] ?? null, "Failed web login for {$email}");
        } elseif ($p = user_account_problem($u)) {
            $error = $p[0];
        } else {
            session_regenerate_id(true);
            [$token] = issue_user_token((int)$u['user_id'], 'Web browser');
            $_SESSION['api_token'] = $token;
            db_run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE user_id = ?', [$u['user_id']]);
            log_activity((int)$u['user_id'], 'auth.login', 'user', (int)$u['user_id'], 'Signed in (web)');
            header('Location: ' . web_url($u['role_key'] === ROLE_SUPER_ADMIN ? 'admin/index.php' : 'dashboard.php'));
            exit;
        }
    }
}
$base = web_base();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= h(app_setting('app_name', 'Signal Signage')) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
<link rel="stylesheet" href="<?= h($base) ?>/web/assets/web.css?v=3"></head>
<body class="auth">
<form class="auth-card" method="post" autocomplete="on">
  <span class="brand-mark big ms">cast_connected</span>
  <h1>Welcome back</h1>
  <p class="muted">Sign in to manage your screens and displays.</p>
  <?php if (!empty($_GET['expired'])): ?><div class="alert">Your session expired. Please sign in again.</div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= h(web_csrf()) ?>">
  <label>Email<input type="email" name="email" required value="<?= h($email) ?>" autofocus></label>
  <label>Password<input type="password" name="password" required></label>
  <button class="btn primary block" type="submit">Sign in</button>
  <p class="muted small center">Accounts are created by your administrator.</p>
</form>
</body></html>
