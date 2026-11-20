<?php
/** Print sheet for any saved design (linked from orders and requests). */
require __DIR__ . '/../includes/init.php';
admin_require('orders');
require __DIR__ . '/sheet.inc.php';
$design = design_by_token(input('token', '', 'get'));
if (!$design) {
    flash('error', 'Design not found.');
    redirect('admin/designs/');
}
$orders = db_all("SELECT DISTINCT o.id, o.order_number FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.is_custom = 2 AND oi.custom_spec LIKE ?", ['%' . $design['token'] . '%']);
$request = db_row('SELECT id, reference FROM design_requests WHERE design_id = ?', [(int) $design['id']]);
admin_header('Design print sheet');
?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <span class="text-muted small">Saved <?= e(format_datetime($design['created_at'])) ?></span>
    <?php foreach ($orders as $o): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/orders/view.php?id=' . $o['id'])) ?>"><i class="bi bi-receipt"></i> Order <?= e($o['order_number']) ?></a><?php endforeach; ?>
    <?php if ($request && admin_can('design_studio')): ?><a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/designs/view.php?id=' . $request['id'])) ?>"><i class="bi bi-chat-square-text"></i> Request <?= e($request['reference']) ?></a><?php endif; ?>
    <button type="button" class="btn btn-sm btn-dark ms-auto" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>
<div class="card a-card"><div class="card-body"><?php admin_design_sheet($design); ?></div></div>
<?php admin_footer();
