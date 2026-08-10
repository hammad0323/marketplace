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
    <a href="/index.php" class="brand">
      <span class="brand-mark"><i class="bi bi-compass"></i></span>
      <?php echo e($siteName); ?>
    </a>

    <nav>
      <ul class="nav-links">
        <li><a href="/index.php#cities" class="<?php echo nav_active('/index.php', $currentPath); ?>">Explore</a></li>
        <li><a href="/index.php#categories">Categories</a></li>
        <li><a href="/pages/page.php?slug=about">About</a></li>
      </ul>
    </nav>

    <div class="navbar-actions">
      <?php if ($loggedInUser): ?>
        <div style="position:relative;">
          <div class="user-chip">
            <span class="avatar-dot"><?php echo e(strtoupper(substr($loggedInUser['name'], 0, 1))); ?></span>
            <?php echo e(explode(' ', $loggedInUser['name'])[0]); ?>
            <i class="bi bi-chevron-down" style="font-size:11px;"></i>
          </div>
          <div class="user-menu">
            <?php if ($loggedInUser['role_slug'] === 'admin'): ?>
              <a href="/admin/index.php"><i class="bi bi-speedometer2"></i> Admin Dashboard</a>
            <?php elseif ($loggedInUser['role_slug'] === 'provider'): ?>
              <a href="/provider/index.php"><i class="bi bi-speedometer2"></i> Provider Dashboard</a>
            <?php else: ?>
              <a href="/customer/index.php"><i class="bi bi-speedometer2"></i> My Dashboard</a>
            <?php endif; ?>
            <a href="/<?php echo e($loggedInUser['role_slug']); ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="/provider/register.php" class="btn-w btn-ghost btn-sm">List your business</a>
        <a href="/customer/login.php" class="btn-w btn-outline btn-sm">Log in</a>
        <a href="/customer/register.php" class="btn-w btn-primary btn-sm">Sign up</a>
      <?php endif; ?>
      <button class="mobile-toggle" aria-label="Menu"><i class="bi bi-list" style="font-size:20px;"></i></button>
    </div>
  </div>
</header>
