<?php
require __DIR__ . '/includes/init.php';
admin_require('delivery');
if (is_post()) {
    csrf_check();
    $act = input('act'); $id = input_int('id');
    if ($act === 'settings') {
        foreach (['delivery_enabled' => 'int', 'default_delivery_fee' => 'num', 'free_delivery_over' => 'num', 'min_order_amount' => 'num', 'delivery_note' => 'str'] as $k => $t) {
            setting_set($k, $t === 'int' ? input_int($k) : ($t === 'num' ? (string) input_float($k, 0.0) : input($k)));
        }
        flash('success', 'Delivery settings saved.');
    } elseif ($act === 'delete') {
        db_exec('DELETE FROM delivery_zones WHERE id = ?', [$id]); flash('success', 'Area deleted.');
    } else {
        $d = ['city' => input('city'), 'area' => input('area'), 'delivery_fee' => input_float('delivery_fee', 0.0), 'free_delivery_over' => input_float('free_delivery_over', null),
            'min_order_amount' => input_float('min_order_amount', 0.0), 'estimated_time' => input('estimated_time') ?: null, 'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active')];
        if ($d['city'] === '' || $d['area'] === '') flash('danger', 'City and area are required.');
        elseif (db_val('SELECT id FROM delivery_zones WHERE city = ? AND area = ? AND id <> ?', [$d['city'], $d['area'], $id])) flash('danger', 'This area already exists.');
        else { $id ? db_update_row('delivery_zones', $d, 'id = ?', [$id]) : db_insert_row('delivery_zones', $d); flash('success', 'Delivery area saved.'); }
    }
    redirect('admin/delivery.php');
}
$zones = delivery_zones(false);
$z = input_int('edit') ? db_row('SELECT * FROM delivery_zones WHERE id = ?', [input_int('edit')]) : ['id' => 0, 'city' => setting('store_city', 'Karachi'), 'area' => '', 'delivery_fee' => '', 'free_delivery_over' => '', 'min_order_amount' => 0, 'estimated_time' => 'Same day', 'sort_order' => 0, 'is_active' => 1];
admin_header('Delivery Settings', 'delivery');
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-header">Delivery areas &amp; charges</div><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>City</th><th>Area</th><th class="text-end">Fee</th><th class="text-end">Free over</th><th class="text-end">Min order</th><th>ETA</th><th></th></tr></thead><tbody>
      <?php foreach ($zones as $r): ?><tr class="<?= $r['is_active'] ? '' : 'opacity-50' ?>"><td><?= e($r['city']) ?></td><td><strong><?= e($r['area']) ?></strong></td><td class="text-end"><?= money($r['delivery_fee']) ?></td><td class="text-end"><?= $r['free_delivery_over'] ? money($r['free_delivery_over']) : '—' ?></td><td class="text-end"><?= money($r['min_order_amount']) ?></td><td class="small"><?= e($r['estimated_time']) ?></td>
        <td class="text-end text-nowrap"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <form method="post" class="d-inline" data-confirm="Delete area?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <form method="post" class="card mb-3"><div class="card-header"><?= $z['id'] ? 'Edit area' : 'Add delivery area' ?></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="zone"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
      <div class="row"><div class="col-6"><?= af_text('city', 'City', $z['city'], ['required' => true]) ?></div><div class="col-6"><?= af_text('area', 'Area', $z['area'], ['required' => true]) ?></div>
      <div class="col-6"><?= af_text('delivery_fee', 'Delivery fee', $z['delivery_fee'], ['type' => 'number', 'min' => 0, 'required' => true]) ?></div><div class="col-6"><?= af_text('free_delivery_over', 'Free delivery over', $z['free_delivery_over'], ['type' => 'number', 'min' => 0]) ?></div>
      <div class="col-6"><?= af_text('min_order_amount', 'Min order', $z['min_order_amount'], ['type' => 'number', 'min' => 0]) ?></div><div class="col-6"><?= af_text('estimated_time', 'Delivery time', $z['estimated_time']) ?></div>
      <div class="col-6"><?= af_text('sort_order', 'Order', $z['sort_order'], ['type' => 'number']) ?></div><div class="col-6 pt-4"><?= af_check('is_active', 'Active', $z['is_active']) ?></div></div>
    </div><div class="card-footer bg-white"><button class="btn btn-brand">Save area</button></div></form>
    <form method="post" class="card"><div class="card-header">General delivery settings</div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="settings">
      <?= af_check('delivery_enabled', 'Home delivery enabled', setting('delivery_enabled', '1') === '1') ?>
      <?= af_text('default_delivery_fee', 'Default fee (when no area matches)', setting('default_delivery_fee', 150), ['type' => 'number', 'min' => 0]) ?>
      <?= af_text('free_delivery_over', 'Default free delivery over', setting('free_delivery_over', 0), ['type' => 'number', 'min' => 0]) ?>
      <?= af_text('min_order_amount', 'Store-wide minimum order', setting('min_order_amount', 0), ['type' => 'number', 'min' => 0]) ?>
      <?= af_textarea('delivery_note', 'Note shown at checkout', setting('delivery_note'), ['rows' => 2]) ?>
    </div><div class="card-footer bg-white"><button class="btn btn-brand">Save settings</button></div></form>
  </div>
</div>
<?php admin_footer();
