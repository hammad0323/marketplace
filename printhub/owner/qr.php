<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
if (is_post()) {
    owner_require_active('owner/qr.php');
    $a = (string) ($_POST['action'] ?? 'design');
    if ($a === 'regenerate') {
        db_exec("UPDATE shop_qr_settings SET version = version + 1, fg_color = '#0F172A', bg_color = '#FFFFFF', accent_color = '#0F766E' WHERE shop_id = ?", [$sid]);
        activity('owner', (int) $OWNER['user']['id'], 'qr_regenerated', $sid);
        flash('success', 'QR code regenerated. Your shop link stays the same, so printed codes keep working.');
    } elseif ($a === 'landing') {
        $err = owner_update_settings($sid, ['landing_heading' => $_POST['landing_heading'] ?? '', 'landing_message' => $_POST['landing_message'] ?? '', 'theme_color' => $_POST['theme_color'] ?? '',
            'estimated_minutes' => $_POST['estimated_minutes'] ?? 30, 'opening_hours' => $_POST['opening_hours'] ?? '']);
        flash($err ? 'error' : 'success', $err ?: 'Customer page updated.');
    } else {
        owner_update_qr($sid, $_POST + ['show_logo' => $_POST['show_logo'] ?? '0']);
        flash('success', 'QR design saved.');
    }
    redirect('owner/qr.php');
}
$q = shop_qr_settings($sid);
$s = shop_settings($sid);
$url = shop_url($OWNER['shop']);
$v = (int) $q['version'] . '-' . substr(md5(json_encode($q)), 0, 6);
owner_header('QR code');
?>
<div class="row g-3">
<div class="col-xl-5"><div class="p-card"><div class="p-card-body">
    <div class="p-qr-preview" style="background:<?= e($q['bg_color']) ?>">
        <p class="fw-bold mb-1" style="color:<?= e($q['accent_color']) ?>;font-size:1.2rem"><?= e($q['heading']) ?></p>
        <p class="small mb-2" style="color:<?= e($q['fg_color']) ?>"><?= e($OWNER['shop']['name']) ?><?= $q['subheading'] ? ' · ' . e($q['subheading']) : '' ?></p>
        <img id="qrImg" src="<?= e(path('api/v1/owner/qr.png?v=' . $v)) ?>" alt="Shop QR code">
        <p class="small mt-2 mb-0" style="color:<?= e($q['fg_color']) ?>"><?= e($url) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#qrFull"><i class="bi bi-arrows-fullscreen"></i> Full screen</button>
        <a class="btn btn-primary btn-sm" href="<?= e(path('api/v1/owner/qr.png?download=1&scale=24')) ?>"><i class="bi bi-download"></i> PNG</a>
        <a class="btn btn-primary btn-sm" href="<?= e(path('api/v1/owner/qr.pdf?download=1')) ?>"><i class="bi bi-file-earmark-pdf"></i> Printable PDF</a>
        <button class="btn btn-outline-primary btn-sm" id="shareQr"><i class="bi bi-share"></i> Share</button>
        <button class="btn btn-light btn-sm" data-copy="<?= e($url) ?>"><i class="bi bi-link-45deg"></i> Copy link</button>
    </div>
    <p class="small text-muted text-center mt-3 mb-0">Shop ID <code><?= e($OWNER['shop']['shop_code']) ?></code> never changes — every QR you print keeps opening your shop.</p>
</div></div></div>
<div class="col-xl-7">
<div class="p-card"><div class="p-card-head"><i class="bi bi-palette"></i> Design</div><div class="p-card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="design">
    <div class="row"><div class="col-4"><?= f_input('fg_color', 'Code colour', $q['fg_color'], 'color') ?></div><div class="col-4"><?= f_input('accent_color', 'Corner colour', $q['accent_color'], 'color') ?></div><div class="col-4"><?= f_input('bg_color', 'Background', $q['bg_color'], 'color') ?></div></div>
    <?= f_input('heading', 'Heading', $q['heading'], 'text', ['maxlength' => 80]) ?>
    <?= f_input('subheading', 'Sub-heading', $q['subheading'], 'text', ['maxlength' => 160]) ?>
    <?= f_switch('show_logo', 'Shop logo in the centre' . ($OWNER['shop']['logo_path'] ? '' : ' (upload a logo under Shop & settings)'), (int) $q['show_logo']) ?>
    <p class="form-text">Keep strong contrast (dark code on a light background) so every phone can scan it.</p>
    <div class="d-flex gap-2"><button class="btn btn-primary">Save design</button></div>
</form>
<form method="post" class="mt-2" data-confirm="Reset the QR design to default colours? The link stays the same."><?= csrf_field() ?><input type="hidden" name="action" value="regenerate"><button class="btn btn-link btn-sm px-0"><i class="bi bi-arrow-repeat"></i> Reset / regenerate QR code</button></form>
</div></div>
<div class="p-card"><div class="p-card-head"><i class="bi bi-phone"></i> Customer landing page<a class="ms-auto small" href="<?= e($url) ?>" target="_blank">Open <i class="bi bi-box-arrow-up-right"></i></a></div><div class="p-card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="landing">
    <?= f_input('landing_heading', 'Heading', $s['landing_heading'], 'text', ['maxlength' => 120, 'placeholder' => 'Print anything in minutes']) ?>
    <?= f_textarea('landing_message', 'Welcome message', $s['landing_message'], 2, '', ['maxlength' => 500, 'placeholder' => 'e.g. Free Wi-Fi · Same-day binding · Open till 10 pm']) ?>
    <div class="row"><div class="col-sm-4"><?= f_input('theme_color', 'Brand colour', $s['theme_color'], 'color') ?></div>
        <div class="col-sm-4"><?= f_input('estimated_minutes', 'Usual ready time (minutes)', $s['estimated_minutes'], 'number', ['min' => 1]) ?></div>
        <div class="col-sm-4"><?= f_input('opening_hours', 'Opening hours', $s['opening_hours'], 'text', ['placeholder' => 'Mon–Sat 9am–10pm']) ?></div></div>
    <button class="btn btn-primary">Save landing page</button>
</form></div></div>
</div></div>
<div class="modal fade" id="qrFull" tabindex="-1"><div class="modal-dialog modal-fullscreen"><div class="modal-content" style="background:<?= e($q['bg_color']) ?>"><div class="modal-body d-flex flex-column align-items-center justify-content-center text-center">
    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
    <h2 style="color:<?= e($q['accent_color']) ?>"><?= e($q['heading']) ?></h2><p style="color:<?= e($q['fg_color']) ?>"><?= e($OWNER['shop']['name']) ?></p>
    <img src="<?= e(path('api/v1/owner/qr.png?scale=24&v=' . $v)) ?>" alt="" style="width:min(80vw,70vh);height:auto">
    <p class="mt-3" style="color:<?= e($q['fg_color']) ?>">Scan with your phone camera to upload files</p></div></div></div></div>
<script>
document.getElementById('shareQr').addEventListener('click', function () {
    var url = <?= json_encode($url) ?>, title = <?= json_encode($OWNER['shop']['name']) ?>;
    fetch(<?= json_encode(path('api/v1/owner/qr.png?scale=16')) ?>).then(function (r) { return r.blob(); }).then(function (b) {
        var file = new File([b], 'shop-qr.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) { return navigator.share({ files: [file], title: title, text: 'Send your files to print: ' + url }); }
        if (navigator.share) { return navigator.share({ title: title, url: url }); }
        navigator.clipboard.writeText(url); Swal.fire({ icon: 'success', text: 'Link copied. Paste it in WhatsApp or anywhere.' });
    }).catch(function () {});
});
</script>
<?php owner_footer();
