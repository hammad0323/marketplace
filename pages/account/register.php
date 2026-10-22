<?php
seo_set(['title' => 'Create Account', 'noindex' => true]);
if (current_customer()) {
    redirect('account');
}
$errors = [];
if (is_post()) {
    csrf_check();
    $d = [
        'first_name' => mb_substr(input('first_name', '', 'post'), 0, 80), 'last_name' => mb_substr(input('last_name', '', 'post'), 0, 80),
        'email' => strtolower(input('email', '', 'post')), 'phone' => mb_substr(input('phone', '', 'post'), 0, 30),
        'password' => (string) ($_POST['password'] ?? ''), 'newsletter' => input_bool('newsletter'),
    ];
    if (!rate_limit('register:' . client_ip(), 6, 3600)) $errors[] = 'Too many accounts created from this connection. Please try later.';
    if (mb_strlen($d['first_name']) < 2) $errors[] = 'Please enter your first name.';
    if (!valid_email($d['email'])) $errors[] = 'Please enter a valid email address.';
    if ($d['phone'] !== '' && !valid_phone($d['phone'])) $errors[] = 'Please enter a valid phone number.';
    if ($err = password_strong_enough($d['password'])) $errors[] = $err;
    if (($_POST['password_confirm'] ?? '') !== $d['password']) $errors[] = 'Passwords do not match.';
    if (!$errors && db_val('SELECT id FROM customers WHERE email = ?', [$d['email']])) $errors[] = 'An account with this email already exists. Please sign in.';
    if (!$errors) {
        $id = customer_create($d);
        customer_login(['id' => $id]);
        // Link earlier guest orders placed with this email, when the visitor proved access via the order link
        $num = input('order', '', 'post');
        $tok = input('t', '', 'post');
        if ($num && $tok && ($o = order_for_viewer($num, $tok)) && strtolower($o['email']) === $d['email']) {
            db_exec('UPDATE orders SET customer_id = ? WHERE email = ? AND customer_id IS NULL', [$id, $d['email']]);
        }
        if ($d['newsletter']) {
            newsletter_subscribe($d['email'], 'register', 'Opted in at registration');
        }
        flash('success', 'Welcome to ' . store_name() . ', ' . $d['first_name'] . '!');
        redirect('account');
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-auth">
    <div class="uc-auth-card">
        <h1 class="uc-page-title">Create Account</h1>
        <?php if ($errors): ?><div class="uc-alert uc-alert--error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="order" value="<?= e(input('order')) ?>"><input type="hidden" name="t" value="<?= e(input('t')) ?>">
            <div class="row g-3">
                <div class="col-6"><label class="uc-field"><span>First name *</span><input type="text" name="first_name" required autocomplete="given-name" value="<?= e(input('first_name', '', 'post')) ?>"></label></div>
                <div class="col-6"><label class="uc-field"><span>Last name</span><input type="text" name="last_name" autocomplete="family-name" value="<?= e(input('last_name', '', 'post')) ?>"></label></div>
            </div>
            <label class="uc-field"><span>Email *</span><input type="email" name="email" required autocomplete="email" value="<?= e(input('email')) ?>"></label>
            <label class="uc-field"><span>Phone</span><input type="tel" name="phone" autocomplete="tel" value="<?= e(input('phone', '', 'post')) ?>"></label>
            <label class="uc-field"><span>Password * <small>(8+ characters, letters &amp; numbers)</small></span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label class="uc-field"><span>Confirm password *</span><input type="password" name="password_confirm" required autocomplete="new-password"></label>
            <label class="uc-check"><input type="checkbox" name="newsletter" value="1"> <span>Email me new arrivals and offers</span></label>
            <button class="uc-btn uc-btn--block mt-3" type="submit">Create Account</button>
        </form>
        <p class="text-center mt-4 mb-0">Already have an account? <a href="<?= e(url('account/login')) ?>">Sign in</a></p>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
