<?php
require __DIR__ . '/includes/init.php';
admin_require('delivery');
if (is_post()) {
    csrf_check();
    setting_set('pickup_enabled', input_int('pickup_enabled'));
    foreach (['pickup_address', 'pickup_instructions'] as $k) setting_set($k, input($k));
    foreach (['pickup_open', 'pickup_close'] as $k) if (preg_match('/^\d{2}:\d{2}$/', input($k))) setting_set($k, input($k));
    setting_set('pickup_interval', max(15, input_int('pickup_interval', 60)));
    setting_set('pickup_lead_hours', max(0, input_int('pickup_lead_hours', 2)));
    setting_set('pickup_days_ahead', max(0, min(30, input_int('pickup_days_ahead', 5))));
    setting_set('pickup_closed_days', implode(',', array_map('intval', input_arr('closed'))));
    flash('success', 'Pickup settings saved.');
    redirect('admin/pickup.php');
}
$closed = array_filter(explode(',', (string) setting('pickup_closed_days', '')), 'strlen');
admin_header('Pickup Settings', 'pickup');
?>
<div class="row g-3"><div class="col-lg-7"><form method="post" class="card"><div class="card-body">
  <?= csrf_field() ?>
  <?= af_check('pickup_enabled', 'Store pickup enabled', setting('pickup_enabled', '1') === '1') ?>
  <?= af_textarea('pickup_address', 'Pickup address', setting('pickup_address'), ['rows' => 2]) ?>
  <div class="row"><div class="col-md-3"><?= af_text('pickup_open', 'Opens', setting('pickup_open', '10:00'), ['type' => 'time']) ?></div><div class="col-md-3"><?= af_text('pickup_close', 'Closes', setting('pickup_close', '21:00'), ['type' => 'time']) ?></div>
    <div class="col-md-3"><?= af_text('pickup_interval', 'Slot length (min)', setting('pickup_interval', 60), ['type' => 'number', 'min' => 15, 'step' => 15]) ?></div><div class="col-md-3"><?= af_text('pickup_lead_hours', 'Preparation time (hours)', setting('pickup_lead_hours', 2), ['type' => 'number', 'min' => 0]) ?></div>
    <div class="col-md-4"><?= af_text('pickup_days_ahead', 'Days customers can book ahead', setting('pickup_days_ahead', 5), ['type' => 'number', 'min' => 0, 'max' => 30]) ?></div></div>
  <label class="form-label">Closed days</label><div class="mb-3"><?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $i => $d): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="closed[]" value="<?= $i ?>" id="cd<?= $i ?>" <?= in_array((string) $i, $closed, true) ? 'checked' : '' ?>><label class="form-check-label" for="cd<?= $i ?>"><?= $d ?></label></div><?php endforeach; ?></div>
  <?= af_textarea('pickup_instructions', 'Instructions for customers', setting('pickup_instructions'), ['rows' => 2]) ?>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form></div>
<div class="col-lg-5"><div class="card"><div class="card-header">Next available slots (preview)</div><div class="card-body small">
  <?php foreach (array_slice(pickup_slots(), 0, 3) as $day): ?><strong><?= e($day['label']) ?></strong><div class="mb-2"><?= e(implode(' · ', array_column(array_slice($day['slots'], 0, 6), 'label'))) ?><?= count($day['slots']) > 6 ? ' …' : '' ?></div><?php endforeach; ?>
</div></div></div></div>
<?php admin_footer();
