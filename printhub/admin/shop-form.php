<?php
require __DIR__ . '/_init.php';
$id = (int) ($_GET['id'] ?? 0);
$shop = $id ? shop_by_id($id) : null;
if ($id && !$shop) {
    flash('error', 'Shop not found.');
    redirect('admin/shops.php');
}
$input = $shop ?: ['months' => 1];
$errors = [];
if (is_post()) {
    [$d, $errors] = shop_validate($_POST, $shop);
    $input = $_POST;
    if (!$errors && !empty($_FILES['logo']['name'])) {
        try {
            $d['logo_path'] = media_store_image($_FILES['logo'], 'logos', 600);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
    if (!$errors) {
        if ($shop) {
            $upd = $d;
            unset($upd['price']);
            $upd['subscription_price'] = $d['price'];
            if (!empty($_POST['remove_logo'])) {
                storage_delete($shop['logo_path']);
                $upd['logo_path'] = null;
            } elseif (isset($d['logo_path']) && $shop['logo_path']) {
                storage_delete($shop['logo_path']);
            }
            db_update('shops', $upd, 'id = ?', [$id]);
            db_exec("UPDATE shop_users SET name = IF(role = 'owner', ?, name) WHERE shop_id = ? AND role = 'owner'", [$d['owner_name'], $id]);
            activity('admin', (int) current_admin()['id'], 'shop_updated', $id, 'shop', $id);
            flash('success', 'Shop updated.');
            redirect('admin/shop.php?id=' . $id);
        }
        $new = shop_create($d, (int) current_admin()['id']);
        $_SESSION['new_shop_credentials'] = ['id' => (int) $new['id'], 'username' => $d['username'], 'password' => $d['password']];
        flash('success', 'Shop created. Share the login details with the owner — they will be asked to change the password at first sign-in.');
        redirect('admin/shop.php?id=' . $new['id']);
    }
}
$v = fn($k, $def = '') => e($input[$k] ?? $def);
admin_header($shop ? 'Edit ' . $shop['name'] : 'Add print shop');
?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="row g-4"><?= csrf_field() ?>
<div class="col-xl-8">
    <div class="p-card"><div class="p-card-head"><i class="bi bi-shop"></i> Shop details</div><div class="p-card-body"><div class="row">
        <div class="col-md-6"><?= f_input('name', 'Shop name', $input['name'] ?? '', 'text', ['required' => true, 'maxlength' => 150]) ?></div>
        <div class="col-md-6"><?= f_input('owner_name', 'Owner name', $input['owner_name'] ?? '', 'text', ['required' => true, 'maxlength' => 120]) ?></div>
        <div class="col-md-4"><?= f_input('phone', 'Contact number', $input['phone'] ?? '', 'tel', ['required' => true, 'placeholder' => '03xx xxxxxxx']) ?></div>
        <div class="col-md-4"><?= f_input('whatsapp', 'WhatsApp (optional)', $input['whatsapp'] ?? '', 'tel') ?></div>
        <div class="col-md-4"><?= f_input('email', 'Email (optional, for password reset)', $input['email'] ?? '', 'email') ?></div>
        <div class="col-12"><?= f_input('address', 'Complete address', $input['address'] ?? '', 'text', ['required' => true, 'placeholder' => 'Shop #, street, market / plaza']) ?></div>
        <div class="col-md-6"><?= f_input('area', 'Area / locality', $input['area'] ?? '', 'text', ['placeholder' => 'e.g. Saddar, Blue Area, Anarkali']) ?></div>
        <div class="col-md-6"><?= f_input('city', 'City', $input['city'] ?? '', 'text', ['required' => true, 'list' => 'pkcities']) ?></div>
        <datalist id="pkcities"><?php foreach (['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar', 'Quetta', 'Hyderabad', 'Sialkot', 'Gujranwala', 'Bahawalpur', 'Sargodha', 'Abbottabad', 'Sukkur'] as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist>
        <div class="col-md-5"><?= f_input('latitude', 'Latitude', $input['latitude'] ?? '', 'text', ['inputmode' => 'decimal', 'placeholder' => '24.8607']) ?></div>
        <div class="col-md-5"><?= f_input('longitude', 'Longitude', $input['longitude'] ?? '', 'text', ['inputmode' => 'decimal', 'placeholder' => '67.0011']) ?></div>
        <div class="col-md-2 d-flex align-items-end mb-3"><button type="button" class="btn btn-light w-100" id="geo" title="Use this device's location"><i class="bi bi-geo-alt"></i></button></div>
        <div class="col-12"><p class="form-text mt-n2">Tip: in Google Maps long-press the shop and copy the numbers, or open this form at the shop and tap <i class="bi bi-geo-alt"></i>.</p></div>
    </div></div></div>
</div>
<div class="col-xl-4">
    <?php if (!$shop): ?>
    <div class="p-card"><div class="p-card-head"><i class="bi bi-person-lock"></i> Owner login</div><div class="p-card-body">
        <?= f_input('username', 'Username', $input['username'] ?? '', 'text', ['required' => true, 'autocomplete' => 'off', 'pattern' => '[A-Za-z0-9._\-]{4,40}']) ?>
        <div class="mb-3"><label class="form-label">Temporary password</label><div class="input-group"><input class="form-control" name="password" id="pw" value="<?= $v('password') ?>" required minlength="8" autocomplete="off">
            <button type="button" class="btn btn-light" id="genpw" title="Generate"><i class="bi bi-shuffle"></i></button></div><div class="form-text">The owner must change it at first sign-in.</div></div>
    </div></div>
    <?php endif; ?>
    <div class="p-card"><div class="p-card-head"><i class="bi bi-calendar-check"></i> Subscription</div><div class="p-card-body">
        <?= f_input('subscription_price', 'Custom monthly price (PKR)', $shop ? ($shop['subscription_price'] ?? '') : ($input['subscription_price'] ?? ''), 'number', ['min' => 0, 'step' => 1, 'placeholder' => 'Default ' . number_format(subscription_default_price())], 'Leave empty to use the platform default of ' . e(money(subscription_default_price())) . '.') ?>
        <?php if (!$shop): ?><?= f_select('months', 'Start with', [0 => 'No subscription yet (record payment later)', 1 => '1 month', 2 => '2 months', 3 => '3 months', 6 => '6 months', 12 => '12 months'], $input['months'] ?? 1, 'Starts today. Record the payment afterwards under Payments.') ?><?php endif; ?>
    </div></div>
    <div class="p-card"><div class="p-card-head"><i class="bi bi-image"></i> Shop logo</div><div class="p-card-body">
        <?php if ($shop && $shop['logo_path']): ?><img src="<?= e(path('media/' . $shop['logo_path'])) ?>" alt="" style="max-height:80px" class="mb-2 d-block"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="remove_logo" value="1"> <span class="form-check-label small">Remove logo</span></label><?php endif; ?>
        <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><div class="form-text">Square PNG works best (shown in the QR code and on the shop page).</div>
    </div></div>
    <button class="btn btn-primary w-100 py-2"><?= $shop ? 'Save changes' : 'Create shop' ?></button>
</div>
</form>
<script>
document.getElementById('genpw') && document.getElementById('genpw').addEventListener('click', function () {
    var c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', p = '', r = new Uint32Array(10); crypto.getRandomValues(r);
    for (var i = 0; i < 10; i++) { p += c[r[i] % c.length]; } document.getElementById('pw').value = p.replace(/^(.{4})/, '$1' + (r[0] % 10));
});
document.getElementById('geo').addEventListener('click', function () {
    if (!navigator.geolocation) { return; }
    navigator.geolocation.getCurrentPosition(function (p) { document.getElementById('f_latitude').value = p.coords.latitude.toFixed(6); document.getElementById('f_longitude').value = p.coords.longitude.toFixed(6); },
        function () { Swal.fire({ text: 'Location permission was denied.', icon: 'info' }); });
});
</script>
<?php admin_footer();
