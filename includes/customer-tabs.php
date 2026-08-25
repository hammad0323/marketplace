<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
$tabs = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/customer/index.php', 'icon' => 'bi-speedometer2'],
    'trips' => ['label' => 'My Trips', 'href' => '/customer/trips.php', 'icon' => 'bi-map'],
    'bookings' => ['label' => 'Bookings', 'href' => '/customer/bookings.php', 'icon' => 'bi-calendar-check'],
    'orders' => ['label' => 'Orders', 'href' => '/customer/orders.php', 'icon' => 'bi-bag-check'],
    'favorites' => ['label' => 'Favorites', 'href' => '/customer/favorites.php', 'icon' => 'bi-heart'],
    'profile' => ['label' => 'Profile', 'href' => '/customer/profile.php', 'icon' => 'bi-person'],
];
?>
<div style="display:flex;gap:8px;margin-bottom:28px;overflow-x:auto;">
  <?php foreach ($tabs as $key => $tab): ?>
    <a href="<?php echo $tab['href']; ?>" class="btn-w btn-sm <?php echo ($customerActiveTab ?? '') === $key ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi <?php echo $tab['icon']; ?>"></i> <?php echo $tab['label']; ?></a>
  <?php endforeach; ?>
</div>
