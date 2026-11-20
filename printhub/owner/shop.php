<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
$shop = $OWNER['shop'];
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    if ($a === 'profile') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $email = trim((string) ($_POST['email'] ?? ''));
        if (mb_strlen($name) < 2 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('error', 'Enter your name and a valid email (or leave email empty).');
        } else {
            db_update('shop_users', ['name' => $name, 'email' => $email ?: null, 'phone' => nullable(clean_phone((string) ($_POST['phone'] ?? '')))], 'id = ?', [(int) $OWNER['user']['id']]);
            flash('success', 'Profile saved.');
        }
        redirect('owner/shop.php#profile');
    }
    owner_require_active('owner/shop.php');
    if ($a === 'toggle_open') {
        db_update('shops', ['accepting_orders' => (int) $shop['accepting_orders'] ? 0 : 1], 'id = ?', [$sid]);
        activity('owner', (int) $OWNER['user']['id'], (int) $shop['accepting_orders'] ? 'orders_paused' : 'orders_resumed', $sid);
        flash('success', (int) $shop['accepting_orders'] ? 'Online orders paused. Customers see your shop as closed.' : 'You are open for online orders.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'owner/');
    }
    if ($a === 'info') {
        $err = owner_update_shop($shop, $_POST + ['accepting_orders' => $_POST['accepting_orders'] ?? '0']);
        if (!$err && !empty($_FILES['logo']['name'])) {
            try {
                $path = media_store_image($_FILES['logo'], 'logos', 600);
                storage_delete($shop['logo_path']);
                db_update('shops', ['logo_path' => $path], 'id = ?', [$sid]);
            } catch (RuntimeException $e) {
                $err = $e->getMessage();
            }
        }
        if (!$err) activity('owner', (int) $OWNER['user']['id'], 'shop_info_updated', $sid);
        flash($err ? 'error' : 'success', $err ?: 'Shop details saved.');
    }
    if ($a === 'settings') {
        $err = owner_update_settings($sid, $_POST);
        if (!$err) activity('owner', (int) $OWNER['user']['id'], 'settings_updated', $sid);
        flash($err ? 'error' : 'success', $err ?: 'Settings saved.');
    }
    redirect('owner/shop.php');
}
$s = shop_settings($sid);
$u = db_row('SELECT * FROM shop_users WHERE id = ?', [(int) $OWNER['user']['id']]);
owner_header('Shop & settings');
?>
<div class="row g-3">
<div class="col-xl-7">
<div class="p-card"><div class="p-card-head"><i class="bi bi-shop"></i> Shop information</div><div class="p-card-body">
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="info">
    <div class="d-flex gap-3 align-items-center mb-3"><?php if ($shop['logo_path']): ?><img src="<?= e(path('media/' . $shop['logo_path'])) ?>" alt="" style="width:64px;height:64px;object-fit:contain;border-radius:14px;border:1px solid #E2E8F0"><?php endif; ?>
        <div class="flex-grow-1"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div></div>
    <?= f_input('name', 'Shop name', $shop['name'], 'text', ['required' => true, 'maxlength' => 150]) ?>
    <div class="row"><div class="col-md-4"><?= f_input('phone', 'Contact number', $shop['phone'], 'tel', ['required' => true]) ?></div><div class="col-md-4"><?= f_input('whatsapp', 'WhatsApp', $shop['whatsapp'], 'tel') ?></div><div class="col-md-4"><?= f_input('email', 'Email', $shop['email'], 'email') ?></div></div>
    <?= f_input('address', 'Address', $shop['address'], 'text', ['required' => true]) ?>
    <div class="row"><div class="col-md-6"><?= f_input('area', 'Area', $shop['area']) ?></div><div class="col-md-6"><?= f_input('city', 'City', $shop['city'], 'text', ['required' => true]) ?></div></div>
    <div class="row"><div class="col-5"><?= f_input('latitude', 'Latitude', $shop['latitude']) ?></div><div class="col-5"><?= f_input('longitude', 'Longitude', $shop['longitude']) ?></div>
        <div class="col-2 d-flex align-items-end mb-3"><button type="button" class="btn btn-light w-100" onclick="navigator.geolocation&&navigator.geolocation.getCurrentPosition(function(p){document.getElementById('f_latitude').value=p.coords.latitude.toFixed(6);document.getElementById('f_longitude').value=p.coords.longitude.toFixed(6);})" title="Use my location"><i class="bi bi-geo-alt"></i></button></div></div>
    <?= f_switch('accepting_orders', 'Accepting online orders now', (int) $shop['accepting_orders']) ?>
    <button class="btn btn-primary">Save shop details</button>
</form></div></div>
<div class="p-card" id="profile"><div class="p-card-head"><i class="bi bi-person"></i> My profile</div><div class="p-card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="row"><div class="col-md-4"><?= f_input('name', 'Name', $u['name'], 'text', ['required' => true]) ?></div><div class="col-md-4"><?= f_input('email', 'Email (for password reset)', $u['email'], 'email') ?></div><div class="col-md-4"><?= f_input('phone', 'Phone', $u['phone'], 'tel') ?></div></div>
    <p class="small text-muted">Username <code><?= e($u['username']) ?></code> · <a href="<?= e(path('owner/password.php')) ?>">Change password</a></p>
    <button class="btn btn-outline-primary">Save profile</button></form></div></div>
</div>
<div class="col-xl-5"><div class="p-card"><div class="p-card-head"><i class="bi bi-gear"></i> Order settings</div><div class="p-card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <?= f_select('pricing_mode', 'Pricing', ['auto' => 'Automatic — customers see my prices', 'quote' => 'Quote — I send a price for each order'], $s['pricing_mode']) ?>
    <?= f_select('payment_mode', 'Payment', ['pay_at_shop' => 'Customers pay at the shop', 'advance_required' => 'Advance payment required before printing', 'advance_optional' => 'Advance payment optional'], $s['payment_mode']) ?>
    <?= f_textarea('payment_instructions', 'Payment instructions (shown for advance payment)', $s['payment_instructions'], 3, 'e.g. JazzCash 0300-1234567 (Imran Ali) · Easypaisa … · Meezan Bank IBAN …') ?>
    <div class="row"><div class="col-6"><?= f_input('min_order_charge', 'Minimum order (PKR)', (float) $s['min_order_charge'], 'number', ['min' => 0]) ?></div><div class="col-6"><?= f_input('estimated_minutes', 'Usual ready time (min)', $s['estimated_minutes'], 'number', ['min' => 1]) ?></div></div>
    <?= f_input('opening_hours', 'Opening hours', $s['opening_hours'], 'text', ['placeholder' => 'Mon–Sat 9am–10pm']) ?>
    <div class="row"><div class="col-6"><?= f_input('retention_days', 'Delete customer files after (days)', $s['retention_days'], 'number', ['min' => 1, 'max' => (int) setting('max_retention_days', 30)], 'Counted from when the order is closed. Max ' . (int) setting('max_retention_days', 30) . '.') ?></div>
        <div class="col-6"><?= f_input('max_upload_mb', 'Max file size (MB)', $s['max_upload_mb'], 'number', ['min' => 1, 'max' => (int) setting('max_upload_mb', 25)]) ?></div></div>
    <button class="btn btn-primary w-100">Save settings</button>
</form></div></div></div>
</div>
<?php owner_footer();
