<?php if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!$orders): ?>
  <p class="text-muted">No orders yet. <a href="<?= e(url('shop')) ?>">Start shopping</a></p>
<?php else: ?>
<div class="table-responsive">
  <table class="table table-eb align-middle">
    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><strong><?= e($o['order_number']) ?></strong></td>
          <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td><?= status_badge($o['payment_status']) ?></td>
          <td class="text-end"><?= money($o['grand_total']) ?></td>
          <td class="text-end"><a href="<?= e(url('order/' . $o['order_number'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif;
