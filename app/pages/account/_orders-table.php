<div class="table-responsive">
<table class="table table-lux align-middle">
  <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><strong><?= e($o['order_number']) ?></strong></td>
      <td><?= e(format_date($o['created_at'])) ?></td>
      <td><span class="badge text-bg-<?= e(status_color($o['status'])) ?>"><?= e(status_label($o['status'])) ?></span></td>
      <td><?= e(payment_method_label($o['payment_method'])) ?><br><small class="text-muted"><?= e(status_label($o['payment_status'])) ?></small></td>
      <td class="text-end"><?= e(money($o['grand_total'])) ?></td>
      <td class="text-end"><a class="link-arrow" href="<?= e(path_url('account/orders/' . $o['order_number'])) ?>">View <i class="bi bi-arrow-right"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
