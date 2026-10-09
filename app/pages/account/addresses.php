<?php
$customer = require_customer();
meta_set(['title' => 'My addresses', 'noindex' => true]);
$cid = (int) $customer['id'];
$errors = [];
$editing = null;
if (is_post()) {
    require_csrf();
    $action = input('action');
    $id = input_int('id');
    if ($action === 'delete') {
        db_exec('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$id, $cid]);
        flash('success', 'Address removed.');
        redirect(path_url('account/addresses'));
    }
    if ($action === 'default') {
        db_tx(function () use ($id, $cid) {
            db_exec('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?', [$cid]);
            db_exec('UPDATE customer_addresses SET is_default = 1 WHERE id = ? AND customer_id = ?', [$id, $cid]);
        });
        redirect(path_url('account/addresses'));
    }
    $d = [
        'label' => mb_substr(input('label') ?: 'Home', 0, 40), 'full_name' => input('full_name'), 'phone' => input('phone'),
        'address_line1' => input('address_line1'), 'address_line2' => input('address_line2') ?: null, 'city' => input('city'),
        'region' => input('region'), 'postal_code' => input('postal_code') ?: null,
    ];
    if (mb_strlen($d['full_name']) < 2) { $errors['full_name'] = 'Required.'; }
    if (!valid_phone($d['phone'])) { $errors['phone'] = 'Enter a valid phone number.'; }
    if (mb_strlen($d['address_line1']) < 5) { $errors['address_line1'] = 'Enter the full address.'; }
    if (mb_strlen($d['city']) < 2) { $errors['city'] = 'Required.'; }
    if (!in_array($d['region'], pk_regions(), true)) { $errors['region'] = 'Select a region.'; }
    if (!$errors) {
        if ($id && db_val('SELECT COUNT(*) FROM customer_addresses WHERE id = ? AND customer_id = ?', [$id, $cid])) {
            db_update('customer_addresses', $d, 'id = ? AND customer_id = ?', [$id, $cid]);
        } else {
            $d['customer_id'] = $cid;
            $d['is_default'] = db_val('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$cid]) ? 0 : 1;
            db_insert('customer_addresses', $d);
        }
        flash('success', 'Address saved.');
        redirect(path_url('account/addresses'));
    }
    $editing = $d + ['id' => $id];
}
if (!$editing && input_int('edit', 0, 'get')) {
    $editing = db_one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [input_int('edit', 0, 'get'), $cid]);
}
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$cid]);
$a = $editing ?? [];
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
partial('header');
?>
<div class="container container--wide page-pad account">
  <h1 class="page-title">Addresses</h1>
  <div class="account__layout">
    <?php $active = 'addresses'; require __DIR__ . '/_nav.php'; ?>
    <div class="account__main">
      <div class="address-grid">
        <?php foreach ($addresses as $ad): ?>
          <div class="info-card">
            <h3><?= e($ad['label']) ?><?= $ad['is_default'] ? ' <span class="badge text-bg-dark">Default</span>' : '' ?></h3>
            <p><?= e($ad['full_name']) ?><br><?= e($ad['address_line1']) ?><?= $ad['address_line2'] ? '<br>' . e($ad['address_line2']) : '' ?><br><?= e($ad['city']) ?>, <?= e($ad['region']) ?> <?= e($ad['postal_code']) ?><br><?= e($ad['phone']) ?></p>
            <div class="d-flex gap-3 small">
              <a href="<?= e(path_url('account/addresses', ['edit' => $ad['id']])) ?>">Edit</a>
              <?php if (!$ad['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>"><button class="link-muted">Make default</button></form><?php endif; ?>
              <form method="post" data-confirm="Remove this address?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ad['id'] ?>"><button class="link-muted text-danger">Remove</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <h2 class="h5 mt-4"><?= !empty($a['id']) ? 'Edit address' : 'Add a new address' ?></h2>
      <form method="post" class="row g-3" novalidate>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">
        <div class="col-md-4"><label class="form-label">Label</label><input class="form-control" name="label" maxlength="40" value="<?= e($a['label'] ?? 'Home') ?>"></div>
        <div class="col-md-4"><label class="form-label">Full name</label><input class="form-control" name="full_name" maxlength="150" value="<?= e($a['full_name'] ?? '') ?>"><?= $err('full_name') ?></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30" value="<?= e($a['phone'] ?? '') ?>"><?= $err('phone') ?></div>
        <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address_line1" maxlength="255" value="<?= e($a['address_line1'] ?? '') ?>"><?= $err('address_line1') ?></div>
        <div class="col-md-4"><label class="form-label">Apartment / landmark</label><input class="form-control" name="address_line2" maxlength="255" value="<?= e($a['address_line2'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" maxlength="100" value="<?= e($a['city'] ?? '') ?>"><?= $err('city') ?></div>
        <div class="col-md-4"><label class="form-label">Province / region</label><select class="form-select" name="region"><option value="">Select…</option><?php foreach (pk_regions() as $r): ?><option<?= ($a['region'] ?? '') === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select><?= $err('region') ?></div>
        <div class="col-md-4"><label class="form-label">Postal code</label><input class="form-control" name="postal_code" maxlength="12" value="<?= e($a['postal_code'] ?? '') ?>"></div>
        <div class="col-12"><button class="btn-lux">Save address</button></div>
      </form>
    </div>
  </div>
</div>
<?php partial('footer');
