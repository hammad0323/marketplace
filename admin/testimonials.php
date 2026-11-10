<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') { db_exec('DELETE FROM testimonials WHERE id = ?', [$id]); redirect('admin/testimonials.php'); }
    $d = ['customer_name' => input('customer_name'), 'location' => input('location') ?: null, 'message' => mb_substr(input('message'), 0, 600), 'rating' => max(1, min(5, input_int('rating', 5))), 'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active')];
    if ($d['customer_name'] === '' || $d['message'] === '') flash('danger', 'Name and message are required.');
    else { $id ? db_update_row('testimonials', $d, 'id = ?', [$id]) : db_insert_row('testimonials', $d); flash('success', 'Saved.'); }
    redirect('admin/testimonials.php');
}
$rows = db_all('SELECT * FROM testimonials ORDER BY sort_order, id');
$t = input_int('edit') ? db_row('SELECT * FROM testimonials WHERE id = ?', [input_int('edit')]) : ['id' => 0, 'customer_name' => '', 'location' => '', 'message' => '', 'rating' => 5, 'sort_order' => 0, 'is_active' => 1];
admin_header('Testimonials', 'testimonials');
?>
<div class="row g-3"><div class="col-lg-7"><div class="card"><table class="table mb-0 align-middle"><tbody>
<?php foreach ($rows as $r): ?><tr class="<?= $r['is_active'] ? '' : 'opacity-50' ?>"><td><strong><?= e($r['customer_name']) ?></strong> <small class="text-muted"><?= e($r['location']) ?></small> <span class="text-warning"><?= str_repeat('★', (int) $r['rating']) ?></span><br><small><?= e($r['message']) ?></small></td>
  <td class="text-end text-nowrap"><a href="?edit=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <form method="post" class="d-inline" data-confirm="Delete?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="col-lg-5"><form method="post" class="card"><div class="card-header"><?= $t['id'] ? 'Edit' : 'Add' ?> testimonial</div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
  <?= af_text('customer_name', 'Customer name', $t['customer_name'], ['required' => true]) ?><?= af_text('location', 'Area / city', $t['location']) ?><?= af_textarea('message', 'Message', $t['message'], ['required' => true, 'maxlength' => 600]) ?>
  <div class="row"><div class="col"><?= af_select('rating', 'Rating', [5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1'], $t['rating']) ?></div><div class="col"><?= af_text('sort_order', 'Order', $t['sort_order'], ['type' => 'number']) ?></div></div><?= af_check('is_active', 'Visible', $t['is_active']) ?>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form></div></div>
<?php admin_footer();
