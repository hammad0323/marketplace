<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('newsletter.manage');
if (is_post()) {
    require_csrf();
    $id = input_int('id');
    if (input('action') === 'unsubscribe') {
        db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [$id]);
    } elseif (input('action') === 'delete') {
        db_exec('DELETE FROM newsletter_subscribers WHERE id = ?', [$id]);
    }
    audit_log('newsletter_' . input('action'), 'newsletter', $id);
    flash('success', 'Subscriber updated.');
    admin_back('newsletter');
}
$status = input('status', 'subscribed', 'get');
if (input('export', '', 'get') === '1') {
    $rows = db_all("SELECT email, status, source, consent_text, subscribed_at, unsubscribed_at FROM newsletter_subscribers WHERE status = 'subscribed' ORDER BY subscribed_at");
    audit_log('newsletter_export', 'newsletter', null, ['count' => count($rows)]);
    csv_download('newsletter-' . date('Ymd') . '.csv', ['Email', 'Status', 'Source', 'Consent text', 'Subscribed at', 'Unsubscribed at'], array_map('array_values', $rows));
}
$q = input('q', '', 'get');
$params = [];
$where = in_array($status, ['subscribed', 'unsubscribed'], true) ? 'status = ?' : '1=1';
if ($where !== '1=1') {
    $params[] = $status;
}
if ($q !== '') {
    $where .= ' AND email LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
$total = (int) db_val("SELECT COUNT(*) FROM newsletter_subscribers WHERE $where", $params);
$pg = paginate($total, 50, input_int('page', 1, 'get'));
$rows = db_all("SELECT * FROM newsletter_subscribers WHERE $where ORDER BY subscribed_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$counts = db_one("SELECT SUM(status = 'subscribed') s, SUM(status = 'unsubscribed') u FROM newsletter_subscribers");
admin_header('Newsletter', 'newsletter');
?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <ul class="nav nav-pills"><li class="nav-item"><a class="nav-link<?= $status === 'subscribed' ? ' active' : '' ?>" href="?status=subscribed">Subscribed (<?= (int) $counts['s'] ?>)</a></li><li class="nav-item"><a class="nav-link<?= $status === 'unsubscribed' ? ' active' : '' ?>" href="?status=unsubscribed">Unsubscribed (<?= (int) $counts['u'] ?>)</a></li></ul>
  <form class="d-flex gap-2 ms-auto"><input type="hidden" name="status" value="<?= e($status) ?>"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search email"><button class="btn btn-sm btn-outline-primary">Search</button></form>
  <a class="btn btn-sm btn-primary" href="?export=1"><i class="bi bi-download"></i> Export subscribed (CSV)</a>
</div>
<p class="small text-muted">Every subscriber records the exact consent wording and IP at sign-up. Each welcome email includes a one-click unsubscribe link; include it in any campaign you send from your email platform.</p>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Email</th><th>Status</th><th>Source</th><th>Consent</th><th>Date</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $s): ?>
    <tr><td><?= e($s['email']) ?></td><td><?= badge($s['status']) ?></td><td><?= e($s['source']) ?></td><td><small class="text-muted"><?= e(excerpt($s['consent_text'], 60)) ?></small></td><td><small><?= e(format_date($s['subscribed_at'])) ?><?= $s['unsubscribed_at'] ? ' → ' . e(format_date($s['unsubscribed_at'])) : '' ?></small></td>
      <td class="text-end text-nowrap"><?php if ($s['status'] === 'subscribed'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="unsubscribe"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-light">Unsubscribe</button></form><?php endif; ?>
        <form method="post" class="d-inline" data-confirm="Permanently delete this subscriber record?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No subscribers.</td></tr><?php endif; ?></tbody>
</table></div><div class="card-body border-top"><?= admin_pager($pg) ?></div></div>
<?php admin_footer();
