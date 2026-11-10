<?php
require __DIR__ . '/includes/init.php';
admin_require('inventory');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') { db_exec('UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = ?', [$id]); flash('success', 'Supplier removed.'); redirect('admin/suppliers.php'); }
    if (input('name') === '') { flash('danger', 'Name is required.'); redirect('admin/suppliers.php'); }
    $d = ['name' => mb_substr(input('name'), 0, 150), 'contact_person' => input('contact_person') ?: null, 'phone' => input('phone') ?: null, 'email' => input('email') ?: null,
        'address' => input('address') ?: null, 'notes' => input('notes') ?: null, 'is_active' => input_int('is_active')];
    if ($id) db_update_row('suppliers', $d, 'id = ?', [$id]); else db_insert_row('suppliers', $d);
    flash('success', 'Supplier saved.');
    redirect('admin/suppliers.php');
}
$rows = db_all('SELECT s.*, (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) AS purchases, (SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE supplier_id = s.id) AS total FROM suppliers s WHERE s.deleted_at IS NULL ORDER BY s.name');
$e = input_int('edit') ? db_row('SELECT * FROM suppliers WHERE id = ?', [input_int('edit')]) : null;
$s = $e ?: ['id' => 0, 'name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => '', 'notes' => '', 'is_active' => 1];
admin_header('Suppliers', 'suppliers');
?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Supplier</th><th>Contact</th><th>Purchases</th><th class="text-end">Total</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e($r['address']) ?></small></td><td class="small"><?= e($r['contact_person']) ?><br><?= e($r['phone']) ?></td>
      <td><a href="<?= aurl('purchases.php?supplier=' . $r['id']) ?>"><?= (int) $r['purchases'] ?></a></td><td class="text-end"><?= money($r['total']) ?></td>
      <td class="text-end text-nowrap"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <form method="post" class="d-inline" data-confirm="Remove supplier?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-muted p-4 text-center">No suppliers yet.</td></tr><?php endif; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-4"><form method="post" class="card"><div class="card-header"><?= $s['id'] ? 'Edit supplier' : 'Add supplier' ?></div><div class="card-body">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    <?= af_text('name', 'Name *', $s['name'], ['required' => true]) ?><?= af_text('contact_person', 'Contact person', $s['contact_person']) ?><?= af_text('phone', 'Phone', $s['phone']) ?>
    <?= af_text('email', 'Email', $s['email'], ['type' => 'email']) ?><?= af_text('address', 'Address', $s['address']) ?><?= af_textarea('notes', 'Notes', $s['notes'], ['rows' => 2]) ?><?= af_check('is_active', 'Active', $s['is_active']) ?>
  </div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form></div>
</div>
<?php admin_footer();
