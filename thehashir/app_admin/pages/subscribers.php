<?php
/** /manage/subscribers — newsletter sign-ups (storage/subscribers.json) with CSV export. */
require_admin('settings');
$list = storage_read('subscribers.json', array());
if (is_post()) {
    require_post_csrf();
    $email = strtolower(post('email'));
    if (isset($list[$email])) {
        unset($list[$email]);
        storage_write('subscribers.json', $list);
        log_me('subscriber.delete', 'subscriber', null, array('email' => $email), null);
        flash('success', 'Removed.');
    }
    bo_redirect('admin', 'subscribers');
}
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('email', 'subscribed', 'city_id', 'page'), ',', '"', '\\');
    foreach ($list as $em => $r) {
        fputcsv($out, array($em, $r['date'], $r['city'], $r['page']), ',', '"', '\\');
    }
    exit;
}
uasort($list, function ($a, $b) { return strcmp($b['date'], $a['date']); });
bo_start('admin', 'Newsletter subscribers');
?>
<div class="d-flex mb-3"><span class="text-muted"><?= number_format(count($list)) ?> subscribers</span><a class="btn btn-outline-primary btn-sm ms-auto" href="<?= e(ADMIN_SELF . '?export=1') ?>"><i class="bi bi-download me-1"></i>Export CSV</a></div>
<div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0 small">
  <thead><tr><th>Email</th><th>Subscribed</th><th>City</th><th></th></tr></thead><tbody>
  <?php if (!$list): ?><tr><td colspan="4" class="text-muted p-3">No subscribers yet.</td></tr><?php endif; ?>
  <?php foreach (array_slice($list, 0, 500, true) as $em => $r): $c = $r['city'] ? get_city((int)$r['city']) : null; ?>
    <tr><td><?= e($em) ?></td><td><?= e($r['date']) ?></td><td><?= e($c ? $c['city_title'] : '') ?></td><td class="text-end"><button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('email' => $em))) ?>' data-confirm-text="Remove this subscriber?"><i class="bi bi-trash"></i></button></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php bo_end();
