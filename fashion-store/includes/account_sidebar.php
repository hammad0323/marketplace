<?php $__accPage = basename($_SERVER['SCRIPT_NAME']); ?>
<div class="list-group">
  <a href="dashboard.php" class="list-group-item list-group-item-action <?= $__accPage==='dashboard.php'?'active':'' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
  <a href="orders.php" class="list-group-item list-group-item-action <?= in_array($__accPage,['orders.php','order_detail.php'])?'active':'' ?>"><i class="bi bi-bag-check me-2"></i>My Orders</a>
  <a href="addresses.php" class="list-group-item list-group-item-action <?= $__accPage==='addresses.php'?'active':'' ?>"><i class="bi bi-geo-alt me-2"></i>Saved Addresses</a>
  <a href="wishlist.php" class="list-group-item list-group-item-action <?= $__accPage==='wishlist.php'?'active':'' ?>"><i class="bi bi-heart me-2"></i>Wishlist</a>
  <a href="profile.php" class="list-group-item list-group-item-action <?= $__accPage==='profile.php'?'active':'' ?>"><i class="bi bi-person me-2"></i>Profile</a>
  <a href="<?= BASE_URL ?>/logout.php" class="list-group-item list-group-item-action text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
</div>
