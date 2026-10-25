<?php
/**
 * Admin layout and form helpers.
 */

function admin_menu(): array
{
    return [
        ['Dashboard', 'admin/', 'speedometer2', 'dashboard'],
        ['Orders', 'admin/orders/', 'receipt', 'orders'],
        ['Products', 'admin/products/', 'tag', 'products'],
        ['Categories', 'admin/categories/', 'diagram-3', 'categories'],
        ['Collections', 'admin/collections/', 'collection', 'collections'],
        ['Sizes & Colours', 'admin/catalog/attributes.php', 'palette', 'attributes'],
        ['Inventory', 'admin/inventory/', 'boxes', 'inventory'],
        ['Customers', 'admin/customers/', 'people', 'customers'],
        ['Reviews', 'admin/reviews/', 'star', 'reviews'],
        ['Coupons', 'admin/coupons/', 'ticket-perforated', 'coupons'],
        ['__', 'Services'],
        ['Alteration Requests', 'admin/alterations/', 'scissors', 'alterations'],
        ['Alteration Price List', 'admin/alterations/services.php', 'tags', 'alterations'],
        ['Custom Size Studio', 'admin/studio/', 'rulers', 'custom_studio'],
        ['__', 'Storefront'],
        ['Homepage Builder', 'admin/homepage/', 'layout-text-window', 'homepage'],
        ['Hero Slides', 'admin/homepage/slides.php', 'images', 'homepage'],
        ['Navigation', 'admin/settings/navigation.php', 'menu-button-wide', 'navigation'],
        ['Content Pages', 'admin/content/', 'file-earmark-text', 'content'],
        ['Newsletter & Messages', 'admin/newsletter/', 'envelope', 'newsletter'],
        ['__', 'Configuration'],
        ['Delivery & Shipping', 'admin/shipping/', 'truck', 'shipping'],
        ['Payment Methods', 'admin/payments/', 'credit-card', 'payments'],
        ['SEO & Redirects', 'admin/seo/', 'search', 'seo'],
        ['Store Settings', 'admin/settings/', 'gear', 'settings'],
        ['Theme & Design', 'admin/settings/theme.php', 'brush', 'settings'],
        ['Admin Users', 'admin/users/', 'person-badge', 'admins'],
        ['Audit Log', 'admin/audit.php', 'clock-history', 'audit'],
    ];
}

function admin_header(string $title, array $opts = []): void
{
    $admin = current_admin();
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    $pending = $admin && admin_can('orders') ? (int) db_val("SELECT COUNT(*) FROM orders WHERE status = 'pending'") : 0;
    $newAlt = $admin && admin_can('alterations') ? (int) db_val("SELECT COUNT(*) FROM alteration_requests WHERE status = 'new'") : 0;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(store_name()) ?> Admin</title>
<link rel="icon" href="<?= e(img_url(setting('favicon', 'assets/images/brand/favicon.png'))) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<?php if (!empty($opts['editor'])): ?><link rel="stylesheet" href="<?= e(asset('vendor/summernote/summernote-lite.min.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="a-body<?= empty($admin) ? ' a-guest' : '' ?>">
<?php if ($admin): ?>
<aside class="a-sidebar" id="aSidebar">
    <a class="a-brand" href="<?= e(url('admin/')) ?>">
        <img src="<?= e(img_url(setting('logo', 'assets/images/brand/logo-round.webp'))) ?>" alt="" width="38" height="38">
        <span>Urban<small>Collections Admin</small></span>
    </a>
    <nav class="a-nav">
        <?php foreach (admin_menu() as $m):
            if ($m[0] === '__') { echo '<p class="a-nav-head">' . e($m[1]) . '</p>'; continue; }
            if (!admin_can($m[3])) continue;
            $href = url($m[1]);
            $path = parse_url($href, PHP_URL_PATH);
            $active = $path === $uri || ($path !== BASE_PATH . '/admin/' && str_ends_with($path, '/') && str_starts_with($uri, $path));
        ?>
            <a href="<?= e($href) ?>" class="<?= $active ? 'is-active' : '' ?>"><i class="bi bi-<?= e($m[2]) ?>"></i><span><?= e($m[0]) ?></span>
                <?php if ($m[3] === 'orders' && $pending): ?><em class="a-pill"><?= $pending ?></em><?php endif; ?>
                <?php if ($m[1] === 'admin/alterations/' && $newAlt): ?><em class="a-pill"><?= $newAlt ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="a-main">
    <header class="a-top">
        <button class="a-burger d-lg-none" type="button" onclick="document.body.classList.toggle('a-nav-open')" aria-label="Menu"><i class="bi bi-list"></i></button>
        <h1 class="a-title"><?= e($title) ?></h1>
        <div class="a-top-actions">
            <a class="btn btn-sm btn-outline-dark" href="<?= e(url()) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> <span class="d-none d-md-inline">View store</span></a>
            <div class="dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-md-inline"><?= e($admin['name']) ?></span></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted"><?= e($admin['role_name']) ?></span></li>
                    <li><a class="dropdown-item" href="<?= e(url('admin/account.php')) ?>">My account &amp; password</a></li>
                    <li><form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger">Sign out</button></form></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="a-content">
<?php foreach (flashes() as $f): $cls = ['error' => 'danger', 'success' => 'success', 'warning' => 'warning'][$f['type']] ?? 'info'; ?>
        <div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
<?php else: ?>
<div class="a-guest-wrap">
<?php endif;
}

function admin_footer(array $opts = []): void
{
    $admin = current_admin();
    ?>
<?php if ($admin): ?>
    </div>
</div>
<div class="a-overlay" onclick="document.body.classList.remove('a-nav-open')"></div>
<?php else: ?>
</div>
<?php endif; ?>
<script>window.UCA = <?= json_encode(['base' => rtrim(url(), '/') . '/', 'csrf' => csrf_token()], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<?php if (!empty($opts['editor'])): ?><script src="<?= e(asset('vendor/summernote/summernote-lite.min.js')) ?>"></script><?php endif; ?>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

// ---------------------------------------------------------------------
//  Form helpers (all values escaped)
// ---------------------------------------------------------------------
function a_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $k => $v) {
        if ($v === false || $v === null) continue;
        $out .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    return $out;
}

function a_input(string $name, string $label, $value = '', string $type = 'text', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>'
        . '<input class="form-control" type="' . e($type) . '" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . a_attrs($attrs) . '>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function a_textarea(string $name, string $label, $value = '', int $rows = 4, array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>'
        . '<textarea class="form-control" id="' . $id . '" name="' . e($name) . '" rows="' . $rows . '"' . a_attrs($attrs) . '>' . e($value) . '</textarea>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function a_html(string $name, string $label, $value = '', string $help = ''): string
{
    return a_textarea($name, $label, $value, 8, ['data-editor' => true], $help);
}

function a_select(string $name, string $label, array $options, $value = '', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $html = '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><select class="form-select" id="' . $id . '" name="' . e($name) . '"' . a_attrs($attrs) . '>';
    foreach ($options as $k => $v) {
        $html .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html . '</select>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function a_check(string $name, string $label, $checked, string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="form-check form-switch mb-3"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function a_color(string $name, string $label, $value, string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><div class="input-group a-color">'
        . '<input type="color" class="form-control form-control-color" value="' . e($value ?: '#000000') . '" data-color-sync="#' . $id . '">'
        . '<input class="form-control" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="#RRGGBB" pattern="#[0-9a-fA-F]{6}"></div>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

/** Image field: preview, upload, paste URL, remove. Upload input is "<name>_file". */
function a_image(string $name, string $label, ?string $value, string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $html = '<div class="mb-3 a-image-field"><label class="form-label">' . e($label) . '</label><div class="d-flex gap-3 align-items-start">';
    $html .= '<div class="a-thumb">' . ($value ? '<img src="' . e(img_sized($value, 300)) . '" alt="">' : '<i class="bi bi-image"></i>') . '</div><div class="flex-grow-1">';
    $html .= '<input class="form-control form-control-sm mb-2" type="file" name="' . e($name) . '_file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview>';
    $html .= '<input class="form-control form-control-sm" type="text" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="…or paste an image URL">';
    if ($value) {
        $html .= '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="' . e($name) . '_remove" value="1" id="' . $id . '_rm"><label class="form-check-label small" for="' . $id . '_rm">Remove image</label></div>';
    }
    $html .= ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div></div></div>';
    return $html;
}

/** Resolves an a_image() field after POST. Returns the new value (upload > removal > URL > existing). */
function a_image_value(string $name, ?string $existing): ?string
{
    $up = upload_image($name . '_file');
    if ($up) {
        if ($existing && $existing !== $up) {
            delete_upload($existing);
        }
        return $up;
    }
    if (!empty($_POST[$name . '_remove'])) {
        delete_upload($existing);
        return null;
    }
    $v = trim((string) ($_POST[$name] ?? ''));
    if ($v === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $v) || preg_match('#^[a-z0-9_\-/\.]+$#i', $v)) {
        return $v;
    }
    return $existing;
}

/**
 * Searchable multi-picker for products / collections / categories. Order of selection is kept.
 */
function a_picker(string $name, string $label, string $type, array $selected, string $help = ''): string
{
    $options = match ($type) {
        'products' => db_all("SELECT id, CONCAT(name, ' (', sku, ')') AS label, status FROM products ORDER BY name"),
        'collections' => db_all('SELECT id, name AS label, is_active AS status FROM collections ORDER BY sort_order, name'),
        'categories' => array_map(fn($c) => ['id' => $c['id'], 'label' => $c['label'], 'status' => ''], category_options(false)),
        default => [],
    };
    $selected = array_values(array_map('intval', $selected));
    $byId = array_column($options, null, 'id');
    $html = '<div class="mb-3 a-picker" data-picker data-name="' . e($name) . '[]"><label class="form-label">' . e($label) . '</label>';
    $html .= '<ul class="a-picker-chosen">';
    foreach ($selected as $id) {
        if (isset($byId[$id])) {
            $html .= '<li data-id="' . $id . '"><input type="hidden" name="' . e($name) . '[]" value="' . $id . '"><span>' . e($byId[$id]['label']) . '</span>'
                . '<button type="button" data-up title="Move up"><i class="bi bi-arrow-up"></i></button><button type="button" data-remove title="Remove"><i class="bi bi-x"></i></button></li>';
        }
    }
    $html .= '</ul><input type="search" class="form-control form-control-sm" placeholder="Search to add…" data-picker-search><div class="a-picker-results">';
    foreach ($options as $o) {
        $html .= '<button type="button" data-id="' . (int) $o['id'] . '" data-label="' . e($o['label']) . '">' . e($o['label']) . ($o['status'] === 'draft' || $o['status'] === 'inactive' ? ' <small>(' . e($o['status']) . ')</small>' : '') . '</button>';
    }
    return $html . '</div>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '<input type="hidden" name="' . e(preg_replace('/[\[\]]/', '_', $name)) . '_present" value="1"></div>';
}

function a_ids(string $name): array
{
    return array_values(array_unique(array_filter(array_map('intval', (array) ($_POST[$name] ?? [])))));
}

/** Small status pill for admin tables. */
function a_badge(string $text, string $variant = 'secondary'): string
{
    return '<span class="badge text-bg-' . e($variant) . '">' . e($text) . '</span>';
}

function a_status_toggle(bool $on): string
{
    return $on ? '<span class="a-dot is-on">Active</span>' : '<span class="a-dot">Inactive</span>';
}

/** Small POST button (delete / action) with confirmation. */
function a_post_button(string $action, array $fields, string $label, string $class = 'btn btn-sm btn-outline-danger', string $confirm = 'Are you sure?'): string
{
    $html = '<form method="post" action="' . e($action) . '" class="d-inline" data-confirm="' . e($confirm) . '">' . csrf_field();
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $html . '<button type="submit" class="' . e($class) . '">' . $label . '</button></form>';
}

function a_save_bar(string $cancelUrl = '', string $label = 'Save changes'): string
{
    return '<div class="a-savebar"><button type="submit" class="btn btn-dark px-4"><i class="bi bi-check2"></i> ' . e($label) . '</button>'
        . ($cancelUrl ? ' <a class="btn btn-link text-muted" href="' . e(url($cancelUrl)) . '">Cancel</a>' : '') . '</div>';
}

/** Stores uploaded-image errors as flash messages instead of failing the whole save. */
function a_try(callable $fn, $fallback = null)
{
    try {
        return $fn();
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        return $fallback;
    }
}
