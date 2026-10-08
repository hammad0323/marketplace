<?php
/**
 * One-time web installer:  https://yourdomain.com/install/
 * Creates the tables, loads the starter content, creates your admin
 * account and writes config.php. Locks itself afterwards.
 */
define('IS_INSTALLER', true);
define('NO_PRETTY_REDIRECT', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

$lock = __DIR__ . '/install.lock';
$errors = [];
$done = false;
$checks = [
    'PHP 7.4 or newer'                => version_compare(PHP_VERSION, '7.4', '>='),
    'PDO MySQL extension'             => extension_loaded('pdo_mysql'),
    'OpenSSL extension (EasyPaisa)'   => extension_loaded('openssl'),
    'GD / image support'              => function_exists('getimagesize'),
    'config.php is writable'          => is_writable(ROOT . '/config.php'),
    '/uploads folder is writable'     => is_writable(ROOT . '/uploads'),
];

function installer_split_sql(string $sql): array
{
    $sql = preg_replace('~^\s*--.*$~m', '', $sql);
    return array_filter(array_map('trim', preg_split('~;\s*(\r?\n|$)~', $sql)), 'strlen');
}

if (!file_exists($lock) && is_post()) {
    $f = [
        'db_host' => trim($_POST['db_host'] ?? 'localhost'), 'db_name' => trim($_POST['db_name'] ?? ''), 'db_user' => trim($_POST['db_user'] ?? ''), 'db_pass' => (string)($_POST['db_pass'] ?? ''),
        'site_name' => trim($_POST['site_name'] ?? 'Eashal Mirha'), 'admin_name' => trim($_POST['admin_name'] ?? ''), 'admin_email' => trim($_POST['admin_email'] ?? ''), 'admin_pass' => (string)($_POST['admin_pass'] ?? ''),
        'demo' => !empty($_POST['demo']),
    ];
    if ($f['db_name'] === '' || $f['db_user'] === '') $errors[] = 'Database name and user are required.';
    if (!filter_var($f['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email.';
    if (strlen($f['admin_pass']) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $f['db_host'] . ';dbname=' . $f['db_name'] . ';charset=utf8mb4', $f['db_user'], $f['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach (installer_split_sql(file_get_contents(__DIR__ . '/schema.sql')) as $stmt) $pdo->exec($stmt);
            foreach (installer_split_sql(file_get_contents(__DIR__ . '/seed.sql')) as $stmt) $pdo->exec($stmt);
            if (!$f['demo']) {
                foreach (['product_images', 'products', 'reviews', 'coupons'] as $t) $pdo->exec("DELETE FROM $t");
            }
            $pdo->exec('DELETE FROM admins');
            $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, 'super')")->execute([$f['admin_name'] ?: 'Store Owner', $f['admin_email'], password_hash($f['admin_pass'], PASSWORD_DEFAULT)]);
            $pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = 'site_name'")->execute([$f['site_name']]);
            $pdo->prepare("UPDATE settings SET svalue = ? WHERE skey = 'email'")->execute([$f['admin_email']]);

            $cfg = file_get_contents(ROOT . '/config.php');
            $rep = ['DB_HOST' => $f['db_host'], 'DB_NAME' => $f['db_name'], 'DB_USER' => $f['db_user'], 'DB_PASS' => $f['db_pass']];
            foreach ($rep as $k => $v) {
                $cfg = preg_replace("~define\('$k',\s*'.*?'\);~", "define('$k', " . var_export($v, true) . ');', $cfg);
            }
            if (@file_put_contents(ROOT . '/config.php', $cfg) === false) {
                $errors[] = 'Database installed, but config.php could not be written. Open config.php and enter your database details manually.';
            }
            @file_put_contents($lock, date('c'));
            $done = !$errors;
        } catch (Throwable $ex) {
            $errors[] = 'Installation failed: ' . $ex->getMessage();
        }
    }
}
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install · Eashal Mirha</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css">
<style>body{background:#0b0b0b url('../assets/images/demo/hero-2.svg') center/cover fixed}.wrap{max-width:720px;margin:40px auto;padding:0 16px}.card h1{font-family:'Cormorant Garamond',serif;font-size:36px;margin:0}.chk{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #eee}.ok{color:#1e7a45}.bad{color:#c0392b}</style>
</head><body>
<div class="wrap"><div class="card">
  <div class="center"><span class="brand__mark lg">EM</span><h1>Eashal Mirha Installer</h1><p class="muted">Set up your store in one step.</p></div>
  <?php if (file_exists($lock) && !$done): ?>
    <div class="alert alert-success">The store is already installed. For security, delete the <code>/install</code> folder from your server.</div>
    <a class="btn btn-primary btn-block" href="<?= e($base) ?>/admin">Go to Admin Panel</a>
  <?php elseif ($done): ?>
    <div class="alert alert-success"><b>Installation complete!</b> Delete the <code>/install</code> folder from your server now.</div>
    <div class="btn-row"><a class="btn btn-primary" href="<?= e($base) ?>/admin">Open Admin Panel</a><a class="btn" href="<?= e($base) ?>/">View Store</a></div>
  <?php else: ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <h4>Server check</h4>
    <?php foreach ($checks as $lbl => $ok): ?><div class="chk"><span><?= e($lbl) ?></span><b class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓ OK' : '✗ Fix needed' ?></b></div><?php endforeach; ?>
    <form method="post" class="mt">
      <h4>1. Database (cPanel → MySQL Databases)</h4>
      <div class="row-2"><?= f_text_i('db_host', 'Host', $_POST['db_host'] ?? 'localhost') ?><?= f_text_i('db_name', 'Database name', $_POST['db_name'] ?? '') ?></div>
      <div class="row-2"><?= f_text_i('db_user', 'Username', $_POST['db_user'] ?? '') ?><?= f_text_i('db_pass', 'Password', '', 'password') ?></div>
      <h4>2. Store & admin account</h4>
      <?= f_text_i('site_name', 'Store name', $_POST['site_name'] ?? 'Eashal Mirha') ?>
      <div class="row-2"><?= f_text_i('admin_name', 'Your name', $_POST['admin_name'] ?? '') ?><?= f_text_i('admin_email', 'Admin email (login)', $_POST['admin_email'] ?? '', 'email') ?></div>
      <?= f_text_i('admin_pass', 'Admin password (min 8 characters)', '', 'password') ?>
      <label class="switch"><input type="checkbox" name="demo" value="1" checked><i></i><span>Install demo products & reviews<small class="help">Recommended — you can edit or delete them later. Categories, homepage banners and pages are always installed.</small></span></label>
      <button class="btn btn-primary btn-block" type="submit">Install Store</button>
    </form>
  <?php endif; ?>
</div></div>
</body></html>
<?php
function f_text_i(string $n, string $l, string $v, string $t = 'text'): string
{
    return '<label class="field"><span>' . e($l) . '</span><input type="' . $t . '" name="' . $n . '" value="' . e($v) . '"' . ($n === 'db_pass' ? '' : ' required') . '></label>';
}
