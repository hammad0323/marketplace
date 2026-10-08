<?php
/**
 * Admin bootstrap + layout helpers. Every admin page starts with:
 *   require __DIR__ . '/includes/admin.php';
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (!defined('ADMIN_PUBLIC')) {
    $ADMIN = require_admin();
}

/** Which sections each role may open. */
function admin_can(string $section): bool
{
    $a = admin();
    if (!$a) return false;
    if ($a['role'] === 'super') return true;
    $deny = [
        'manager' => ['users'],
        'staff'   => ['users', 'settings', 'payments', 'seo', 'shipping', 'coupons', 'homepage', 'slides', 'pages'],
    ];
    return !in_array($section, $deny[$a['role']] ?? [], true);
}

function require_section(string $section): void
{
    if (!admin_can($section)) {
        flash('error', 'You do not have permission to open that section.');
        redirect('admin');
    }
}

function admin_menu(): array
{
    $pendingOrders = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pendingReviews = (int)val('SELECT COUNT(*) FROM reviews WHERE status = 0');
    $unread = (int)val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
    return [
        'Main' => [
            ['index', 'Dashboard', 'grid', 0],
            ['orders', 'Orders', 'bag', $pendingOrders],
            ['customers', 'Customers', 'user', 0],
        ],
        'Catalogue' => [
            ['products', 'Products', 'tag', 0],
            ['categories', 'Categories', 'layers', 0],
            ['reviews', 'Reviews', 'star', $pendingReviews],
            ['coupons', 'Coupons', 'gift', 0],
        ],
        'Storefront' => [
            ['slides', 'Hero Banners', 'image', 0],
            ['homepage', 'Homepage Sections', 'home', 0],
            ['pages', 'Pages', 'file', 0],
            ['messages', 'Messages & Subscribers', 'mail', $unread],
        ],
        'Configuration' => [
            ['settings', 'Site Settings', 'settings', 0],
            ['payments', 'Payments', 'card', 0],
            ['shipping', 'Delivery Charges', 'truck', 0],
            ['seo', 'SEO Tools', 'search', 0],
            ['users', 'Admin Users', 'shield', 0],
        ],
    ];
}

function aicon(string $name): string
{
    $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'bag' => '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'tag' => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'gift' => '<rect x="3" y="8" width="18" height="13"/><path d="M3 12h18M12 8v13"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'home' => '<path d="m3 11 9-8 9 8v10H3z"/><path d="M9 21v-7h6v7"/>',
        'file' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1A2 2 0 1 1 5 17l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3.8a2 2 0 0 1 0-4h.1A1.7 1.7 0 0 0 5.4 9a1.7 1.7 0 0 0-.3-1.8L5 7.1A2 2 0 1 1 7.8 4.3l.1.1a1.7 1.7 0 0 0 1.8.3H10a1.7 1.7 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1A2 2 0 1 1 20.7 7l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H22a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'ext' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        'logout' => '<path d="M9 21H5V3h4M16 17l5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'copy' => '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V4H3v13h5"/>',
        'print' => '<path d="M6 9V3h12v6M6 18H3v-8h18v8h-3M6 14h12v7H6z"/>',
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
    ];
    return '<svg class="ai" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($p[$name] ?? '') . '</svg>';
}

function admin_header(string $title, string $active = ''): void
{
    $a = admin();
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(setting('site_name')) ?> Admin</title>
<link rel="icon" href="<?= e(setting('favicon') ? img(setting('favicon')) : url('assets/images/favicon.svg')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body data-base="<?= e(base_path()) ?>">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= url('admin') ?>"><span class="brand__mark">EM</span><span><?= e(setting('site_name')) ?><small>Admin Panel</small></span></a>
  <nav>
    <?php foreach (admin_menu() as $group => $items): ?>
      <div class="nav-group"><?= e($group) ?></div>
      <?php foreach ($items as [$slug, $label, $ico, $badge]): if (!admin_can($slug)) continue; ?>
        <a href="<?= url('admin' . ($slug === 'index' ? '' : '/' . $slug)) ?>" class="<?= $active === $slug ? 'active' : '' ?>"><?= aicon($ico) ?><span><?= e($label) ?></span><?php if ($badge): ?><b class="nav-badge"><?= $badge ?></b><?php endif; ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="main">
  <header class="topbar">
    <button class="icon" id="sideToggle" aria-label="Menu"><?= aicon('menu') ?></button>
    <h1><?= e($title) ?></h1>
    <div class="topbar__right">
      <a class="btn-ghost" href="<?= url('') ?>" target="_blank"><?= aicon('ext') ?> <span>View Store</span></a>
      <div class="me"><span class="avatar"><?= e(strtoupper(mb_substr($a['name'], 0, 1))) ?></span><span class="me__name"><?= e($a['name']) ?><small><?= e(ucfirst($a['role'])) ?></small></span></div>
      <a class="icon" href="<?= url('admin/logout') ?>" title="Logout"><?= aicon('logout') ?></a>
    </div>
  </header>
  <div class="content">
    <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<div class="side-backdrop" id="sideBackdrop"></div>
<script src="<?= asset('js/admin.js') ?>"></script>
</body></html>
<?php
}

/* ---------- Form helpers ---------- */
function f_text(string $name, string $label, $value, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $attrs = $o['attrs'] ?? '';
    $help = !empty($o['help']) ? '<small class="help">' . $o['help'] . '</small>' : '';
    $cls = $o['class'] ?? '';
    if ($type === 'textarea') {
        $field = '<textarea name="' . e($name) . '" rows="' . ($o['rows'] ?? 4) . '" ' . $attrs . '>' . e($value) . '</textarea>';
    } else {
        $field = '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" ' . $attrs . '>';
    }
    return '<label class="field ' . e($cls) . '"><span>' . $label . '</span>' . $field . $help . '</label>';
}

function f_select(string $name, string $label, $value, array $options, array $o = []): string
{
    $h = '<label class="field ' . e($o['class'] ?? '') . '"><span>' . $label . '</span><select name="' . e($name) . '" ' . ($o['attrs'] ?? '') . '>';
    foreach ($options as $k => $v) {
        $h .= '<option value="' . e($k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    $h .= '</select>' . (!empty($o['help']) ? '<small class="help">' . $o['help'] . '</small>' : '') . '</label>';
    return $h;
}

function f_switch(string $name, string $label, $checked, string $help = ''): string
{
    return '<label class="switch"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><i></i><span>' . $label . ($help ? '<small class="help">' . $help . '</small>' : '') . '</span></label>';
}

function f_image(string $name, string $label, ?string $current, string $help = ''): string
{
    $h = '<div class="field field-image"><span>' . $label . '</span><div class="img-pick">';
    $h .= '<div class="img-pick__preview">' . ($current ? '<img src="' . e(img($current)) . '" alt="">' : '<em>No image</em>') . '</div>';
    $h .= '<div><input type="file" name="' . e($name) . '" accept="image/*" data-preview>';
    if ($current) {
        $h .= '<label class="check-sm"><input type="checkbox" name="remove_' . e($name) . '" value="1"> Remove image</label>';
    }
    $h .= ($help ? '<small class="help">' . $help . '</small>' : '') . '</div></div></div>';
    return $h;
}

/** Saves an uploaded image for a field, honours "remove", returns the new value. */
function handle_image(string $field, ?string $current, string $folder, array $ext = ['jpg', 'jpeg', 'png', 'webp', 'gif']): ?string
{
    if (!empty($_FILES[$field]['name'])) {
        $new = upload_image($_FILES[$field], $folder, $ext);
        if ($new) {
            delete_upload($current);
            return $new;
        }
        return $current;
    }
    if (post('remove_' . $field) === '1') {
        delete_upload($current);
        return null;
    }
    return $current;
}

/** Saves posted keys (whitelist) to the settings table. */
function save_posted_settings(array $keys): void
{
    $pairs = [];
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            $pairs[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : '';
        }
    }
    save_settings($pairs);
}

function seo_counter_attrs(int $max): string
{
    return 'data-count="' . $max . '"';
}
