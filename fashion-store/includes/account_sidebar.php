<?php $__accPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>
<div class="list-group">
  <a href="<?= url('account/dashboard') ?>" class="list-group-item list-group-item-action <?= str_contains($__accPath, '/account/dashboard')?'active':'' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
  <a href="<?= url('account/orders') ?>" class="list-group-item list-group-item-action <?= (str_contains($__accPath, '/account/orders') || str_contains($__accPath, '/account/order/'))?'active':'' ?>"><i class="bi bi-bag-check me-2"></i>My Orders</a>
  <a href="<?= url('account/addresses') ?>" class="list-group-item list-group-item-action <?= str_contains($__accPath, '/account/addresses')?'active':'' ?>"><i class="bi bi-geo-alt me-2"></i>Saved Addresses</a>
  <a href="<?= url('account/wishlist') ?>" class="list-group-item list-group-item-action <?= str_contains($__accPath, '/account/wishlist')?'active':'' ?>"><i class="bi bi-heart me-2"></i>Wishlist</a>
  <a href="<?= url('account/profile') ?>" class="list-group-item list-group-item-action <?= str_contains($__accPath, '/account/profile')?'active':'' ?>"><i class="bi bi-person me-2"></i>Profile</a>
  <a href="<?= url('logout') ?>" class="list-group-item list-group-item-action text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
</div>
