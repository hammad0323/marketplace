<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
if (is_post()) {
    owner_require_active('owner/wifi.php');
    $err = owner_update_wifi($sid, ['ssid' => $_POST['ssid'] ?? '', 'password' => $_POST['password'] ?? '', 'security' => $_POST['security'] ?? 'WPA',
        'is_hidden_network' => $_POST['is_hidden_network'] ?? '0', 'is_public' => $_POST['is_public'] ?? '0']);
    if (!$err) activity('owner', (int) $OWNER['user']['id'], 'wifi_updated', $sid);
    flash($err ? 'error' : 'success', $err ?: 'Wi-Fi details saved.');
    redirect('owner/wifi.php');
}
$w = shop_wifi($sid, true);
owner_header('Wi-Fi');
?>
<div class="row g-3">
<div class="col-xl-7"><div class="p-card"><div class="p-card-head"><i class="bi bi-wifi"></i> Customer Wi-Fi</div><div class="p-card-body">
<form method="post" autocomplete="off"><?= csrf_field() ?>
    <?= f_input('ssid', 'Wi-Fi name (SSID)', $w['ssid'], 'text', ['maxlength' => 32]) ?>
    <div class="row"><div class="col-sm-7"><div class="mb-3"><label class="form-label">Password</label><div class="input-group"><input class="form-control" type="password" name="password" id="wpw" value="<?= e($w['password']) ?>" autocomplete="new-password">
        <button type="button" class="btn btn-light" onclick="var i=document.getElementById('wpw');i.type=i.type==='password'?'text':'password'"><i class="bi bi-eye"></i></button></div></div></div>
        <div class="col-sm-5"><?= f_select('security', 'Security', ['WPA' => 'WPA / WPA2 / WPA3', 'WEP' => 'WEP (old)', 'nopass' => 'Open network (no password)'], $w['security']) ?></div></div>
    <?= f_switch('is_hidden_network', 'This is a hidden network', (int) $w['is_hidden_network']) ?>
    <?= f_switch('is_public', 'Show Wi-Fi details to customers on my shop page', (int) $w['is_public'], 'Off by default. Turn on only if you are happy for anyone with your shop link to see the password.') ?>
    <button class="btn btn-primary">Save Wi-Fi</button>
</form></div></div></div>
<div class="col-xl-5"><div class="p-card"><div class="p-card-head"><i class="bi bi-qr-code"></i> Wi-Fi QR code</div><div class="p-card-body text-center">
<?php if ($w['ssid']): ?>
    <img src="<?= e(path('api/v1/owner/wifi/qr.png?v=' . substr(md5($w['ssid'] . $w['password'] . $w['security']), 0, 8))) ?>" alt="Wi-Fi QR" style="width:220px;height:220px">
    <p class="small text-muted mt-2">A separate code from your ordering QR — scanning it joins <strong><?= e($w['ssid']) ?></strong> on most Android phones and iPhones.</p>
    <a class="btn btn-sm btn-primary" href="<?= e(path('api/v1/owner/wifi/qr.png')) ?>" download="wifi-qr.png"><i class="bi bi-download"></i> Download PNG</a>
    <p class="small mt-3 mb-0"><?= (int) $w['is_public'] ? '<span class="text-success"><i class="bi bi-eye"></i> Shown on your shop page and printable poster.</span>' : '<span class="text-muted"><i class="bi bi-eye-slash"></i> Hidden from customers.</span>' ?></p>
<?php else: ?><div class="p-empty"><i class="bi bi-wifi-off"></i>Add your Wi-Fi name to create its QR code.</div><?php endif; ?>
</div></div></div></div>
<?php owner_footer();
