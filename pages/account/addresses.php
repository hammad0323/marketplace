<?php
$c = require_customer();
$cid = (int) $c['id'];
seo_set(['title' => 'My Addresses', 'noindex' => true]);
$errors = [];
if (is_post()) {
    csrf_check();
    $action = input('action', '', 'post');
    $id = input_int('id');
    if ($action === 'delete') {
        db_exec('DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?', [$id, $cid]);
        flash('success', 'Address removed.');
        redirect('account/addresses');
    }
    if ($action === 'default') {
        db_exec('UPDATE customer_addresses SET is_default = (id = ?) WHERE customer_id = ?', [$id, $cid]);
        redirect('account/addresses');
    }
    $d = [
        'label' => mb_substr(input('label', '', 'post'), 0, 40), 'full_name' => mb_substr(input('full_name', '', 'post'), 0, 120),
        'phone' => mb_substr(input('phone', '', 'post'), 0, 30), 'address_line1' => mb_substr(input('address_line1', '', 'post'), 0, 255),
        'address_line2' => nullable(mb_substr(input('address_line2', '', 'post'), 0, 255)), 'city' => mb_substr(input('city', '', 'post'), 0, 100),
        'region' => input('region', '', 'post'), 'postal_code' => nullable(mb_substr(input('postal_code', '', 'post'), 0, 20)),
    ];
    if (mb_strlen($d['full_name']) < 3) $errors[] = 'Full name is required.';
    if (!valid_phone($d['phone'])) $errors[] = 'A valid phone number is required.';
    if (mb_strlen($d['address_line1']) < 5) $errors[] = 'Address is required.';
    if (mb_strlen($d['city']) < 2) $errors[] = 'City is required.';
    if (!in_array($d['region'], pakistan_regions(), true)) $errors[] = 'Please choose a province / region.';
    if (!$errors) {
        if ($id) {
            db_update('customer_addresses', $d, 'id = ? AND customer_id = ?', [$id, $cid]);
        } else {
            $d['customer_id'] = $cid;
            $d['is_default'] = db_val('SELECT 1 FROM customer_addresses WHERE customer_id = ?', [$cid]) ? 0 : 1;
            db_insert('customer_addresses', $d);
        }
        flash('success', 'Address saved.');
        redirect('account/addresses');
    }
}
$addresses = db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$cid]);
$edit = input_int('edit') ? db_row('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [input_int('edit'), $cid]) : null;
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">My Addresses</h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'addresses']); ?>
        <div class="uc-account-main">
            <div class="uc-address-grid">
                <?php foreach ($addresses as $a): ?>
                    <div class="uc-card-box">
                        <?php if ((int) $a['is_default']): ?><span class="uc-tag">Default</span><?php endif; ?>
                        <strong class="d-block"><?= e($a['label'] ?: 'Address') ?></strong>
                        <address class="small mb-2"><?= e($a['full_name']) ?><br><?= e($a['address_line1']) ?><?= $a['address_line2'] ? ', ' . e($a['address_line2']) : '' ?><br><?= e($a['city']) ?>, <?= e($a['region']) ?> <?= e($a['postal_code']) ?><br><?= e($a['phone']) ?></address>
                        <div class="d-flex gap-3 small">
                            <a href="?edit=<?= (int) $a['id'] ?>#addressForm">Edit</a>
                            <?php if (!(int) $a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="uc-link-btn" type="submit">Make default</button></form><?php endif; ?>
                            <form method="post" data-confirm="Remove this address?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="uc-link-btn text-danger" type="submit">Remove</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="uc-card-box" id="addressForm">
                <h2 class="h5"><?= $edit ? 'Edit address' : 'Add a new address' ?></h2>
                <?php if ($errors): ?><div class="uc-alert uc-alert--error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
                <form method="post" class="row g-3">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <?php $v = fn($k) => e($_POST[$k] ?? ($edit[$k] ?? '')); ?>
                    <div class="col-md-4"><label class="uc-field"><span>Label</span><input type="text" name="label" placeholder="Home, Office…" value="<?= $v('label') ?>"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>Full name *</span><input type="text" name="full_name" required value="<?= $v('full_name') ?>"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>Phone *</span><input type="tel" name="phone" required value="<?= $v('phone') ?>"></label></div>
                    <div class="col-12"><label class="uc-field"><span>Address *</span><input type="text" name="address_line1" required value="<?= $v('address_line1') ?>"></label></div>
                    <div class="col-12"><label class="uc-field"><span>Apartment, landmark</span><input type="text" name="address_line2" value="<?= $v('address_line2') ?>"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>City *</span><input type="text" name="city" required value="<?= $v('city') ?>"></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>Province / region *</span><select name="region" class="form-select" required><option value="">Select…</option>
                        <?php foreach (pakistan_regions() as $r): ?><option<?= ($_POST['region'] ?? ($edit['region'] ?? '')) === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></label></div>
                    <div class="col-md-4"><label class="uc-field"><span>Postal code</span><input type="text" name="postal_code" value="<?= $v('postal_code') ?>"></label></div>
                    <div class="col-12"><button class="uc-btn" type="submit">Save Address</button><?php if ($edit): ?> <a class="ms-3" href="<?= e(url('account/addresses')) ?>">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
