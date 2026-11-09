<?php
/** Page chrome: responsive sidebar (desktop) / bottom navigation (mobile). */
function web_page_start(string $title, string $active, array $user, array $opts = []): void
{
    $isAdmin = $user['role_key'] === ROLE_SUPER_ADMIN;
    $adminArea = str_starts_with($active, 'admin');
    $nav = $adminArea ? [
        ['admin', 'admin/index.php', 'space_dashboard', 'Dashboard'],
        ['admin-users', 'admin/users.php', 'group', 'Users'],
        ['admin-templates', 'admin/templates.php', 'auto_awesome_mosaic', 'Templates'],
        ['admin-plans', 'admin/plans.php', 'workspace_premium', 'Plans'],
        ['admin-media', 'media.php?shared=1', 'perm_media', 'Shared media'],
        ['admin-settings', 'admin/settings.php', 'settings', 'Settings'],
    ] : [
        ['home', 'dashboard.php', 'home', 'Home'],
        ['screens', 'screens.php', 'dashboard', 'My Screens'],
        ['playlists', 'playlists.php', 'playlist_play', 'Playlists'],
        ['displays', 'displays.php', 'tv', 'Displays'],
        ['media', 'media.php', 'photo_library', 'Media'],
        ['profile', 'profile.php', 'person', 'Profile'],
    ];
    $base = web_base();
    $fonts = 'Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Amatic+SC:wght@700&family=Bebas+Neue&family=Oswald:wght@400;600;700&family=Caveat:wght@500;700&family=Pacifico&family=Playfair+Display:wght@400;700&family=Montserrat:wght@400;600;800&family=Lobster&family=Dancing+Script:wght@600&family=Anton&family=Archivo+Black&family=Permanent+Marker&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Lato:wght@400;700&family=Raleway:wght@400;700&family=Merriweather:wght@400;700&family=Nunito:wght@400;700&family=Cairo:wght@400;700&family=Noto+Sans:wght@400;700';
    $cfg = ['api' => $base . '/api/', 'web' => $base . '/web/', 'token' => $_SESSION['api_token'] ?? '', 'csrf' => web_csrf(),
        'user' => ['user_id' => (int)$user['user_id'], 'full_name' => $user['full_name'], 'role_key' => $user['role_key']]];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?> · <?= h(app_setting('app_name', 'Signal Signage')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= $fonts ?>&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
<link rel="stylesheet" href="<?= h($base) ?>/web/assets/web.css?v=3">
<script>window.SIGNAGE = <?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= h($base) ?>/web/assets/vendor/qrcode.js"></script>
<script src="<?= h($base) ?>/web/assets/app.js?v=3"></script>
<script src="<?= h($base) ?>/web/assets/render.js?v=3"></script>
<script src="<?= h($base) ?>/web/assets/player.js?v=3"></script>
<?php foreach ($opts['scripts'] ?? [] as $s): ?><script src="<?= h($base) ?>/web/assets/<?= h($s) ?>?v=3"></script><?php endforeach; ?>
</head>
<body class="<?= h($opts['body_class'] ?? '') ?>">
<?php if (empty($opts['bare'])): ?>
<aside class="sidebar">
  <a class="brand" href="<?= h(web_url($adminArea ? 'admin/index.php' : 'dashboard.php')) ?>"><span class="brand-mark ms">cast_connected</span><span><?= h(app_setting('app_name', 'Signal Signage')) ?></span></a>
  <?php if ($adminArea): ?><div class="side-label">Super admin</div><?php endif; ?>
  <nav>
    <?php foreach ($nav as [$key, $href, $icon, $label]): ?>
      <a class="<?= $active === $key ? 'on' : '' ?>" href="<?= h(web_url($href)) ?>"><span class="ms"><?= $icon ?></span><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <?php if ($isAdmin): ?>
      <a href="<?= h(web_url($adminArea ? 'dashboard.php' : 'admin/index.php')) ?>"><span class="ms"><?= $adminArea ? 'dashboard_customize' : 'admin_panel_settings' ?></span><span><?= $adminArea ? 'My workspace' : 'Admin panel' ?></span></a>
    <?php endif; ?>
    <a href="#" data-theme-toggle><span class="ms">dark_mode</span><span>Dark mode</span></a>
    <a href="<?= h(web_url('logout.php')) ?>"><span class="ms">logout</span><span>Sign out</span></a>
  </div>
</aside>
<nav class="bottom-nav">
  <?php foreach (array_slice($nav, 0, 5) as [$key, $href, $icon, $label]): ?>
    <a class="<?= $active === $key ? 'on' : '' ?>" href="<?= h(web_url($href)) ?>"><span class="ms"><?= $icon ?></span><span><?= h($label) ?></span></a>
  <?php endforeach; ?>
</nav>
<main class="main">
<?php else: ?>
<main class="main bare">
<?php endif;
}

function web_page_end(): void
{
    echo "</main>\n<div id=\"toast\"></div>\n</body>\n</html>";
}
