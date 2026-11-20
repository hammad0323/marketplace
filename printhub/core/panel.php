<?php
/** Shared layout and form helpers for the Super Admin panel and the shop owner web dashboard. */

function panel_header(string $title, array $o): void
{
    // $o: area (admin|owner), menu [[label, path, icon, badge]], user_name, user_sub, notif_url, unread
    $current = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · <?= e(platform_name()) ?></title>
<link rel="icon" href="<?= e(path('assets/img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
</head>
<body class="p-body p-<?= e($o['area']) ?>">
<aside class="p-side" id="pSide">
    <a class="p-brand" href="<?= e(path($o['area'] . '/')) ?>"><span class="p-logo"><i class="bi bi-printer-fill"></i></span><span><?= e(platform_name()) ?><small><?= $o['area'] === 'admin' ? 'Super Admin' : e($o['shop_name'] ?? 'Shop') ?></small></span></a>
    <nav class="p-nav">
        <?php foreach ($o['menu'] as $m): if ($m[0] === '—'): ?><div class="p-nav-label"><?= e($m[1]) ?></div><?php continue; endif;
            $active = rtrim($current, '/') === rtrim(path($m[1]), '/') || (strlen($m[1]) > strlen($o['area']) + 2 && str_starts_with($current, path(preg_replace('/\.php$/', '', $m[1])))); ?>
            <a class="<?= $active ? 'is-active' : '' ?>" href="<?= e(path($m[1])) ?>"><i class="bi bi-<?= e($m[2]) ?>"></i><span><?= e($m[0]) ?></span><?php if (!empty($m[3])): ?><em><?= (int) $m[3] ?></em><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="p-main">
    <header class="p-top">
        <button class="p-burger d-lg-none" type="button" onclick="document.body.classList.toggle('p-side-open')" aria-label="Menu"><i class="bi bi-list"></i></button>
        <h1 class="p-title"><?= e($title) ?></h1>
        <div class="p-top-right">
            <?php if (!empty($o['extra_top'])) echo $o['extra_top']; ?>
            <a class="p-bell" href="<?= e(path($o['notif_url'])) ?>" aria-label="Notifications" data-notif-bell><i class="bi bi-bell"></i><?php if (!empty($o['unread'])): ?><em data-notif-count><?= (int) $o['unread'] ?></em><?php endif; ?></a>
            <div class="dropdown">
                <button class="p-user dropdown-toggle" data-bs-toggle="dropdown"><span class="p-avatar"><?= e(mb_strtoupper(mb_substr($o['user_name'], 0, 1))) ?></span><span class="d-none d-md-inline"><?= e($o['user_name']) ?></span></button>
                <ul class="dropdown-menu dropdown-menu-end"><li class="dropdown-header small"><?= e($o['user_sub']) ?></li>
                    <li><a class="dropdown-item" href="<?= e(path($o['area'] . '/password.php')) ?>"><i class="bi bi-key me-2"></i>Change password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="post" action="<?= e(path($o['area'] . '/logout.php')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li></ul>
            </div>
        </div>
    </header>
    <?php if (!empty($o['banner'])) echo $o['banner']; ?>
    <main class="p-content">
    <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> alert-dismissible fade show"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach;
}

function panel_footer(array $scripts = []): void
{
    ?></main></div>
<div class="p-overlay" onclick="document.body.classList.remove('p-side-open')"></div>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>"></script><?php endforeach; ?>
<script src="<?= e(asset('js/panel.js')) ?>"></script>
</body></html><?php
}

/** Minimal page for login screens. */
function panel_auth_header(string $title, string $area): void
{
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($title) ?> · <?= e(platform_name()) ?></title><link rel="icon" href="<?= e(path('assets/img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>"><link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>"></head><body class="p-auth p-<?= e($area) ?>"><div class="p-auth-card">
<div class="p-brand mb-4"><span class="p-logo"><i class="bi bi-printer-fill"></i></span><span><?= e(platform_name()) ?><small><?= $area === 'admin' ? 'Super Admin' : 'Shop Owner' ?></small></span></div>
<?php foreach (flashes() as [$type, $msg]): ?><div class="alert alert-<?= $type === 'error' ? 'danger' : e($type) ?> py-2 small"><?= e($msg) ?></div><?php endforeach;
}

function panel_auth_footer(): void
{
    echo '</div></body></html>';
}

// ---------------------------------------------------------------- form helpers
function f_input(string $name, string $label, $value = '', string $type = 'text', array $attrs = [], string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= $v === true ? ' ' . $k : ' ' . $k . '="' . e($v) . '"';
    }
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><input class="form-control" type="' . e($type) . '" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . $a . '>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function f_textarea(string $name, string $label, $value = '', int $rows = 3, string $help = '', array $attrs = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= ' ' . $k . '="' . e($v) . '"';
    }
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><textarea class="form-control" id="' . $id . '" name="' . e($name) . '" rows="' . $rows . '"' . $a . '>' . e($value) . '</textarea>'
        . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function f_select(string $name, string $label, array $options, $value = '', string $help = '', array $attrs = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= $v === true ? ' ' . $k : ' ' . $k . '="' . e($v) . '"';
    }
    $html = '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><select class="form-select" id="' . $id . '" name="' . e($name) . '"' . $a . '>';
    foreach ($options as $k => $l) {
        $html .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $html . '</select>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function f_switch(string $name, string $label, $checked, string $help = ''): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="form-check form-switch mb-3"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label>' . ($help ? '<div class="form-text">' . $help . '</div>' : '') . '</div>';
}

function stat_card(string $label, string $value, string $icon, string $tone = 'teal', string $sub = '', ?string $href = null): string
{
    $inner = '<div class="p-stat p-stat-' . e($tone) . '"><span class="p-stat-icon"><i class="bi bi-' . e($icon) . '"></i></span><div><div class="p-stat-label">' . e($label) . '</div><div class="p-stat-value">' . e($value) . '</div>'
        . ($sub ? '<div class="p-stat-sub">' . $sub . '</div>' : '') . '</div></div>';
    return $href ? '<a class="p-stat-link" href="' . e(path($href)) . '">' . $inner . '</a>' : $inner;
}

function sub_badge(string $state): string
{
    $map = ['active' => ['success', 'Active'], 'grace' => ['warning', 'Grace period'], 'expired' => ['danger', 'Expired'], 'suspended' => ['secondary', 'Suspended'], 'none' => ['light', 'No subscription']];
    [$tone, $label] = $map[$state] ?? ['light', $state];
    return '<span class="badge rounded-pill text-bg-' . $tone . '">' . e($label) . '</span>';
}

function order_badge(string $status): string
{
    $map = ['submitted' => 'secondary', 'awaiting_approval' => 'warning', 'accepted' => 'info', 'quoted' => 'primary', 'awaiting_payment' => 'warning', 'payment_confirmed' => 'info',
        'printing' => 'primary', 'ready' => 'success', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
    return '<span class="badge rounded-pill p-ob p-ob-' . e($status) . ' text-bg-' . ($map[$status] ?? 'secondary') . '">' . e(order_statuses()[$status] ?? $status) . '</span>';
}
