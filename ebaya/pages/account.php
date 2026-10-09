<?php
/** Customer accounts: login, register, password reset, dashboard, orders, addresses, profile. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$errors = [];
$customer = current_customer();
$guestOnly = ['login', 'register', 'forgot-password', 'reset-password'];
if ($customer && in_array($sub, $guestOnly, true)) redirect('account');
if (!$customer && !in_array($sub, $guestOnly, true) && $sub !== 'logout') {
    redirect('account/login?return=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
}
$return = safe_return(get('return', post('return')), 'account');

// ------------------------------------------------------------------ actions
if ($sub === 'logout') {
    if (is_post()) {
        csrf_check();
        customer_logout();
        flash('success', 'You have been signed out.');
    }
    redirect('');
}

if (is_post()) {
    csrf_check();
    switch ($sub) {
        case 'login':
            rate_limit_or_fail('login', 8, 900);
            rate_limit_or_fail('login-email', 8, 900, strtolower(post('email')));
            $c = customer_attempt_login(post('email'), (string)($_POST['password'] ?? ''));
            if ($c) {
                customer_login($c);
                flash('success', 'Welcome back, ' . strtok($c['name'], ' ') . '.');
                redirect($return);
            }
            $errors['form'] = 'The email or password is incorrect.';
            break;

        case 'register':
            rate_limit_or_fail('register', 6, 3600);
            $name = mb_substr(post('name'), 0, 120);
            $email = strtolower(post('email'));
            $phone = post('phone');
            $pw = (string)($_POST['password'] ?? '');
            if (!v_len($name, 2, 120)) $errors['name'] = 'Please enter your name.';
            if (!v_email($email)) $errors['email'] = 'Please enter a valid email.';
            elseif (db_val('SELECT id FROM customers WHERE email = ?', [$email])) $errors['email'] = 'An account with this email already exists.';
            if ($phone !== '' && !v_phone($phone)) $errors['phone'] = 'Please enter a valid phone number.';
            if ($e = v_password($pw)) $errors['password'] = $e;
            elseif ($pw !== ($_POST['password_confirm'] ?? '')) $errors['password_confirm'] = 'Passwords do not match.';
            if (!$errors) {
                $id = customer_create($name, $email, $phone, $pw, (bool)post('marketing'));
                // Link earlier guest orders only when the visitor proves ownership with the order key.
                $ordNo = post('order');
                $ordKey = post('key');
                if ($ordNo && $ordKey) {
                    $o = order_find_public($ordNo, $ordKey);
                    if ($o && $o['customer_id'] === null && strcasecmp($o['email'], $email) === 0) {
                        db_exec('UPDATE orders SET customer_id = ? WHERE id = ?', [$id, (int)$o['id']]);
                    }
                }
                customer_login(db_one('SELECT * FROM customers WHERE id = ?', [$id]));
                flash('success', 'Welcome to Ebaya! Your account has been created.');
                redirect($return);
            }
            break;

        case 'forgot-password':
            rate_limit_or_fail('forgot', 5, 3600);
            $email = strtolower(post('email'));
            if (v_email($email) && ($c = db_one("SELECT id, name FROM customers WHERE email = ? AND status = 'active'", [$email]))) {
                $token = password_reset_create('customer', (int)$c['id']);
                $link = abs_url('account/reset-password?token=' . $token);
                send_mail($email, 'Reset your Ebaya password', '<p>Dear ' . e($c['name']) . ',</p><p>Use the link below to choose a new password. It expires in one hour.</p><p><a href="' . e($link) . '">Reset my password</a></p><p>If you did not request this, you can ignore this email.</p>');
            }
            // Same message whether or not the email exists (no account enumeration).
            flash('success', 'If an account exists for that email, we have sent a reset link. Please check your inbox.');
            redirect('account/login');

        case 'reset-password':
            rate_limit_or_fail('reset', 10, 3600);
            $reset = password_reset_find('customer', post('token'));
            $pw = (string)($_POST['password'] ?? '');
            if (!$reset) {
                $errors['form'] = 'This reset link is invalid or has expired.';
            } elseif ($e = v_password($pw)) {
                $errors['password'] = $e;
            } elseif ($pw !== ($_POST['password_confirm'] ?? '')) {
                $errors['password_confirm'] = 'Passwords do not match.';
            } else {
                db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), (int)$reset['user_id']]);
                db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [(int)$reset['id']]);
                flash('success', 'Your password has been updated. Please sign in.');
                redirect('account/login');
            }
            break;

        case 'profile':
            $name = mb_substr(post('name'), 0, 120);
            $phone = post('phone');
            if (!v_len($name, 2, 120)) $errors['name'] = 'Please enter your name.';
            if ($phone !== '' && !v_phone($phone)) $errors['phone'] = 'Please enter a valid phone number.';
            $newPw = (string)($_POST['new_password'] ?? '');
            if ($newPw !== '') {
                $row = db_one('SELECT password_hash FROM customers WHERE id = ?', [(int)$customer['id']]);
                if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) $errors['current_password'] = 'Current password is incorrect.';
                elseif ($e = v_password($newPw)) $errors['new_password'] = $e;
            }
            if (!$errors) {
                db_exec('UPDATE customers SET name = ?, phone = ?, marketing_opt_in = ? WHERE id = ?', [$name, $phone, post('marketing') ? 1 : 0, (int)$customer['id']]);
                if ($newPw !== '') {
                    db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($newPw, PASSWORD_DEFAULT), (int)$customer['id']]);
                    session_regenerate_id(true);
                }
                flash('success', 'Your profile has been updated.');
                redirect('account/profile');
            }
            break;

        case 'addresses':
            $act = post('act');
            $aid = (int)post('id');
            if ($act === 'delete') {
                db_exec('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$aid, (int)$customer['id']]);
                flash('success', 'Address removed.');
                redirect('account/addresses');
            }
            if ($act === 'default') {
                db_exec('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?', [$aid, (int)$customer['id']]);
                redirect('account/addresses');
            }
            $a = ['label' => mb_substr(post('label'), 0, 60), 'full_name' => mb_substr(post('full_name'), 0, 120), 'phone' => post('phone'), 'address_line1' => mb_substr(post('address_line1'), 0, 255),
                  'address_line2' => mb_substr(post('address_line2'), 0, 255), 'city' => mb_substr(post('city'), 0, 100), 'province' => in_list(post('province'), shipping_provinces(), ''), 'postal_code' => mb_substr(post('postal_code'), 0, 20)];
            if (!v_len($a['full_name'], 2, 120) || !v_phone($a['phone']) || !v_len($a['address_line1'], 5, 255) || !v_len($a['city'], 2, 100)) {
                $errors['form'] = 'Please complete name, a valid phone, address and city.';
                break;
            }
            $vals = [$a['label'] ?: null, $a['full_name'], $a['phone'], $a['address_line1'], $a['address_line2'] ?: null, $a['city'], $a['province'] ?: null, $a['postal_code'] ?: null];
            if ($aid) {
                db_exec('UPDATE customer_addresses SET label=?, full_name=?, phone=?, address_line1=?, address_line2=?, city=?, province=?, postal_code=? WHERE id = ? AND customer_id = ?', array_merge($vals, [$aid, (int)$customer['id']]));
            } else {
                $first = !db_val('SELECT id FROM customer_addresses WHERE customer_id = ?', [(int)$customer['id']]);
                db_insert('INSERT INTO customer_addresses (label, full_name, phone, address_line1, address_line2, city, province, postal_code, customer_id, is_default) VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [(int)$customer['id'], $first ? 1 : 0]));
            }
            flash('success', 'Address saved.');
            redirect('account/addresses');
    }
}

// ------------------------------------------------------------------ views
$titles = ['login' => 'Sign In', 'register' => 'Create Account', 'forgot-password' => 'Forgot Password', 'reset-password' => 'Reset Password',
           'dashboard' => 'My Account', 'orders' => 'My Orders', 'addresses' => 'Addresses', 'profile' => 'Profile'];
seo_set(['title' => $titles[$sub] ?? 'Account', 'noindex' => true]);
$bodyClass = 'page-account';
require ROOT_PATH . '/templates/header.php';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<section class="page-section">
<?php if (in_array($sub, $guestOnly, true)): ?>
  <div class="container-eb auth-wrap">
    <div class="auth-card" data-reveal>
      <h1 class="page-title text-center"><?= e($titles[$sub]) ?></h1>
      <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>

      <?php if ($sub === 'login'): ?>
        <form method="post" novalidate>
          <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($return) ?>">
          <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" name="email" id="email" required autocomplete="email" value="<?= e(post('email')) ?>"></div>
          <div class="mb-2"><label class="form-label" for="password">Password</label><input class="form-control" type="password" name="password" id="password" required autocomplete="current-password"></div>
          <p class="text-end small"><a href="<?= e(url('account/forgot-password')) ?>">Forgot password?</a></p>
          <button class="btn btn-eb btn-primary-eb w-100">Sign in</button>
        </form>
        <p class="text-center mt-4 small">New to Ebaya? <a href="<?= e(url('account/register?return=' . urlencode($return))) ?>">Create an account</a></p>
        <p class="text-center small text-muted">You can also check out as a guest — no account needed.</p>

      <?php elseif ($sub === 'register'): ?>
        <form method="post" novalidate>
          <?= csrf_field() ?><input type="hidden" name="return" value="<?= e($return) ?>">
          <input type="hidden" name="order" value="<?= e(get('order', post('order'))) ?>"><input type="hidden" name="key" value="<?= e(get('key', post('key'))) ?>">
          <div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control<?= $inv('name') ?>" name="name" id="name" required maxlength="120" value="<?= e(post('name')) ?>" autocomplete="name"><?= $err('name') ?></div>
          <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control<?= $inv('email') ?>" type="email" name="email" id="email" required value="<?= e(post('email', get('email'))) ?>" autocomplete="email"><?= $err('email') ?></div>
          <div class="mb-3"><label class="form-label" for="phone">Phone (optional)</label><input class="form-control<?= $inv('phone') ?>" type="tel" name="phone" id="phone" value="<?= e(post('phone')) ?>" autocomplete="tel"><?= $err('phone') ?></div>
          <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control<?= $inv('password') ?>" type="password" name="password" id="password" required minlength="8" autocomplete="new-password"><?= $err('password') ?><div class="form-text">At least 8 characters, with letters and numbers.</div></div>
          <div class="mb-3"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control<?= $inv('password_confirm') ?>" type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password"><?= $err('password_confirm') ?></div>
          <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="marketing" value="1"> <span class="form-check-label small">Email me about new collections (optional)</span></label>
          <button class="btn btn-eb btn-primary-eb w-100">Create account</button>
        </form>
        <p class="text-center mt-4 small">Already have an account? <a href="<?= e(url('account/login')) ?>">Sign in</a></p>

      <?php elseif ($sub === 'forgot-password'): ?>
        <p class="text-center text-muted">Enter your email and we'll send you a link to reset your password.</p>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" name="email" id="email" required autocomplete="email"></div>
          <button class="btn btn-eb btn-primary-eb w-100">Send reset link</button>
        </form>

      <?php elseif ($sub === 'reset-password'): $tok = get('token', post('token')); ?>
        <?php if (!password_reset_find('customer', $tok)): ?>
          <div class="alert alert-warning">This reset link is invalid or has expired. <a href="<?= e(url('account/forgot-password')) ?>">Request a new one</a>.</div>
        <?php else: ?>
        <form method="post" novalidate>
          <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($tok) ?>">
          <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control<?= $inv('password') ?>" type="password" name="password" id="password" required minlength="8" autocomplete="new-password"><?= $err('password') ?></div>
          <div class="mb-3"><label class="form-label" for="password_confirm">Confirm password</label><input class="form-control<?= $inv('password_confirm') ?>" type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password"><?= $err('password_confirm') ?></div>
          <button class="btn btn-eb btn-primary-eb w-100">Update password</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="container-eb">
    <h1 class="page-title"><?= e($titles[$sub] ?? 'My Account') ?></h1>
    <div class="row g-4">
      <div class="col-lg-3">
        <nav class="account-nav">
          <?php foreach (['dashboard' => ['Overview', 'grid'], 'orders' => ['Orders', 'bag'], 'addresses' => ['Addresses', 'geo-alt'], 'profile' => ['Profile & password', 'person']] as $k => [$l, $ic]): ?>
            <a href="<?= e(url('account' . ($k === 'dashboard' ? '' : '/' . $k))) ?>" class="<?= $sub === $k ? 'active' : '' ?>"><i class="bi bi-<?= $ic ?>"></i> <?= e($l) ?></a>
          <?php endforeach; ?>
          <a href="<?= e(url('wishlist')) ?>"><i class="bi bi-heart"></i> Wishlist</a>
          <form method="post" action="<?= e(url('account/logout')) ?>"><?= csrf_field() ?><button class="link-btn"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
        </nav>
      </div>
      <div class="col-lg-9">
        <?php if ($sub === 'dashboard'):
          $recent = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 3', [(int)$customer['id']]); ?>
          <p>Welcome, <strong><?= e($customer['name']) ?></strong>.</p>
          <div class="row g-3 mb-4">
            <div class="col-sm-4"><div class="stat-card"><span><?= (int)db_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int)$customer['id']]) ?></span>Orders</div></div>
            <div class="col-sm-4"><div class="stat-card"><span><?= count(wishlist_ids()) ?></span>Wishlist</div></div>
            <div class="col-sm-4"><div class="stat-card"><span><?= (int)db_val('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [(int)$customer['id']]) ?></span>Addresses</div></div>
          </div>
          <h2 class="h5">Recent orders</h2>
          <?php $orders = $recent; include ROOT_PATH . '/templates/account-orders.php'; ?>

        <?php elseif ($sub === 'orders'):
          $orders = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 100', [(int)$customer['id']]);
          include ROOT_PATH . '/templates/account-orders.php'; ?>

        <?php elseif ($sub === 'addresses'):
          $addrs = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id', [(int)$customer['id']]);
          $edit = get('edit') ? db_one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [(int)get('edit'), (int)$customer['id']]) : null; ?>
          <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
          <div class="row g-3 mb-4">
            <?php foreach ($addrs as $a): ?>
              <div class="col-md-6"><div class="card-eb h-100">
                <strong><?= e($a['label'] ?: 'Address') ?></strong> <?= $a['is_default'] ? '<span class="badge text-bg-light">Default</span>' : '' ?>
                <p class="small mt-2 mb-2"><?= e($a['full_name']) ?><br><?= e($a['address_line1']) ?><?= $a['address_line2'] ? ', ' . e($a['address_line2']) : '' ?><br><?= e($a['city']) ?><?= $a['province'] ? ', ' . e($a['province']) : '' ?><br><?= e($a['phone']) ?></p>
                <div class="d-flex gap-3 small">
                  <a href="?edit=<?= (int)$a['id'] ?>">Edit</a>
                  <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="default"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-btn">Make default</button></form><?php endif; ?>
                  <form method="post" data-confirm="Remove this address?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-btn text-danger">Remove</button></form>
                </div>
              </div></div>
            <?php endforeach; ?>
          </div>
          <div class="card-eb">
            <h2 class="h5"><?= $edit ? 'Edit address' : 'Add an address' ?></h2>
            <form method="post" class="row g-3" novalidate>
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
              <div class="col-md-4"><label class="form-label">Label</label><input class="form-control" name="label" maxlength="60" placeholder="Home, Office…" value="<?= e($edit['label'] ?? '') ?>"></div>
              <div class="col-md-4"><label class="form-label">Full name</label><input class="form-control" name="full_name" required value="<?= e($edit['full_name'] ?? $customer['name']) ?>"></div>
              <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" required value="<?= e($edit['phone'] ?? $customer['phone']) ?>"></div>
              <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address_line1" required value="<?= e($edit['address_line1'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Apartment / landmark</label><input class="form-control" name="address_line2" value="<?= e($edit['address_line2'] ?? '') ?>"></div>
              <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" required list="cityList2" value="<?= e($edit['city'] ?? '') ?>"><datalist id="cityList2"><?php foreach (shipping_known_cities() as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
              <div class="col-md-4"><label class="form-label">Province</label><select class="form-select" name="province"><option value="">Select</option><?php foreach (shipping_provinces() as $p): ?><option<?= ($edit['province'] ?? '') === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-4"><label class="form-label">Postal code</label><input class="form-control" name="postal_code" value="<?= e($edit['postal_code'] ?? '') ?>"></div>
              <div class="col-12"><button class="btn btn-eb btn-primary-eb">Save address</button></div>
            </form>
          </div>

        <?php elseif ($sub === 'profile'): ?>
          <form method="post" class="card-eb row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control<?= $inv('name') ?>" name="name" required value="<?= e($customer['name']) ?>"><?= $err('name') ?></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control<?= $inv('phone') ?>" name="phone" value="<?= e($customer['phone']) ?>"><?= $err('phone') ?></div>
            <div class="col-12"><label class="form-label">Email</label><input class="form-control" value="<?= e($customer['email']) ?>" disabled><div class="form-text">Contact us to change your email address.</div></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="marketing" value="1"<?= $customer['marketing_opt_in'] ? ' checked' : '' ?>> <span class="form-check-label">Email me about new collections</span></label></div>
            <div class="col-12"><hr><h2 class="h6">Change password</h2></div>
            <div class="col-md-6"><label class="form-label">Current password</label><input type="password" class="form-control<?= $inv('current_password') ?>" name="current_password" autocomplete="current-password"><?= $err('current_password') ?></div>
            <div class="col-md-6"><label class="form-label">New password</label><input type="password" class="form-control<?= $inv('new_password') ?>" name="new_password" autocomplete="new-password"><?= $err('new_password') ?></div>
            <div class="col-12"><button class="btn btn-eb btn-primary-eb">Save changes</button></div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
