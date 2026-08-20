<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $oldUrl = tp_sanitize_text($_POST['old_url'] ?? '', 255);
    $newUrl = tp_sanitize_text($_POST['new_url'] ?? '', 255);
    $type = (int) ($_POST['redirect_type'] ?? 301);
    if ($oldUrl !== '' && $newUrl !== '') {
        if (!str_starts_with($oldUrl, '/')) { $oldUrl = '/' . $oldUrl; }
        tp_execute('INSERT INTO redirects (old_url, new_url, redirect_type) VALUES (?,?,?)', 'ssi', [$oldUrl, $newUrl, $type]);
        tp_flash_set('success', 'Redirect created.');
    }
    header('Location: ' . tp_url('admin/redirects.php'));
    exit;
}

if (isset($_GET['toggle'])) {
    $rid = (int) $_GET['toggle'];
    $r = tp_query_one('SELECT status FROM redirects WHERE id = ?', 'i', [$rid]);
    if ($r) {
        $newStatus = $r['status'] === 'active' ? 'inactive' : 'active';
        tp_execute('UPDATE redirects SET status = ? WHERE id = ?', 'si', [$newStatus, $rid]);
    }
    header('Location: ' . tp_url('admin/redirects.php'));
    exit;
}
if (isset($_GET['delete'])) {
    tp_execute('DELETE FROM redirects WHERE id = ?', 'i', [(int) $_GET['delete']]);
    header('Location: ' . tp_url('admin/redirects.php'));
    exit;
}

$redirects = tp_query('SELECT * FROM redirects ORDER BY created_at DESC');
$adminPageTitle = 'Redirects';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-card mb-3">
  <h3 class="h6 fw-bold">Add 301 Redirect</h3>
  <p class="text-muted small">Slug changes on tools/categories/pages/posts create these automatically — use this form for manual redirects (e.g. an old external URL). URLs are extension-less and relative to the site root (e.g. "/old-slug", not "/old-slug.php") — they're matched after the base folder and any ".php" are stripped from the incoming request.</p>
  <form method="post" class="row g-2">
    <?= tp_csrf_field() ?>
    <div class="col-md-4"><input type="text" name="old_url" class="form-control" placeholder="/old-slug" required></div>
    <div class="col-md-4"><input type="text" name="new_url" class="form-control" placeholder="/new-slug" required></div>
    <div class="col-md-2">
      <select name="redirect_type" class="form-select">
        <option value="301">301 (Permanent)</option>
        <option value="302">302 (Temporary)</option>
      </select>
    </div>
    <div class="col-md-2"><button class="btn tp-btn-calc">Add</button></div>
  </form>
</div>
<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Old URL</th><th>New URL</th><th>Type</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($redirects as $r): ?>
      <tr>
        <td><?= e($r['old_url']) ?></td>
        <td><?= e($r['new_url']) ?></td>
        <td><?= (int) $r['redirect_type'] ?></td>
        <td><span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
        <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
        <td>
          <a href="?toggle=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-secondary">Toggle</a>
          <a href="?delete=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
