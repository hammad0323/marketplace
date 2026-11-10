<?php
/** Account sidebar. Expects $active. */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$links = ['dashboard' => ['bi-speedometer2', 'Dashboard', ''], 'orders' => ['bi-bag', 'My Orders', 'orders/'], 'rashan-lists' => ['bi-basket2', 'Rashan Lists', 'rashan-lists/'],
    'wishlist' => ['bi-heart', 'Wishlist', 'wishlist/'], 'addresses' => ['bi-geo-alt', 'Addresses', 'addresses/'], 'profile' => ['bi-person', 'Profile', 'profile/'], 'password' => ['bi-key', 'Change Password', 'password/']];
?>
<div class="box p-2 account-nav">
  <div class="px-3 py-2 border-bottom mb-2"><small class="text-muted">Hello,</small><div class="fw-bold"><?= e(current_customer()['name']) ?></div></div>
  <div class="list-group list-group-flush">
    <?php foreach ($links as $k => [$icon, $label, $path]): ?>
    <a href="<?= url('my-account/' . $path) ?>" class="list-group-item list-group-item-action<?= $active === $k ? ' active' : '' ?>"><i class="bi <?= $icon ?> me-2"></i><?= $label ?></a>
    <?php endforeach; ?>
    <a href="<?= url('logout/') ?>" class="list-group-item list-group-item-action text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
  </div>
</div>
