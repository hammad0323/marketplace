<?php $active = $active ?? 'dashboard'; $items = ['dashboard' => ['Overview', 'grid'], 'orders' => ['Orders', 'bag'], 'addresses' => ['Addresses', 'geo-alt'], 'profile' => ['Profile & password', 'person']]; ?>
<nav class="account-nav" aria-label="Account">
  <?php foreach ($items as $k => [$label, $icon]): ?>
    <a href="<?= e(path_url($k === 'dashboard' ? 'account' : 'account/' . $k)) ?>" class="<?= $active === $k ? 'is-active' : '' ?>"><i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if (wishlist_enabled()): ?><a href="<?= e(path_url('wishlist')) ?>"><i class="bi bi-heart"></i> Wishlist</a><?php endif; ?>
  <form method="post" action="<?= e(path_url('account/logout')) ?>"><?= csrf_field() ?><button type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
</nav>
