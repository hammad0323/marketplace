<?php
$pageTitle = 'Newsletter';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'delete') {
        mysqli_query($mysqli, "DELETE FROM newsletter_subscribers WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Subscriber removed.');
    }
    redirect('newsletter.php');
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed At']);
    $res = mysqli_query($mysqli, "SELECT email, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC");
    while ($r = mysqli_fetch_assoc($res)) fputcsv($out, [$r['email'], $r['subscribed_at']]);
    fclose($out);
    exit;
}

$subscribers = mysqli_query($mysqli, "SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Newsletter Subscribers</h1>
  <a href="?export=csv" class="btn btn-outline-dark"><i class="bi bi-download"></i> Export CSV</a>
</div>
<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th>Email</th><th>Subscribed</th><th></th></tr></thead>
    <tbody>
    <?php while ($s = mysqli_fetch_assoc($subscribers)): ?>
      <tr>
        <td><?= e($s['email']) ?></td>
        <td><?= e(date('d M Y', strtotime($s['subscribed_at']))) ?></td>
        <td class="text-end"><form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
