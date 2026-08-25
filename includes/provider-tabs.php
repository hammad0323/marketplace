<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
$providerIsStore = false;
if (!empty($provider['category_id'])) {
    $providerIsStore = (db_select_one($conn, 'SELECT listing_type FROM categories WHERE id = ?', [(int) $provider['category_id']])['listing_type'] ?? 'service') === 'product';
}

$tabs = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/provider/index.php', 'icon' => 'bi-speedometer2'],
    'services' => ['label' => $providerIsStore ? 'Products' : 'Services', 'href' => '/provider/services.php', 'icon' => 'bi-list-ul'],
];
if ($providerIsStore) {
    $tabs['orders'] = ['label' => 'Orders', 'href' => '/provider/orders.php', 'icon' => 'bi-bag-check'];
} else {
    $tabs['bookings'] = ['label' => 'Bookings', 'href' => '/provider/bookings.php', 'icon' => 'bi-calendar-check'];
    $tabs['availability'] = ['label' => 'Availability', 'href' => '/provider/availability.php', 'icon' => 'bi-calendar-week'];
}
$tabs['analytics'] = ['label' => 'Analytics', 'href' => '/provider/analytics.php', 'icon' => 'bi-graph-up'];
$tabs['reviews'] = ['label' => 'Reviews', 'href' => '/provider/reviews.php', 'icon' => 'bi-star'];
$tabs['membership'] = ['label' => 'Membership', 'href' => '/provider/membership.php', 'icon' => 'bi-award'];
$tabs['profile'] = ['label' => 'Profile', 'href' => '/provider/profile.php', 'icon' => 'bi-person'];
?>
<div style="display:flex;gap:8px;margin-bottom:28px;overflow-x:auto;">
  <?php foreach ($tabs as $key => $tab): ?>
    <a href="<?php echo $tab['href']; ?>" class="btn-w btn-sm <?php echo ($providerActiveTab ?? '') === $key ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi <?php echo $tab['icon']; ?>"></i> <?php echo $tab['label']; ?></a>
  <?php endforeach; ?>
</div>
