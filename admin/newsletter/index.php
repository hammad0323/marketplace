<?php
require __DIR__ . '/../includes/init.php';
admin_require('newsletter');
if (is_post()) {
    $id = input_int('id');
    $a = input('action', '', 'post');
    if ($a === 'unsubscribe') db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [$id]);
    if ($a === 'delete') db_exec('DELETE FROM newsletter_subscribers WHERE id = ?', [$id]);
    audit_log('subscriber_' . $a, 'newsletter', $id);
    redirect_back('admin/newsletter/');
}
$status = input('status', 'subscribed', 'get');
$q = input('q', '', 'get');
$where = 'status = ?';
$params = [$status === 'unsubscribed' ? 'unsubscribed' : 'subscribed'];
if ($q !== '') { $where .= ' AND email LIKE ?'; $params[] = '%' . $q . '%'; }
if (input('export', '', 'get') === 'csv') {
    audit_log('subscribers_exported', 'newsletter');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['email', 'status', 'source', 'consent_text', 'consent_at', 'unsubscribe_link']);
    foreach (db_all("SELECT * FROM newsletter_subscribers WHERE $where ORDER BY id", $params) as $s) {
        fputcsv($o, [$s['email'], $s['status'], $s['source'], $s['consent_text'], $s['consent_at'], url('newsletter/unsubscribe?token=' . $s['unsubscribe_token'])]);
    }
    exit;
}
$total = (int) db_val("SELECT COUNT(*) FROM newsletter_subscribers WHERE $where", $params);
$pg = paginate($total, 50);
$rows = db_all("SELECT * FROM newsletter_subscribers WHERE $where ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$unread = (int) db_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
admin_header('Newsletter subscribers');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link active" href="#">Subscribers</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/newsletter/messages.php')) ?>">Contact messages<?= $unread ? ' (' . $unread . ')' : '' ?></a></li></ul>
<form class="a-filters" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search email">
<select class="form-select" name="status"><option value="subscribed">Subscribed</option><option value="unsubscribed"<?= $status === 'unsubscribed' ? ' selected' : '' ?>>Unsubscribed</option></select><button class="btn btn-dark">Filter</button>
<a class="btn btn-outline-dark ms-auto" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-download"></i> Export CSV</a></form>
<div class="card a-card"><div class="card-header"><?= number_format($total) ?> <?= e($status) ?></div><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Email</th><th>Source</th><th>Consent</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $s): ?><tr><td><?= e($s['email']) ?></td><td><?= e($s['source']) ?></td><td class="small"><?= e($s['consent_text']) ?><div class="text-muted"><?= e($s['consent_at']) ?> · <?= e($s['consent_ip']) ?></div></td><td class="small"><?= e(format_date($s['created_at'])) ?></td>
<td class="text-end text-nowrap"><?php if ($s['status'] === 'subscribed'): ?><?= a_post_button(url('admin/newsletter/'), ['action' => 'unsubscribe', 'id' => $s['id']], 'Unsubscribe', 'btn btn-sm btn-light', 'Unsubscribe ' . $s['email'] . '?') ?><?php endif; ?>
<?= a_post_button(url('admin/newsletter/'), ['action' => 'delete', 'id' => $s['id']], '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger', 'Delete ' . $s['email'] . ' permanently (e.g. data removal request)?') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/newsletter/')) ?>
<?php admin_footer();
