<?php
/**
 * Admin bootstrap: loads the app, enforces login + permissions,
 * provides the admin layout and form helpers.
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');

define('ADMIN_PUBLIC', defined('ADMIN_LOGIN_PAGE'));
if (!ADMIN_PUBLIC && !admin_session_valid()) {
    admin_logout();
    if (is_ajax()) json_error('Your admin session expired. Please log in again.', 401);
    $_SESSION['admin_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . url('admin/login.php'));
    exit;
}

function aurl(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

/** Require a permission or stop with 403. */
function admin_require(string $perm): void
{
    if (!admin_can($perm)) {
        if (is_ajax()) json_error('You do not have permission for this action.', 403);
        http_response_code(403);
        admin_header('Access denied');
        echo '<div class="alert alert-danger"><i class="bi bi-shield-lock me-2"></i>You do not have permission to access this page. Ask the store owner to update your role.</div>';
        admin_footer();
        exit;
    }
}

/** Admin menu definition: [key, label, icon, url, permission] or group headers. */
function admin_menu(): array
{
    return [
        ['dashboard', 'Dashboard', 'bi-speedometer2', 'index.php', 'dashboard'],
        ['pos', 'POS / Counter', 'bi-upc-scan', 'pos.php', 'pos'],
        '— Sales',
        ['orders', 'Orders', 'bi-bag-check', 'orders.php', 'orders'],
        ['rashan-orders', 'Monthly Rashan Orders', 'bi-basket2', 'orders.php?type=rashan', 'orders'],
        ['customers', 'Customers', 'bi-people', 'customers.php', 'customers'],
        ['reports', 'Reports', 'bi-graph-up', 'reports.php', 'reports'],
        '— Catalog',
        ['products', 'Products', 'bi-box-seam', 'products.php', 'products'],
        ['categories', 'Categories', 'bi-diagram-3', 'categories.php', 'categories'],
        ['brands', 'Brands', 'bi-award', 'brands.php', 'products'],
        ['import', 'Bulk Import / Export', 'bi-file-earmark-spreadsheet', 'import.php', 'import'],
        ['reviews', 'Reviews & Ratings', 'bi-star-half', 'reviews.php', 'reviews'],
        '— Inventory',
        ['inventory', 'Stock', 'bi-boxes', 'inventory.php', 'inventory'],
        ['purchases', 'Purchases (Stock In)', 'bi-truck', 'purchases.php', 'inventory'],
        ['suppliers', 'Suppliers', 'bi-building', 'suppliers.php', 'inventory'],
        ['movements', 'Stock History', 'bi-clock-history', 'movements.php', 'inventory'],
        '— Marketing',
        ['homepage', 'Homepage Sections', 'bi-layout-text-window', 'homepage.php', 'content'],
        ['banners', 'Banners', 'bi-images', 'banners.php', 'content'],
        ['featured', 'Featured Products', 'bi-star', 'featured.php', 'products'],
        ['promotions', 'Promotions', 'bi-megaphone', 'promotions.php', 'content'],
        ['coupons', 'Coupons & Discounts', 'bi-ticket-perforated', 'coupons.php', 'coupons'],
        ['testimonials', 'Testimonials', 'bi-chat-quote', 'testimonials.php', 'content'],
        ['newsletter', 'Newsletter', 'bi-envelope-paper', 'newsletter.php', 'content'],
        '— Store Setup',
        ['delivery', 'Delivery Settings', 'bi-geo-alt', 'delivery.php', 'delivery'],
        ['pickup', 'Pickup Settings', 'bi-shop', 'pickup.php', 'delivery'],
        ['payments', 'Payment Methods', 'bi-credit-card', 'payments.php', 'delivery'],
        ['pages', 'Content Pages', 'bi-file-earmark-text', 'pages.php', 'content'],
        ['seo', 'SEO Management', 'bi-search', 'seo.php', 'seo'],
        ['messages', 'Contact Messages', 'bi-inbox', 'messages.php', 'customers'],
        '— System',
        ['admins', 'Admin Users & Roles', 'bi-person-badge', 'admins.php', 'admins'],
        ['settings', 'Website Settings', 'bi-gear', 'settings.php', 'settings'],
    ];
}

function admin_header(string $title, string $active = ''): void
{
    $a = current_admin();
    $unread = $a ? (int) db_val('SELECT COUNT(*) FROM notifications WHERE is_read = 0') : 0;
    $pendingOrders = $a ? (int) db_val("SELECT COUNT(*) FROM orders WHERE status = 'pending'") : 0;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Fahim Store Admin</title>
<link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= url('admin/assets/admin.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/admin.css') ?>">
<script>window.FSA = {base: <?= json_encode(base_path()) ?>, admin: <?= json_encode(aurl('')) ?>, csrf: <?= json_encode(csrf_token()) ?>, currency: <?= json_encode(setting('currency_symbol', 'Rs.')) ?>};</script>
</head>
<body class="admin <?= ADMIN_PUBLIC ? 'admin-public' : '' ?>">
<?php if (!ADMIN_PUBLIC && $a): ?>
<aside class="a-sidebar" id="aSidebar">
  <a href="<?= aurl('index.php') ?>" class="a-brand"><img src="<?= url('assets/images/logo-white.svg') ?>" alt="Fahim Store" height="40"></a>
  <nav class="a-nav">
    <?php foreach (admin_menu() as $m):
        if (is_string($m)) { echo '<div class="a-nav-head">' . e(ltrim($m, '— ')) . '</div>'; continue; }
        [$key, $label, $icon, $href, $perm] = $m;
        if (!admin_can($perm)) continue; ?>
      <a href="<?= aurl($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span>
        <?php if ($key === 'orders' && $pendingOrders): ?><b class="a-pill"><?= $pendingOrders ?></b><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="a-main">
  <header class="a-topbar">
    <button class="btn btn-light d-lg-none" type="button" onclick="document.body.classList.toggle('a-nav-open')" aria-label="Menu"><i class="bi bi-list"></i></button>
    <h1 class="a-title"><?= e($title) ?></h1>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="<?= url('') ?>" target="_blank" class="btn btn-light btn-sm d-none d-md-inline-flex" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View store</a>
      <div class="dropdown">
        <button class="btn btn-light position-relative" data-bs-toggle="dropdown" id="notifBtn" aria-label="Notifications"><i class="bi bi-bell"></i><span class="a-badge js-notif-count<?= $unread ? '' : ' d-none' ?>"><?= $unread ?></span></button>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width:340px"><div class="p-2 border-bottom d-flex justify-content-between"><strong>Notifications</strong><a href="<?= aurl('notifications.php') ?>" class="small">View all</a></div><div class="js-notif-list" style="max-height:360px;overflow:auto"><div class="p-3 small text-muted">Loading…</div></div></div>
      </div>
      <div class="dropdown">
        <button class="btn btn-light" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><span class="d-none d-md-inline"><?= e($a['name']) ?></span></button>
        <ul class="dropdown-menu dropdown-menu-end"><li><span class="dropdown-item-text small text-muted"><?= e($a['role_name']) ?></span></li><li><a class="dropdown-item" href="<?= aurl('profile.php') ?>"><i class="bi bi-key me-2"></i>My password</a></li><li><a class="dropdown-item text-danger" href="<?= aurl('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li></ul>
      </div>
    </div>
  </header>
  <div class="a-content">
  <?= render_flashes() ?>
<?php endif;
}

function admin_footer(array $scripts = []): void
{
    if (!ADMIN_PUBLIC) echo '</div></div><div class="a-backdrop" onclick="document.body.classList.remove(\'a-nav-open\')"></div>';
    ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastBox"></div>
<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('admin/assets/admin.js') ?>?v=<?= @filemtime(__DIR__ . '/../assets/admin.js') ?>"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body></html>
<?php
}

/* ------------------------------------------------------------------
 * Form helpers
 * ------------------------------------------------------------------ */
function af_attrs(array $a): string
{
    $s = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) continue;
        $s .= ' ' . $k . ($v === true ? '' : '="' . e($v) . '"');
    }
    return $s;
}

function af_text(string $name, string $label, $value = '', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $type = $attrs['type'] ?? 'text';
    unset($attrs['type']);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><input type="' . e($type) . '" class="form-control" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . af_attrs($attrs) . '>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function af_textarea(string $name, string $label, $value = '', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><textarea class="form-control" id="' . $id . '" name="' . e($name) . '"' . af_attrs($attrs + ['rows' => 3]) . '>' . e($value) . '</textarea>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function af_select(string $name, string $label, array $options, $value = '', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $h = '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><select class="form-select" id="' . $id . '" name="' . e($name) . '"' . af_attrs($attrs) . '>';
    foreach ($options as $k => $v) {
        $sel = is_array($value) ? in_array((string) $k, array_map('strval', $value), true) : ((string) $k === (string) $value);
        $h .= '<option value="' . e($k) . '"' . ($sel ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $h . '</select>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function af_check(string $name, string $label, $checked = false, string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="form-check form-switch mb-2"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label>' . ($help ? '<div class="form-text mt-0">' . $help . '</div>' : '') . '</div>';
}

function af_image(string $name, string $label, ?string $current, string $help = ''): string
{
    $id = 'f_' . $name;
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>'
        . '<div class="d-flex gap-3 align-items-center"><img src="' . e(upload_url($current)) . '" class="a-img-preview js-preview-' . $id . '" alt="">'
        . '<div class="flex-grow-1"><input type="file" class="form-control js-img-input" data-preview=".js-preview-' . $id . '" id="' . $id . '" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp,image/gif">'
        . ($current ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="' . e($name) . '_remove" value="1" id="' . $id . '_rm"><label class="form-check-label small" for="' . $id . '_rm">Remove current image</label></div>' : '')
        . '<div class="form-text">' . ($help ?: 'JPG, PNG or WebP up to ' . MAX_UPLOAD_MB . ' MB. Saved as optimised WebP.') . '</div></div></div></div>';
}

/** Handle an af_image() field on POST. Returns the path to store. */
function admin_save_image(string $field, string $folder, ?string $current, int $maxW = 1600): ?string
{
    $err = null;
    $new = upload_field($field, $folder, $maxW, $err);
    if ($err) {
        flash('danger', $label = 'Image not saved: ' . $err);
        return $current;
    }
    if ($new) {
        if ($current) upload_delete($current);
        return $new;
    }
    if (input($field . '_remove') === '1') {
        if ($current) upload_delete($current);
        return null;
    }
    return $current;
}

/** SEO fields block (used by products, categories, brands, pages, promotions). */
function af_seo_fields(array $seo, string $slugPrefix = '', string $slug = ''): string
{
    ob_start(); ?>
<div class="row">
  <div class="col-md-8"><?= af_text('seo[seo_title]', 'SEO title', $seo['seo_title'], ['maxlength' => 190, 'class' => 'form-control js-count', 'data-max' => 60], 'Recommended up to 60 characters. Leave empty to use the name.') ?></div>
  <div class="col-md-4"><?= af_text('seo[focus_keyword]', 'Focus keyword', $seo['focus_keyword'], ['maxlength' => 120]) ?></div>
  <div class="col-12"><?= af_textarea('seo[meta_description]', 'Meta description', $seo['meta_description'], ['maxlength' => 320, 'rows' => 2, 'class' => 'form-control js-count', 'data-max' => 160], 'Recommended 120–160 characters.') ?></div>
  <div class="col-md-6"><?= af_text('seo[secondary_keywords]', 'Secondary keywords', $seo['secondary_keywords'], ['maxlength' => 255], 'Comma separated') ?></div>
  <div class="col-md-6"><?= af_text('seo[canonical_url]', 'Canonical URL (optional override)', $seo['canonical_url'], ['maxlength' => 255, 'placeholder' => 'https://…']) ?></div>
  <div class="col-md-4"><?= af_select('seo[meta_robots]', 'Meta robots', ['' => 'Default (index, follow)', 'index,follow' => 'index, follow', 'noindex,follow' => 'noindex, follow', 'index,nofollow' => 'index, nofollow', 'noindex,nofollow' => 'noindex, nofollow'], $seo['meta_robots']) ?></div>
  <div class="col-md-4"><?= af_text('seo[breadcrumb_title]', 'Breadcrumb title', $seo['breadcrumb_title'], ['maxlength' => 120]) ?></div>
  <div class="col-md-4"><?= af_text('seo[image_alt]', 'Main image ALT text', $seo['image_alt'], ['maxlength' => 190]) ?></div>
  <div class="col-md-6"><?= af_text('seo[og_title]', 'Open Graph title', $seo['og_title'], ['maxlength' => 190]) ?></div>
  <div class="col-md-6"><?= af_text('seo[og_image]', 'Open Graph image path/URL', $seo['og_image'], ['maxlength' => 255, 'placeholder' => 'Defaults to the main image']) ?></div>
  <div class="col-12"><?= af_textarea('seo[og_description]', 'Open Graph description', $seo['og_description'], ['maxlength' => 320, 'rows' => 2]) ?></div>
  <div class="col-md-4"><?= af_select('seo[twitter_card]', 'Twitter card', ['' => 'summary_large_image (default)', 'summary' => 'summary', 'summary_large_image' => 'summary_large_image'], $seo['twitter_card']) ?></div>
  <div class="col-md-8"><?= af_text('seo[twitter_title]', 'Twitter title', $seo['twitter_title'], ['maxlength' => 190]) ?></div>
  <div class="col-12"><?= af_textarea('seo[twitter_description]', 'Twitter description', $seo['twitter_description'], ['maxlength' => 320, 'rows' => 2]) ?></div>
  <div class="col-12"><?= af_textarea('seo[schema_json]', 'Extra structured data (JSON-LD, optional)', $seo['schema_json'], ['rows' => 3, 'class' => 'form-control font-monospace small', 'placeholder' => '{"@context":"https://schema.org", ...}'], 'Product/Breadcrumb schema is generated automatically. Invalid JSON is ignored.') ?></div>
</div>
<div class="a-serp mt-2"><div class="small text-muted">Google preview</div><div class="a-serp-title js-serp-title"><?= e($seo['seo_title'] ?: 'Title') ?></div><div class="a-serp-url"><?= e(abs_url($slugPrefix . $slug . ($slug ? '/' : ''))) ?></div><div class="a-serp-desc js-serp-desc"><?= e($seo['meta_description'] ?: 'Meta description…') ?></div></div>
<?php
    return ob_get_clean();
}

/** Admin pagination using the same helper. */
function admin_pagination(array $p): string
{
    return render_pagination($p);
}

/** Table sort/filter helpers */
function a_sel(string $name, array $opts, $val, string $all = 'All'): string
{
    $h = '<select name="' . e($name) . '" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">' . e($all) . '</option>';
    foreach ($opts as $k => $v) $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $val ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h . '</select>';
}

/** Log admin-friendly CSV download. */
function csv_download(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel (Urdu)
    fputcsv($out, $header);
    foreach ($rows as $r) {
        // neutralise spreadsheet formula injection
        fputcsv($out, array_map(function ($v) { $v = (string) $v; return (preg_match('/^[=+\-@]/', $v) && !is_numeric($v)) ? "'" . $v : $v; }, $r));
    }
    fclose($out);
    exit;
}

/** Clear caches that depend on catalog data. */
function catalog_changed(): void
{
    cache_clear('menu_tree');
    cache_clear('search_vocab');
}
