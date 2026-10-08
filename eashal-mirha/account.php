<?php
require __DIR__ . '/includes/bootstrap.php';

$c = require_customer();
$tab = get('tab', 'orders');

if (is_post()) {
    require_csrf();
    if (post('form') === 'profile') {
        $name = mb_substr(post('name'), 0, 120);
        if ($name === '') {
            flash('error', 'Name cannot be empty.');
        } else {
            q('UPDATE customers SET name = ?, phone = ?, address = ?, city = ? WHERE id = ?', [$name, mb_substr(post('phone'), 0, 40), mb_substr(post('address'), 0, 255), mb_substr(post('city'), 0, 80), $c['id']]);
            flash('success', 'Your profile has been updated.');
        }
        redirect('account?tab=profile');
    }
    if (post('form') === 'password') {
        if (!password_verify((string)post('current'), $c['password'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen((string)post('new')) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } else {
            q('UPDATE customers SET password = ? WHERE id = ?', [password_hash((string)post('new'), PASSWORD_DEFAULT), $c['id']]);
            flash('success', 'Your password has been changed.');
        }
        redirect('account?tab=password');
    }
}

$orders = rows('SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) items FROM orders o WHERE customer_id = ? ORDER BY id DESC', [$c['id']]);
$seo = ['title' => 'My Account | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="page-title"><div class="container"><span class="ornament">✦</span><h1 class="section-title">Hello, <?= e(strtok($c['name'], ' ')) ?></h1></div></section>
<section class="section section--tight">
  <div class="container account-layout">
    <nav class="account-nav">
      <a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">My Orders</a>
      <a href="<?= url('wishlist') ?>">Wishlist</a>
      <a href="?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">Profile & Address</a>
      <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">Change Password</a>
      <a href="<?= url('logout') ?>">Logout</a>
    </nav>
    <div class="account-main" data-reveal>
      <?php if ($tab === 'profile'): ?>
        <form method="post" class="panel form-grid">
          <?= csrf_field() ?><input type="hidden" name="form" value="profile">
          <label>Full Name<input type="text" name="name" value="<?= e($c['name']) ?>" required></label>
          <label>Email<input type="email" value="<?= e($c['email']) ?>" disabled></label>
          <label>Phone<input type="tel" name="phone" value="<?= e($c['phone']) ?>"></label>
          <label>City<input type="text" name="city" value="<?= e($c['city']) ?>"></label>
          <label class="span-2">Address<input type="text" name="address" value="<?= e($c['address']) ?>"></label>
          <div class="span-2"><button class="btn btn-dark" type="submit">Save Changes</button></div>
        </form>
      <?php elseif ($tab === 'password'): ?>
        <form method="post" class="panel form-stack narrow-form">
          <?= csrf_field() ?><input type="hidden" name="form" value="password">
          <label>Current Password<input type="password" name="current" required></label>
          <label>New Password<input type="password" name="new" required minlength="6"></label>
          <button class="btn btn-dark" type="submit">Update Password</button>
        </form>
      <?php else: ?>
        <?php if (!$orders): ?>
          <div class="empty-state"><h3>No orders yet</h3><p>When you place an order it will appear here.</p><a class="btn btn-dark" href="<?= url('shop') ?>">Start Shopping</a></div>
        <?php else: ?>
          <div class="table-wrap"><table class="table">
            <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><strong><?= e($o['order_no']) ?></strong></td>
                  <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                  <td><?= (int)$o['items'] ?></td>
                  <td><?= money($o['total']) ?></td>
                  <td><?= status_badge($o['status']) ?></td>
                  <td><?= status_badge($o['payment_status']) ?></td>
                  <td><a class="link-underline sm" href="<?= url('order-success/' . $o['order_no'] . '?k=' . $o['access_key']) ?>">View</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
