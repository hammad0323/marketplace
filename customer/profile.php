<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'profile') {
        $name = clean_input($_POST['name'] ?? '');
        $phone = clean_input($_POST['phone'] ?? '');
        require_field($name, 'Name', $errors);

        $avatarPath = $user['avatar'];
        if (!empty($_FILES['avatar']['name'])) {
            $upload = upload_file('avatar', 'users');
            if (!$upload['ok'] && $upload['error']) {
                $errors[] = $upload['error'];
            } elseif ($upload['ok']) {
                $avatarPath = $upload['path'];
            }
        }

        if (!$errors) {
            db_execute($conn, 'UPDATE users SET name = ?, phone = ?, avatar = ? WHERE id = ?', [$name, $phone, $avatarPath, (int) $user['id']]);
            $_SESSION['user_name'] = $name;
            flash_set('success', 'Profile updated.');
            redirect('/customer/profile.php');
        }
    } elseif ($form === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if (!$errors) {
            db_execute($conn, 'UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            flash_set('success', 'Password changed.');
            redirect('/customer/profile.php');
        }
    }
}

$pageTitle = 'Profile';
$customerActiveTab = 'profile';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-person"></i> Customer</span>
      <h1 class="section-heading">Profile settings</h1>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <div class="panel" style="max-width:560px;">
      <h3 style="font-size:15px;margin-bottom:16px;">Personal information</h3>
      <form method="post" class="form-w" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="profile">
        <?php if ($user['avatar']): ?>
          <img src="<?php echo e($user['avatar']); ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:14px;">
        <?php endif; ?>
        <label>Avatar</label>
        <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp">
        <label>Full name</label>
        <input type="text" name="name" value="<?php echo e($user['name']); ?>" required>
        <label>Email</label>
        <input type="email" value="<?php echo e($user['email']); ?>" disabled style="background:var(--bg);color:var(--ink-mute);">
        <div class="form-hint">Email address can't be changed.</div>
        <label>Phone</label>
        <input type="tel" name="phone" value="<?php echo e($user['phone']); ?>">
        <button type="submit" class="btn-w btn-primary" style="margin-top:20px;">Save changes</button>
      </form>
    </div>

    <div class="panel" style="max-width:560px;">
      <h3 style="font-size:15px;margin-bottom:16px;">Change password</h3>
      <form method="post" class="form-w">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="password">
        <label>Current password</label>
        <input type="password" name="current_password" required>
        <label>New password</label>
        <input type="password" name="new_password" minlength="8" required>
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" minlength="8" required>
        <button type="submit" class="btn-w btn-primary" style="margin-top:20px;">Update password</button>
      </form>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
