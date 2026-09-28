<?php
require __DIR__ . '/inc.php';
require_admin();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'delete') {
    q('DELETE FROM subscribers WHERE id = ?', [(int) $_POST['id']]);
    flash('success', 'Subscriber removed.');
    redirect('admin/subscribers.php');
}
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed']);
    foreach (rows('SELECT email, created_at FROM subscribers ORDER BY created_at DESC') as $r) {
        fputcsv($out, [preg_match('~^[=+\-@]~', $r['email']) ? "'" . $r['email'] : $r['email'], $r['created_at']]);
    }
    exit;
}
$list = rows('SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 1000');
admin_header('Newsletter Subscribers', 'subscribers');
?>
<div class="page-actions">
  <p class="muted" style="margin:0"><?= count($list) ?> subscriber<?= count($list) === 1 ? '' : 's' ?></p>
  <a class="btn btn-light" href="?export=1"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
</div>
<div class="card flush">
  <?php if (!$list): ?><p class="empty">No subscribers yet. Visitors can subscribe from the website footer.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Email</th><th>Subscribed</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td>
        <td class="muted"><?= e(date('M j, Y g:ia', strtotime($r['created_at']))) ?></td>
        <td class="right"><form method="post" class="inline" data-confirm="Remove this subscriber?"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" title="Remove"><i class="fa-solid fa-trash"></i></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php
admin_footer();
