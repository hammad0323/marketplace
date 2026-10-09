<?php
/** Newsletter subscribers (with consent records) and contact messages. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('newsletter.manage');
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'unsubscribe') db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [(int)post('id')]);
    if ($act === 'delete_sub') db_exec('DELETE FROM newsletter_subscribers WHERE id = ?', [(int)post('id')]);
    if ($act === 'msg_status') db_exec('UPDATE contact_messages SET status = ? WHERE id = ?', [in_list(post('status'), ['new', 'read', 'replied', 'archived'], 'read'), (int)post('id')]);
    audit('newsletter_' . $act, 'newsletter', (int)post('id'));
    redirect(admin_url('newsletter' . query_with([])));
}
if (get('export') === 'csv' && can('reports.export')) {
    audit('newsletter_export', 'newsletter');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ebaya-subscribers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Consent text', 'Consent date', 'Unsubscribe link']);
    foreach (db_all("SELECT * FROM newsletter_subscribers WHERE status = 'subscribed' ORDER BY id") as $s) {
        fputcsv($out, [$s['email'], $s['consent_text'], $s['consent_at'], abs_url('newsletter/unsubscribe?token=' . $s['unsubscribe_token'])]);
    }
    exit;
}
$tab = get('tab') === 'messages' ? 'messages' : 'subscribers';
$subs = db_all('SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 500');
$msgs = db_all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 200');
$admin_title = 'Newsletter & messages';
require __DIR__ . '/partials/header.php';
?>
<ul class="nav nav-tabs mb-3"><li class="nav-item"><a class="nav-link<?= $tab === 'subscribers' ? ' active' : '' ?>" href="?tab=subscribers">Subscribers (<?= (int)db_val("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'") ?>)</a></li>
<li class="nav-item"><a class="nav-link<?= $tab === 'messages' ? ' active' : '' ?>" href="?tab=messages">Contact messages (<?= (int)db_val("SELECT COUNT(*) FROM contact_messages WHERE status='new'") ?> new)</a></li></ul>
<?php if ($tab === 'subscribers'): ?>
<div class="d-flex mb-2"><span class="small text-muted">Each export row includes the subscriber's personal unsubscribe link — include it in every marketing email.</span><?php if (can('reports.export')): ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="?export=csv"><i class="bi bi-download"></i> Export subscribed</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Email</th><th>Status</th><th>Consent</th><th>Source</th><th></th></tr></thead><tbody>
  <?php foreach ($subs as $s): ?><tr><td><?= e($s['email']) ?></td><td><?= status_badge($s['status']) ?></td><td class="small"><?= e($s['consent_at']) ?> · <?= e($s['consent_ip']) ?><div class="text-muted"><?= e(str_limit($s['consent_text'], 80)) ?></div></td><td class="small"><?= e($s['source']) ?></td>
    <td class="text-nowrap"><?php if ($s['status'] === 'subscribed'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="unsubscribe"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light">Unsubscribe</button></form><?php endif; ?>
      <form method="post" class="d-inline" data-confirm="Permanently delete this subscriber record?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_sub"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php else: ?>
<?php foreach ($msgs as $m): ?><div class="card mb-2"><div class="card-body">
  <div class="d-flex"><strong><?= e($m['name']) ?></strong>&nbsp;<a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?? 'Your enquiry')) ?>"><?= e($m['email']) ?></a>&nbsp;<?= e($m['phone']) ?><span class="ms-auto small text-muted"><?= e($m['created_at']) ?></span></div>
  <div class="small text-muted"><?= e($m['subject']) ?></div><p class="mb-2"><?= nl2br(e($m['message'])) ?></p>
  <form method="post" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="msg_status"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><?= status_badge($m['status']) ?>
    <?php foreach (['read', 'replied', 'archived'] as $s): ?><button class="btn btn-sm btn-light" name="status" value="<?= $s ?>">Mark <?= $s ?></button><?php endforeach; ?></form>
</div></div><?php endforeach; if (!$msgs): ?><p class="text-muted">No messages yet.</p><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php';
