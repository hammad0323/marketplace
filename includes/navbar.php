<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

$siteName = $siteName ?? get_setting($conn, 'site_name', APP_NAME);
$loggedInUser = is_logged_in() ? current_user($conn) : null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function nav_active($path, $current)
{
    return $path === $current ? ' active' : '';
}
?>
<header class="navbar-w">
  <div class="container-xl navbar-inner">
    <a href="<?php echo url('/index.php'); ?>" class="brand">
      <span class="brand-mark"><i class="bi bi-compass"></i></span>
      <?php echo e($siteName); ?>
    </a>

    <nav>
      <ul class="nav-links">
        <li><a href="<?php echo url('/index.php'); ?>#cities" class="<?php echo nav_active('/index.php', $currentPath); ?>">Explore</a></li>
        <li><a href="<?php echo url('/index.php'); ?>#categories">Categories</a></li>
        <li><a href="<?php echo url('/pages/trip-planner.php'); ?>">Trip Planner</a></li>
        <li><a href="<?php echo url('/pages/blog.php'); ?>">Blog</a></li>
        <li><a href="<?php echo url('/pages/page.php'); ?>?slug=about">About</a></li>
      </ul>
    </nav>

    <div class="navbar-actions">
      <?php if ($loggedInUser): ?>
        <?php if ($loggedInUser['role_slug'] !== 'admin'): ?>
          <a href="/<?php echo e($loggedInUser['role_slug']); ?>/messages.php" class="btn-w btn-ghost btn-sm" style="padding:9px;" title="Messages"><i class="bi bi-chat-dots" style="font-size:16px;"></i></a>
        <?php endif; ?>
        <div style="position:relative;">
          <button class="btn-w btn-ghost btn-sm" id="notif-bell" style="padding:9px;position:relative;" title="Notifications">
            <i class="bi bi-bell" style="font-size:16px;"></i>
            <span id="notif-count" style="display:none;position:absolute;top:2px;right:2px;background:#EF4444;color:#fff;font-size:10px;font-weight:700;border-radius:999px;min-width:16px;height:16px;line-height:16px;text-align:center;padding:0 3px;"></span>
          </button>
          <div class="user-menu" id="notif-panel" style="width:320px;right:0;"></div>
        </div>
        <div style="position:relative;">
          <div class="user-chip">
            <span class="avatar-dot"><?php echo e(strtoupper(substr($loggedInUser['name'], 0, 1))); ?></span>
            <?php echo e(explode(' ', $loggedInUser['name'])[0]); ?>
            <i class="bi bi-chevron-down" style="font-size:11px;"></i>
          </div>
          <div class="user-menu">
            <?php if ($loggedInUser['role_slug'] === 'admin'): ?>
              <a href="<?php echo url('/admin/index.php'); ?>"><i class="bi bi-speedometer2"></i> Admin Dashboard</a>
            <?php elseif ($loggedInUser['role_slug'] === 'provider'): ?>
              <a href="<?php echo url('/provider/index.php'); ?>"><i class="bi bi-speedometer2"></i> Provider Dashboard</a>
              <a href="<?php echo url('/provider/profile.php'); ?>"><i class="bi bi-person"></i> Business Profile</a>
            <?php else: ?>
              <a href="<?php echo url('/customer/index.php'); ?>"><i class="bi bi-speedometer2"></i> My Dashboard</a>
              <a href="<?php echo url('/customer/favorites.php'); ?>"><i class="bi bi-heart"></i> Favorites</a>
              <a href="<?php echo url('/customer/profile.php'); ?>"><i class="bi bi-person"></i> Profile</a>
            <?php endif; ?>
            <a href="/<?php echo e($loggedInUser['role_slug']); ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo url('/provider/register.php'); ?>" class="btn-w btn-ghost btn-sm">List your business</a>
        <a href="<?php echo url('/customer/login.php'); ?>" class="btn-w btn-outline btn-sm">Log in</a>
        <a href="<?php echo url('/customer/register.php'); ?>" class="btn-w btn-primary btn-sm">Sign up</a>
      <?php endif; ?>
      <button class="mobile-toggle" aria-label="Menu"><i class="bi bi-list" style="font-size:20px;"></i></button>
    </div>
  </div>
</header>
