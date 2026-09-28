<?php
require __DIR__ . '/inc.php';
require_admin();
require_csrf();

$statuses = ['new' => 'New', 'contacted' => 'Contacted', 'in_progress' => 'In progress', 'won' => 'Won', 'lost' => 'Lost'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'update' && $id) {
        $st = array_key_exists($_POST['status'] ?? '', $statuses) ? $_POST['status'] : 'new';
        q('UPDATE inquiries SET status = ?, notes = ? WHERE id = ?', [$st, trim((string) ($_POST['notes'] ?? '')), $id]);
        flash('success', 'Inquiry updated.');
        redirect('admin/inquiries.php?view=' . $id);
    }
    if (($_POST['do'] ?? '') === 'delete' && $id) {
        q('DELETE FROM inquiries WHERE id = ?', [$id]);
        flash('success', 'Inquiry deleted.');
        redirect('admin/inquiries.php');
    }
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inquiries-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Date', 'Name', 'Email', 'Phone', 'Company', 'Service', 'Package', 'Budget', 'Status', 'Message', 'Notes']);
    foreach (rows('SELECT * FROM inquiries ORDER BY created_at DESC') as $r) {
        $line = [$r['id'], $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['company'], $r['service'], $r['package_name'], $r['budget'], $r['status'], $r['message'], $r['notes']];
        // Guard against spreadsheet formula injection.
        fputcsv($out, array_map(fn($v) => preg_match('~^[=+\-@]~', (string) $v) ? "'" . $v : $v, $line));
    }
    exit;
}

/* ----- single inquiry ----- */
if (!empty($_GET['view'])) {
    $r = row('SELECT * FROM inquiries WHERE id = ?', [(int) $_GET['view']]);
    if (!$r) {
        redirect('admin/inquiries.php');
    }
    admin_header('Inquiry #' . $r['id'], 'inquiries');
    $wa = preg_replace('~\D~', '', (string) $r['phone']);
    ?>
    <div class="page-actions">
      <a href="inquiries.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> All inquiries</a>
      <div class="inline-actions">
        <a class="btn btn-primary" href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Re: your inquiry — ' . setting('site_name')) ?>"><i class="fa-solid fa-reply"></i> Reply by email</a>
        <?php if ($wa): ?><a class="btn btn-light" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
      </div>
    </div>
    <div class="edit-grid">
      <div class="card">
        <div class="inq-head">
          <span class="avatar lg"><?= e(initials($r['name'])) ?></span>
          <div><h2><?= e($r['name']) ?></h2><p class="muted"><?= e(date('l, M j, Y \a\t g:i a', strtotime($r['created_at']))) ?></p></div>
          <?= status_badge($r['status']) ?>
        </div>
        <?php if ($r['package_name']): ?><div class="pkg-callout"><i class="fa-solid fa-tags"></i> Ordered package: <strong><?= e($r['package_name']) ?></strong></div><?php endif; ?>
        <dl class="details">
          <dt>Email</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd>
          <dt>Phone</dt><dd><?= e($r['phone'] ?: '—') ?></dd>
          <dt>Company</dt><dd><?= e($r['company'] ?: '—') ?></dd>
          <dt>Service</dt><dd><?= e($r['service'] ?: '—') ?></dd>
          <dt>Budget</dt><dd><?= e($r['budget'] ?: '—') ?></dd>
          <dt>IP address</dt><dd class="muted"><?= e($r['ip'] ?: '—') ?></dd>
        </dl>
        <h4>Message</h4>
        <div class="message"><?= nl2br(e($r['message'] ?: '(no message)')) ?></div>
      </div>
      <div>
        <form method="post" class="card sticky">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <div class="field"><label>Status</label>
            <select name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </div>
          <div class="field"><label>Private notes</label><textarea name="notes" rows="6" placeholder="Call notes, quote sent, next steps…"><?= e($r['notes']) ?></textarea></div>
          <div class="form-buttons"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>
        </form>
        <form method="post" data-confirm="Delete this inquiry permanently?" class="card">
          <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-danger btn-block" type="submit"><i class="fa-solid fa-trash"></i> Delete inquiry</button>
        </form>
      </div>
    </div>
    <?php
    admin_footer();
    exit;
}

/* ----- list ----- */
$status = array_key_exists($_GET['status'] ?? '', $statuses) ? $_GET['status'] : '';
$search = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];
if ($status) { $where[] = 'status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ? OR package_name LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $search . '%'));
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$perPage = 30;
$pageNo = max(1, (int) ($_GET['p'] ?? 1));
$total = (int) val("SELECT COUNT(*) FROM inquiries $w", $params);
$pages = max(1, (int) ceil($total / $perPage));
$list = rows("SELECT * FROM inquiries $w ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($pageNo - 1) * $perPage), $params);
$counts = [];
foreach (rows('SELECT status, COUNT(*) n FROM inquiries GROUP BY status') as $c) { $counts[$c['status']] = (int) $c['n']; }

admin_header('Inquiries & Orders', 'inquiries');
?>
<div class="tabs">
  <a href="inquiries.php" class="<?= $status === '' ? 'on' : '' ?>">All <b><?= array_sum($counts) ?></b></a>
  <?php foreach ($statuses as $k => $l): ?><a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($l) ?> <b><?= $counts[$k] ?? 0 ?></b></a><?php endforeach; ?>
</div>
<div class="page-actions">
  <form class="search" method="get">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone, package…">
  </form>
  <a class="btn btn-light" href="?export=1"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
</div>
<div class="card flush">
  <?php if (!$list): ?><p class="empty">No inquiries found.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Contact</th><th>Interested in</th><th>Budget</th><th>Status</th><th>Received</th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr class="clickable<?= $r['status'] === 'new' ? ' unread' : '' ?>" onclick="location.href='?view=<?= (int) $r['id'] ?>'">
        <td><strong><?= e($r['name']) ?></strong><?php if ($r['company']): ?><br><small class="muted"><?= e($r['company']) ?></small><?php endif; ?></td>
        <td><?= e($r['email']) ?><br><small class="muted"><?= e($r['phone']) ?></small></td>
        <td><?= e($r['package_name'] ?: ($r['service'] ?: '—')) ?></td>
        <td><?= e($r['budget'] ?: '—') ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="muted nowrap"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php if ($pages > 1): ?>
  <div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $pageNo ? 'on' : '' ?>" href="?<?= e(http_build_query(array_filter(['status' => $status, 'q' => $search, 'p' => $i]))) ?>"><?= $i ?></a><?php endfor; ?></div>
<?php endif; ?>
<?php
admin_footer();
