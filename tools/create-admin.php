<?php
/**
 * Create or reset a Super Admin from the command line (cPanel » Terminal, or SSH):
 *   php tools/create-admin.php owner@example.com "Owner Name"
 * You will be prompted for a password. The account must change it at first sign-in
 * unless you pass --keep.
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
define('UC_ROOT', dirname(__DIR__));
require UC_ROOT . '/includes/bootstrap.php';

$email = strtolower($argv[1] ?? '');
$name = $argv[2] ?? 'Store Owner';
if (!valid_email($email)) {
    fwrite(STDERR, "Usage: php tools/create-admin.php email@example.com \"Full Name\" [--keep]\n");
    exit(1);
}
echo 'Password (10+ chars, letters & numbers): ';
if (DIRECTORY_SEPARATOR === '/') system('stty -echo');
$pw = trim((string) fgets(STDIN));
if (DIRECTORY_SEPARATOR === '/') system('stty echo');
echo PHP_EOL;
if (strlen($pw) < 10 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
    fwrite(STDERR, "Password too weak.\n");
    exit(1);
}
$roleId = (int) db_val("SELECT id FROM admin_roles WHERE slug = 'super-admin'");
$must = in_array('--keep', $argv, true) ? 0 : 1;
$existing = db_val('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    db_exec('UPDATE admins SET password_hash = ?, is_active = 1, must_change_password = ?, role_id = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $must, $roleId, (int) $existing]);
    echo "Password reset for $email (Super Admin).\n";
} else {
    db_insert('admins', ['role_id' => $roleId, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => $must]);
    echo "Super Admin $email created.\n";
}
