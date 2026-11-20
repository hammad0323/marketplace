<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
if (is_post()) {
    owner_require_active('owner/services.php');
    $data = ['categories' => [], 'subcategories' => [], 'options' => [], 'notes' => (array) ($_POST['notes'] ?? [])];
    foreach ((array) ($_POST['cat_ids'] ?? []) as $cid) $data['categories'][(int) $cid] = !empty($_POST['cat'][(int) $cid]);
    foreach ((array) ($_POST['sub_ids'] ?? []) as $sbid) $data['subcategories'][(int) $sbid] = !empty($_POST['sub'][(int) $sbid]);
    foreach ((array) ($_POST['opt_ids'] ?? []) as $oid) $data['options'][(int) $oid] = !empty($_POST['opt'][(int) $oid]);
    owner_save_services($sid, $data);
    activity('owner', (int) $OWNER['user']['id'], 'services_updated', $sid);
    flash('success', 'Services saved. Customers only see what you switched on.');
    redirect('owner/services.php');
}
$sv = owner_services($sid);
$groups = ['paper_size' => 'Paper sizes', 'paper_type' => 'Paper types', 'gsm' => 'Paper GSM', 'finishing' => 'Finishing services'];
owner_header('Services');
?>
<form method="post"><?= csrf_field() ?>
<p class="text-muted">Switch on the services you offer. Then set your rates under <a href="<?= e(path('owner/pricing.php')) ?>">Pricing</a> — anything without a rate is sent to you as a quote request.</p>
<div class="row g-3">
<?php foreach ($sv['categories'] as $c): ?>
<div class="col-xl-6"><div class="p-card h-100 mb-0"><div class="p-card-head">
    <input type="hidden" name="cat_ids[]" value="<?= (int) $c['id'] ?>">
    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="cat[<?= (int) $c['id'] ?>]" value="1" id="c<?= (int) $c['id'] ?>"<?= $c['enabled'] ? ' checked' : '' ?>>
    <label class="form-check-label" for="c<?= (int) $c['id'] ?>"><i class="bi bi-<?= e($c['icon']) ?> me-1"></i><?= e($c['name']) ?></label></div></div>
<div class="p-card-body"><p class="small text-muted mb-2"><?= e($c['description']) ?></p>
    <div class="d-flex flex-wrap gap-2 mb-3"><?php foreach ($c['subcategories'] as $s): ?><input type="hidden" name="sub_ids[]" value="<?= (int) $s['id'] ?>">
        <label class="p-chip"><input class="form-check-input mt-0" type="checkbox" name="sub[<?= (int) $s['id'] ?>]" value="1"<?= $s['enabled'] ? ' checked' : '' ?>> <?= e($s['name']) ?></label><?php endforeach; ?></div>
    <input class="form-control form-control-sm" name="notes[<?= (int) $c['id'] ?>]" value="<?= e($c['note']) ?>" maxlength="160" placeholder="Note for customers, e.g. “Same-day”, “Binding ready in 1 hour”">
</div></div></div>
<?php endforeach; ?>
</div>
<div class="p-card mt-3"><div class="p-card-head"><i class="bi bi-sliders"></i> Papers and finishing you offer</div><div class="p-card-body">
<?php foreach ($groups as $g => $label): ?><p class="fw-semibold small mb-1 mt-2"><?= e($label) ?></p><div class="d-flex flex-wrap gap-2 mb-2">
    <?php foreach ($sv['options'][$g] ?? [] as $o): ?><input type="hidden" name="opt_ids[]" value="<?= (int) $o['id'] ?>"><label class="p-chip"><input class="form-check-input mt-0" type="checkbox" name="opt[<?= (int) $o['id'] ?>]" value="1"<?= $o['enabled'] ? ' checked' : '' ?>> <?= e($o['label']) ?></label><?php endforeach; ?></div>
<?php endforeach; ?></div></div>
<button class="btn btn-primary">Save services</button>
</form>
<?php owner_footer();
