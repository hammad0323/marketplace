<?php if (!defined('FS_BOOT')) { http_response_code(403); exit; } ?>
<?php if (!$recent): ?><p class="text-muted small mb-0">No orders yet. <a href="<?= url('shop/') ?>">Start shopping</a></p><?php else: ?>
<div class="table-responsive">
<table class="table align-middle small mb-0">
  <thead><tr><th>Order</th><th>Date</th><th>Type</th><th>Total</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($recent as $o): ?>
  <tr>
    <td class="fw-semibold"><?= e($o['order_number']) ?></td>
    <td><?= fmt_date($o['created_at']) ?></td>
    <td><?= $o['order_type'] === 'rashan' ? '<span class="badge bg-success-subtle text-success">Rashan</span> ' : '' ?><?= $o['fulfillment'] === 'pickup' ? 'Pickup' : ($o['fulfillment'] === 'walkin' ? 'In store' : 'Delivery') ?></td>
    <td><?= money($o['total']) ?></td>
    <td><?= status_badge($o['status']) ?></td>
    <td class="text-end"><a href="<?= url('my-account/order/' . $o['order_number'] . '/') ?>" class="btn btn-sm btn-outline-brand">View</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
