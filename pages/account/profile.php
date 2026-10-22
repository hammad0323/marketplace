<?php
$c = require_customer();
$cid = (int) $c['id'];
seo_set(['title' => 'Profile', 'noindex' => true]);
$errors = [];
if (is_post()) {
    csrf_check();
    if (input('action', '', 'post') === 'password') {
        $row = db_row('SELECT password_hash FROM customers WHERE id = ?', [$cid]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) $errors[] = 'Your current password is incorrect.';
        elseif ($err = password_strong_enough($new)) $errors[] = $err;
        elseif ($new !== ($_POST['new_password_confirm'] ?? '')) $errors[] = 'New passwords do not match.';
        if (!$errors) {
            db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $cid]);
            uc_regenerate_session();
            $_SESSION['customer_id'] = $cid;
            flash('success', 'Password updated.');
            redirect('account/profile');
        }
    } else {
        $d = ['first_name' => mb_substr(input('first_name', '', 'post'), 0, 80), 'last_name' => mb_substr(input('last_name', '', 'post'), 0, 80),
              'phone' => mb_substr(input('phone', '', 'post'), 0, 30), 'newsletter' => input_bool('newsletter')];
        if (mb_strlen($d['first_name']) < 2) $errors[] = 'First name is required.';
        if ($d['phone'] !== '' && !valid_phone($d['phone'])) $errors[] = 'Please enter a valid phone number.';
        if (!$errors) {
            db_update('customers', $d, 'id = ?', [$cid]);
            if ($d['newsletter']) {
                newsletter_subscribe($c['email'], 'account', 'Opted in from account profile');
            } else {
                db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE email = ? AND status = 'subscribed'", [$c['email']]);
            }
            flash('success', 'Profile updated.');
            redirect('account/profile');
        }
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">Profile</h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'profile']); ?>
        <div class="uc-account-main">
            <?php if ($errors): ?><div class="uc-alert uc-alert--error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
            <div class="uc-card-box">
                <h2 class="h5">Personal information</h2>
                <form method="post" class="row g-3"><?= csrf_field() ?>
                    <div class="col-md-6"><label class="uc-field"><span>First name</span><input type="text" name="first_name" required value="<?= e($c['first_name']) ?>"></label></div>
                    <div class="col-md-6"><label class="uc-field"><span>Last name</span><input type="text" name="last_name" value="<?= e($c['last_name']) ?>"></label></div>
                    <div class="col-md-6"><label class="uc-field"><span>Email</span><input type="email" value="<?= e($c['email']) ?>" disabled></label></div>
                    <div class="col-md-6"><label class="uc-field"><span>Phone</span><input type="tel" name="phone" value="<?= e($c['phone']) ?>"></label></div>
                    <div class="col-12"><label class="uc-check"><input type="checkbox" name="newsletter" value="1"<?= (int) $c['newsletter'] ? ' checked' : '' ?>> <span>Receive new arrivals and offers by email</span></label></div>
                    <div class="col-12"><button class="uc-btn" type="submit">Save Changes</button></div>
                </form>
            </div>
            <div class="uc-card-box">
                <h2 class="h5">Change password</h2>
                <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="password">
                    <div class="col-md-4"><label class="uc-field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>New password</span><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>Confirm new password</span><input type="password" name="new_password_confirm" required autocomplete="new-password"></label></div>
                    <div class="col-12"><button class="uc-btn uc-btn--outline" type="submit">Update Password</button></div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
