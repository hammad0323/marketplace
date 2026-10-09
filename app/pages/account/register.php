<?php
if (current_customer()) {
    redirect(path_url('account'));
}
meta_set(['title' => 'Create an account', 'noindex' => true]);
$errors = [];
if (is_post()) {
    require_csrf();
    if (!rate_limit('register', client_ip(), 5, 3600)) {
        $errors['email'] = 'Too many registrations from this connection. Please try later.';
    } elseif (input('website') !== '') {
        $errors['email'] = 'Unable to register.';
    } else {
        [$id, $errors] = customer_register($_POST);
        if ($id) {
            customer_start_session($id);
            if (!empty($_POST['newsletter'])) {
                newsletter_subscribe(mb_strtolower(input('email')), 'I agree to receive emails. Unsubscribe at any time.', 'register');
            }
            flash('success', 'Your account has been created.');
            redirect(path_url('account'));
        }
    }
}
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$cls = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
partial('header');
?>
<div class="container container--narrow page-pad">
  <div class="auth-card" data-reveal="fade-up">
    <p class="eyebrow">Join us</p>
    <h1 class="page-title">Create an account</h1>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="first_name">First name</label><input class="form-control<?= $cls('first_name') ?>" id="first_name" name="first_name" required maxlength="80" autocomplete="given-name" value="<?= e(input('first_name')) ?>"><?= $err('first_name') ?></div>
        <div class="col-md-6"><label class="form-label" for="last_name">Last name</label><input class="form-control<?= $cls('last_name') ?>" id="last_name" name="last_name" maxlength="80" autocomplete="family-name" value="<?= e(input('last_name')) ?>"><?= $err('last_name') ?></div>
        <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control<?= $cls('email') ?>" type="email" id="email" name="email" required maxlength="190" autocomplete="email" value="<?= e(input('email')) ?>"><?= $err('email') ?></div>
        <div class="col-md-6"><label class="form-label" for="phone">Mobile <span class="text-muted">(optional)</span></label><input class="form-control<?= $cls('phone') ?>" type="tel" id="phone" name="phone" maxlength="30" autocomplete="tel" value="<?= e(input('phone')) ?>"><?= $err('phone') ?></div>
        <div class="col-md-6"><label class="form-label" for="password">Password</label><input class="form-control<?= $cls('password') ?>" type="password" id="password" name="password" required minlength="8" maxlength="128" autocomplete="new-password"><?= $err('password') ?></div>
        <div class="col-md-6"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control<?= $cls('password_confirm') ?>" type="password" id="password_confirm" name="password_confirm" required maxlength="128" autocomplete="new-password"><?= $err('password_confirm') ?></div>
        <div class="col-12"><label class="check"><input type="checkbox" name="newsletter" value="1"> Send me news about new collections and offers (optional). Unsubscribe any time.</label></div>
        <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="col-12"><button class="btn-lux btn-lux--block" type="submit">Create account</button></div>
      </div>
    </form>
    <p class="text-center small mt-3">Already registered? <a href="<?= e(path_url('account/login')) ?>">Sign in</a></p>
  </div>
</div>
<?php partial('footer');
