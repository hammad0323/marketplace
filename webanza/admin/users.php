<?php
require __DIR__ . '/inc.php';
$me = require_admin();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    if ($do === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $hash = val('SELECT password_hash FROM admins WHERE id = ?', [$me['id']]);
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a name and a valid email.');
        } elseif (!password_verify($cur, $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($new !== '' && strlen($new) < 8) {
            flash('error', 'The new password must be at least 8 characters.');
        } elseif (val('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, $me['id']])) {
            flash('error', 'Another admin already uses that email.');
        } else {
            q('UPDATE admins SET name = ?, email = ? WHERE id = ?', [$name, $email, $me['id']]);
            if ($new !== '') {
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            }
            flash('success', 'Your profile has been updated.');
        }
    } elseif ($do === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            flash('error', 'Enter a name, a valid email and a password of at least 8 characters.');
        } elseif (val('SELECT COUNT(*) FROM admins WHERE email = ?', [$email])) {
            flash('error', 'An admin with that email already exists.');
        } else {
            q('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('success', 'Admin user added.');
        }
    } elseif ($do === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $me['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            q('DELETE FROM admins WHERE id = ?', [$id]);
            flash('success', 'Admin user removed.');
        }
    }
    redirect('admin/users.php');
}

$admins = rows('SELECT id, name, email, last_login, created_at FROM admins ORDER BY id');
admin_header('Admin Users', 'users');
$defaultPw = password_verify('admin123', (string) val('SELECT password_hash FROM admins WHERE id = ?', [$me['id']]));
?>
<?php if ($defaultPw): ?><div class="alert alert-error"><i class="fa-solid fa-shield-halved"></i> You are still using the default password. Change it below now.</div><?php endif; ?>
<div class="edit-grid">
  <div>
    <div class="card flush">
      <div class="card-head pad"><h3>Administrators</h3></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Last login</th><th class="right"></th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
          <tr>
            <td><span class="avatar sm"><?= e(initials($a['name'])) ?></span> <strong><?= e($a['name']) ?></strong><?= (int) $a['id'] === (int) $me['id'] ? ' <span class="badge badge-contacted">You</span>' : '' ?></td>
            <td><?= e($a['email']) ?></td>
            <td class="muted"><?= $a['last_login'] ? e(date('M j, Y g:ia', strtotime($a['last_login']))) : 'Never' ?></td>
            <td class="right"><?php if ((int) $a['id'] !== (int) $me['id']): ?><form method="post" class="inline" data-confirm="Remove this admin?"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="icon-btn danger" title="Remove"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="do" value="add">
      <h3>Add an admin</h3>
      <div class="settings-grid">
        <div class="field"><label>Name</label><input name="name" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      </div>
      <div class="form-buttons"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Add admin</button></div>
    </form>
  </div>
  <form method="post" class="card sticky">
    <?= csrf_field() ?><input type="hidden" name="do" value="profile">
    <h3>My profile &amp; password</h3>
    <div class="field"><label>Name</label><input name="name" value="<?= e($me['name']) ?>" required></div>
    <div class="field"><label>Email (login)</label><input type="email" name="email" value="<?= e($me['email']) ?>" required></div>
    <div class="field"><label>New password</label><input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Leave empty to keep current"></div>
    <div class="field"><label>Current password *</label><input type="password" name="current" required autocomplete="current-password"><small class="help">Required to save changes.</small></div>
    <div class="form-buttons"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Update profile</button></div>
  </form>
</div>
<?php
admin_footer();
