<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE s.status = ?" : "";
$params = $statusFilter ? [$statusFilter] : [];
$types = $statusFilter ? 's' : '';

$count = db_fetch_one("SELECT COUNT(*) c FROM shops s $where", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$shops = db_fetch_all("SELECT s.*, o.name as owner_name, o.email as owner_email FROM shops s
    JOIN shop_owners o ON o.id = s.owner_id $where ORDER BY s.created_at DESC LIMIT ? OFFSET ?",
    $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = 'admin'; $pageTitle = 'Shops'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shops</h1></div>
<div class="filter-tabs">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'payment_overdue' => 'Payment Overdue', 'banned' => 'Banned'] as $k => $v): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="<?= admin_url('shops/index.php?status=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Shop</th><th>Owner</th><th>City</th><th>Status</th><th>Rating</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($shops): foreach ($shops as $s): ?>
      <tr>
        <td><a href="<?= admin_url('shops/view.php?id=' . $s['id']) ?>"><?= clean($s['shop_name']) ?></a></td>
        <td><?= clean($s['owner_name']) ?><br><small class="text-muted"><?= clean($s['owner_email']) ?></small></td>
        <td><?= clean($s['city']) ?></td>
        <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : ($s['status'] === 'pending' ? 'warn' : 'danger') ?>"><?= clean(str_replace('_', ' ', $s['status'])) ?></span></td>
        <td><i class="fa-solid fa-star"></i> <?= number_format($s['rating_avg'], 1) ?></td>
        <td class="action-cell">
          <?php if ($s['status'] === 'pending'): ?>
            <form method="post" action="<?= admin_url('shops/action.php') ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
              <button name="action" value="reject" class="btn btn-sm btn-danger" onclick="return prompt('Rejection reason:') !== null">Reject</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= admin_url('shops/action.php') ?>" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <?php foreach (['active','suspended','payment_overdue','banned','inactive'] as $st): ?>
                  <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="action" value="set_status">
            </form>
          <?php endif; ?>
          <a href="<?= admin_url('shops/view.php?id=' . $s['id']) ?>" class="btn btn-sm btn-outline">View</a>
        </td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No shops found</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, admin_url('shops/index.php?status=' . $statusFilter)) ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
