<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('shipping.manage');
if (is_post()) {
    require_csrf();
    if (input('action') === 'delete_zone') {
        $id = input_int('id');
        db_exec('UPDATE orders SET shipping_zone_id = NULL WHERE shipping_zone_id = ?', [$id]);
        db_exec('DELETE FROM shipping_zones WHERE id = ?', [$id]);
        audit_log('shipping_zone_deleted', 'shipping_zone', $id);
        flash('success', 'Zone deleted.');
        redirect(admin_url('shipping'));
    }
    save_setting('shipping_default_rate', (string) max(0, (float) input_float('shipping_default_rate', 0)), 'shipping');
    save_setting('shipping_default_estimate', mb_substr(input('shipping_default_estimate'), 0, 60), 'shipping');
    save_setting('free_shipping_enabled', input_bool('free_shipping_enabled') ? '1' : '0', 'shipping');
    save_setting('free_shipping_threshold', (string) max(0, (float) input_float('free_shipping_threshold', 0)), 'shipping');
    save_setting('minimum_order_amount', (string) max(0, (float) input_float('minimum_order_amount', 0)), 'shipping');
    save_setting('express_enabled', input_bool('express_enabled') ? '1' : '0', 'shipping');
    save_setting('checkout_regions', mb_substr(input('checkout_regions'), 0, 2000), 'shipping');
    audit_log('shipping_settings_updated', 'settings');
    flash('success', 'Delivery settings saved.');
    redirect(admin_url('shipping'));
}
$zones = db_all('SELECT z.*, (SELECT GROUP_CONCAT(COALESCE(NULLIF(city, \'\'), CONCAT(\'[\', region, \']\')) SEPARATOR \', \') FROM shipping_zone_locations l WHERE l.zone_id = z.id) locations FROM shipping_zones z ORDER BY sort_order, name');
$rates = [];
foreach (db_all('SELECT * FROM shipping_rates ORDER BY method') as $r) {
    $rates[(int) $r['zone_id']][] = $r;
}
admin_header('Shipping & delivery', 'shipping');
?>
<div class="row g-3">
  <div class="col-xl-5"><div class="card"><div class="card-header">Delivery rules</div><div class="card-body">
    <form method="post"><?= csrf_field() ?>
      <?= f_number('shipping_default_rate', 'Default delivery charge (when no zone matches and no default zone exists)', setting('shipping_default_rate'), ['min' => 0]) ?>
      <?= f_text('shipping_default_estimate', 'Default delivery estimate', setting('shipping_default_estimate')) ?>
      <?= f_check('free_shipping_enabled', 'Free standard delivery above a threshold', setting_bool('free_shipping_enabled', true)) ?>
      <?= f_number('free_shipping_threshold', 'Free delivery threshold (order subtotal after discount)', setting('free_shipping_threshold'), ['min' => 0], 'A zone can override this with its own "free over" amount.') ?>
      <?= f_number('minimum_order_amount', 'Minimum order value', setting('minimum_order_amount'), ['min' => 0], '0 = no minimum.') ?>
      <?= f_check('express_enabled', 'Offer express delivery where configured', setting_bool('express_enabled', true)) ?>
      <?= f_textarea('checkout_regions', 'Provinces / regions at checkout (one per line)', setting('checkout_regions'), ['rows' => 4], 'Blank = Pakistani provinces & territories.') ?>
      <?= f_submit() ?>
    </form>
    <hr><p class="small text-muted mb-0">Cash-on-delivery fee and order limit are configured under <a href="<?= e(admin_url('payments')) ?>">Payments → Cash on Delivery</a>. COD can be switched off per zone. Shipping policy text lives in <a href="<?= e(admin_url('pages')) ?>">Pages</a>.</p>
  </div></div></div>
  <div class="col-xl-7">
    <div class="d-flex justify-content-between mb-2"><h2 class="h6 mb-0 align-self-center">Delivery zones</h2><a class="btn btn-primary btn-sm" href="<?= e(admin_url('shipping-zone')) ?>"><i class="bi bi-plus-lg"></i> Add zone</a></div>
    <p class="small text-muted">At checkout the customer's city is matched first, then the province; otherwise the zone marked <em>default</em> applies.</p>
    <?php foreach ($zones as $z): ?>
      <div class="card mb-2"><div class="card-body">
        <div class="d-flex justify-content-between gap-2"><div><strong><?= e($z['name']) ?></strong> <?= $z['is_default'] ? '<span class="badge text-bg-primary">Default</span>' : '' ?> <?= $z['is_active'] ? '' : '<span class="badge text-bg-secondary">Inactive</span>' ?> <?= $z['cod_available'] ? '<span class="badge text-bg-light">COD</span>' : '<span class="badge text-bg-warning">No COD</span>' ?><br><small class="text-muted"><?= e($z['locations'] ?: ($z['is_default'] ? 'All other locations' : 'No locations')) ?></small></div>
          <div class="text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(admin_url('shipping-zone', ['id' => $z['id']])) ?>"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="Delete this zone and its rates?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_zone"><input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div></div>
        <?php foreach ($rates[(int) $z['id']] ?? [] as $r): ?>
          <div class="small mt-2"><span class="badge text-bg-light"><?= e(ucfirst($r['method'])) ?></span> <?= e($r['name']) ?> — <?= e(money($r['rate'])) ?><?= $r['free_over'] !== null ? ' · free over ' . e(money($r['free_over'])) : '' ?> · <?= (int) $r['min_days'] ?>–<?= (int) $r['max_days'] ?> days<?= $r['is_active'] ? '' : ' (inactive)' ?></div>
        <?php endforeach; ?>
      </div></div>
    <?php endforeach; ?>
  </div>
</div>
<?php admin_footer();
