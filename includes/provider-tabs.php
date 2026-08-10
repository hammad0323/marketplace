<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
$tabs = [
    'dashboard' => ['label' => 'Dashboard', 'href' => '/provider/index.php', 'icon' => 'bi-speedometer2'],
    'services' => ['label' => 'Services', 'href' => '/provider/services.php', 'icon' => 'bi-list-ul'],
    'bookings' => ['label' => 'Bookings', 'href' => '/provider/bookings.php', 'icon' => 'bi-calendar-check'],
    'availability' => ['label' => 'Availability', 'href' => '/provider/availability.php', 'icon' => 'bi-calendar-week'],
    'analytics' => ['label' => 'Analytics', 'href' => '/provider/analytics.php', 'icon' => 'bi-graph-up'],
    'reviews' => ['label' => 'Reviews', 'href' => '/provider/reviews.php', 'icon' => 'bi-star'],
    'profile' => ['label' => 'Profile', 'href' => '/provider/profile.php', 'icon' => 'bi-person'],
];
?>
<div style="display:flex;gap:8px;margin-bottom:28px;overflow-x:auto;">
  <?php foreach ($tabs as $key => $tab): ?>
    <a href="<?php echo $tab['href']; ?>" class="btn-w btn-sm <?php echo ($providerActiveTab ?? '') === $key ? 'btn-primary' : 'btn-outline'; ?>"><i class="bi <?php echo $tab['icon']; ?>"></i> <?php echo $tab['label']; ?></a>
  <?php endforeach; ?>
</div>
