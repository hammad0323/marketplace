<?php
seo_set(['title' => 'Choose a New Password', 'noindex' => true]);
$token = input('token');
$reset = password_reset_find('customer', $token);
$errors = [];
if ($reset && is_post()) {
    csrf_check();
    $pw = (string) ($_POST['password'] ?? '');
    if ($err = password_strong_enough($pw)) $errors[] = $err;
    if ($pw !== ($_POST['password_confirm'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), (int) $reset['user_id']]);
        db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int) $reset['id']]);
        $c = db_row('SELECT * FROM customers WHERE id = ?', [(int) $reset['user_id']]);
        customer_login($c);
        flash('success', 'Your password has been changed.');
        redirect('account');
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-auth">
    <div class="uc-auth-card">
        <h1 class="uc-page-title">New Password</h1>
        <?php if (!$reset): ?>
            <div class="uc-alert uc-alert--error">This reset link is invalid or has expired.</div>
            <a class="uc-btn" href="<?= e(url('account/forgot-password')) ?>">Request a new link</a>
        <?php else: ?>
            <?php if ($errors): ?><div class="uc-alert uc-alert--error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
            <form method="post"><?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label class="uc-field"><span>New password</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                <label class="uc-field"><span>Confirm password</span><input type="password" name="password_confirm" required autocomplete="new-password"></label>
                <button class="uc-btn uc-btn--block" type="submit">Save Password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
