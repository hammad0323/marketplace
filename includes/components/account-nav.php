<?php $active = $active ?? 'dashboard'; ?>
<nav class="uc-account-nav" aria-label="Account">
    <?php foreach (['dashboard' => ['Overview', 'grid'], 'orders' => ['Orders', 'box-seam'], 'addresses' => ['Addresses', 'geo-alt'], 'profile' => ['Profile & Password', 'person'], 'wishlist' => ['Wishlist', 'heart']] as $k => [$l, $i]): ?>
        <a href="<?= e(url($k === 'wishlist' ? 'wishlist' : 'account/' . $k)) ?>"<?= $active === $k ? ' class="is-active" aria-current="page"' : '' ?>><i class="bi bi-<?= $i ?>"></i> <?= e($l) ?></a>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('account/logout')) ?>"><?= csrf_field() ?><button type="submit"><i class="bi bi-box-arrow-right"></i> Sign out</button></form>
</nav>
