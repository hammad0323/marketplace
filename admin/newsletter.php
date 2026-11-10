<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
if (is_post()) { csrf_check(); db_exec('DELETE FROM newsletter_subscribers WHERE id = ?', [input_int('id')]); redirect('admin/newsletter.php'); }
if (input('export') === '1') csv_download('newsletter.csv', ['Email', 'Subscribed'], array_map('array_values', db_all('SELECT email, created_at FROM newsletter_subscribers WHERE is_active = 1 ORDER BY id DESC')));
$pg = paginate((int) db_val('SELECT COUNT(*) FROM newsletter_subscribers'), 50);
$rows = db_all('SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 50 OFFSET ' . $pg['offset']);
admin_header('Newsletter Subscribers', 'newsletter');
?>
<div class="d-flex justify-content-between mb-2"><span class="text-muted small"><?= $pg['total'] ?> subscribers</span><a href="?export=1" class="btn btn-sm btn-outline-secondary">Export CSV</a></div>
<div class="card"><table class="table mb-0"><thead><tr><th>Email</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['email']) ?></td><td><?= fmt_datetime($r['created_at']) ?></td><td class="text-end"><form method="post" data-confirm="Remove subscriber?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div><div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
