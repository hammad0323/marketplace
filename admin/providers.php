<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$validActions = ['approve', 'reject', 'suspend', 'block', 'reactivate', 'verify', 'unverify', 'feature', 'unfeature', 'delete'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $providerId = (int) ($_POST['provider_id'] ?? 0);

    if (!in_array($action, $validActions, true) || !$providerId) {
        flash_set('danger', 'Invalid action.');
        redirect('/admin/providers.php');
    }

    $provider = db_select_one($conn, 'SELECT * FROM providers WHERE id = ?', [$providerId]);
    if (!$provider) {
        flash_set('danger', 'Provider not found.');
        redirect('/admin/providers.php');
    }

    switch ($action) {
        case 'approve':
            db_execute($conn, 'UPDATE providers SET status = "approved" WHERE id = ?', [$providerId]);
            db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "provider_approved", "You are approved!", ?, "/provider/index.php")', [(int) $provider['user_id'], 'Your business is now live on ' . get_setting($conn, 'site_name', APP_NAME) . '.']);
            $providerOwner = db_select_one($conn, 'SELECT name, email FROM users WHERE id = ?', [(int) $provider['user_id']]);
            if ($providerOwner) {
                send_email($conn, $providerOwner['email'], $providerOwner['name'], 'provider_approved', ['name' => $providerOwner['name'], 'business_name' => $provider['business_name']]);
            }
            $msg = 'Provider approved.';
            break;
        case 'reject':
            db_execute($conn, 'UPDATE providers SET status = "rejected" WHERE id = ?', [$providerId]);
            db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "provider_rejected", "Application update", "Your provider application was not approved.", "/provider/index.php")', [(int) $provider['user_id']]);
            $msg = 'Provider rejected.';
            break;
        case 'suspend':
            db_execute($conn, 'UPDATE providers SET status = "suspended" WHERE id = ?', [$providerId]);
            $msg = 'Provider suspended.';
            break;
        case 'block':
            db_execute($conn, 'UPDATE providers SET status = "blocked" WHERE id = ?', [$providerId]);
            $msg = 'Provider blocked.';
            break;
        case 'reactivate':
            db_execute($conn, 'UPDATE providers SET status = "approved" WHERE id = ?', [$providerId]);
            $msg = 'Provider reactivated.';
            break;
        case 'verify':
            db_execute($conn, 'UPDATE providers SET is_verified = 1 WHERE id = ?', [$providerId]);
            db_execute($conn, 'INSERT IGNORE INTO provider_badge_map (provider_id, badge_id) SELECT ?, id FROM provider_badges WHERE slug = "verified"', [$providerId]);
            $msg = 'Provider verified.';
            break;
        case 'unverify':
            db_execute($conn, 'UPDATE providers SET is_verified = 0 WHERE id = ?', [$providerId]);
            db_execute($conn, 'DELETE pbm FROM provider_badge_map pbm JOIN provider_badges pb ON pb.id = pbm.badge_id WHERE pbm.provider_id = ? AND pb.slug = "verified"', [$providerId]);
            $msg = 'Verification removed.';
            break;
        case 'feature':
            db_execute($conn, 'UPDATE providers SET is_featured = 1 WHERE id = ?', [$providerId]);
            $msg = 'Provider featured.';
            break;
        case 'unfeature':
            db_execute($conn, 'UPDATE providers SET is_featured = 0 WHERE id = ?', [$providerId]);
            $msg = 'Provider unfeatured.';
            break;
        case 'delete':
            db_execute($conn, 'DELETE FROM providers WHERE id = ?', [$providerId]);
            $msg = 'Provider deleted.';
            break;
    }

    log_audit($conn, (int) $admin['id'], 'provider', $providerId, $action, ['status' => $provider['status']], null);
    flash_set('success', $msg);
    redirect('/admin/providers.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$statusFilter = clean_input($_GET['status'] ?? '');
$q = clean_input($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(p.business_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

$pg = paginate(
    $conn,
    "SELECT COUNT(*) FROM providers p JOIN users u ON u.id = p.user_id WHERE $whereSql",
    $params,
    $page,
    15
);

$providers = db_select(
    $conn,
    "SELECT p.*, u.email, u.phone, c.name AS city_name, cat.name AS category_name
     FROM providers p JOIN users u ON u.id = p.user_id
     LEFT JOIN cities c ON c.id = p.city_id LEFT JOIN categories cat ON cat.id = p.category_id
     WHERE $whereSql ORDER BY p.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$statusCounts = db_select($conn, 'SELECT status, COUNT(*) AS c FROM providers GROUP BY status');
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0, 'blocked' => 0];
foreach ($statusCounts as $row) {
    $counts[$row['status']] = (int) $row['c'];
}

$adminPageTitle = 'Providers';
$adminActive = 'providers';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>All providers</h3>
    <form method="get" style="display:flex;gap:10px;">
      <div style="position:relative;">
        <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search business or email" autocomplete="off" data-suggest="provider" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;min-width:220px;">
      </div>
      <select name="status" onchange="this.form.submit()" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'approved', 'rejected', 'suspended', 'blocked'] as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?> (<?php echo $counts[$s]; ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="btn-w btn-outline btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <?php if ($providers): ?>
  <table class="table-w">
    <thead><tr><th>Business</th><th>Contact</th><th>Category</th><th>City</th><th>Status</th><th>Badges</th><th>Joined</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($providers as $p): ?>
        <tr>
          <td><strong><?php echo e($p['business_name']); ?></strong></td>
          <td><?php echo e($p['email']); ?><br><span style="color:var(--ink-mute);font-size:12.5px;"><?php echo e($p['phone']); ?></span></td>
          <td><?php echo e($p['category_name'] ?? '—'); ?></td>
          <td><?php echo e($p['city_name'] ?? '—'); ?></td>
          <td><?php echo status_badge($p['status']); ?></td>
          <td>
            <?php if ($p['is_verified']): ?><i class="bi bi-patch-check-fill" style="color:var(--purple);" title="Verified"></i><?php endif; ?>
            <?php if ($p['is_featured']): ?><i class="bi bi-star-fill" style="color:var(--warning);" title="Featured"></i><?php endif; ?>
          </td>
          <td><?php echo e(format_date($p['created_at'])); ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <div style="display:inline-flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;">
              <?php
              $rowActions = [];
              if ($p['status'] === 'pending') {
                  $rowActions[] = ['approve', 'Approve', 'btn-primary'];
                  $rowActions[] = ['reject', 'Reject', 'btn-outline'];
              }
              if (in_array($p['status'], ['approved', 'suspended'], true)) {
                  $rowActions[] = [$p['status'] === 'approved' ? 'suspend' : 'reactivate', $p['status'] === 'approved' ? 'Suspend' : 'Reactivate', 'btn-outline'];
              }
              if ($p['status'] !== 'blocked') {
                  $rowActions[] = ['block', 'Block', 'btn-outline'];
              } else {
                  $rowActions[] = ['reactivate', 'Unblock', 'btn-outline'];
              }
              $rowActions[] = [$p['is_verified'] ? 'unverify' : 'verify', $p['is_verified'] ? 'Unverify' : 'Verify', 'btn-outline'];
              $rowActions[] = [$p['is_featured'] ? 'unfeature' : 'feature', $p['is_featured'] ? 'Unfeature' : 'Feature', 'btn-outline'];
              foreach ($rowActions as [$act, $label, $cls]):
              ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo e($label); ?> <?php echo e($p['business_name']); ?>?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="provider_id" value="<?php echo (int) $p['id']; ?>">
                  <input type="hidden" name="action" value="<?php echo $act; ?>">
                  <input type="hidden" name="back_qs" value="<?php echo e($_SERVER['QUERY_STRING'] ?? ''); ?>">
                  <button type="submit" class="btn-w <?php echo $cls; ?> btn-sm"><?php echo e($label); ?></button>
                </form>
              <?php endforeach; ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Permanently delete <?php echo e($p['business_name']); ?>? This cannot be undone.');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="provider_id" value="<?php echo (int) $p['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($pg['total_pages'] > 1): ?>
    <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
      <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo e($q); ?>"
           class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;">
      <div class="icon-wrap"><i class="bi bi-shop"></i></div>
      <h4>No providers match this filter</h4>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
