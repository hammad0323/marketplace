<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
pm_refresh_expired();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$tpl = (int) ($_GET['template'] ?? 0);
$sort = $_GET['sort'] ?? 'updated';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 15;

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR proposal_number LIKE ? OR client_name LIKE ? OR client_company LIKE ? OR client_email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if (isset(status_meta()[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($tpl) {
    $where[] = 'template_id = ?';
    $params[] = $tpl;
}
$orders = ['updated' => 'COALESCE(updated_at, created_at) DESC', 'created' => 'created_at DESC', 'amount' => 'total DESC', 'client' => 'client_company ASC, client_name ASC'];
$order = $orders[$sort] ?? $orders['updated'];
$w = implode(' AND ', $where);
$totalRows = (int) db_value("SELECT COUNT(*) FROM proposals WHERE $w", $params);
$pages = max(1, (int) ceil($totalRows / $per));
$page = min($page, $pages);
$rows = db_all("SELECT * FROM proposals WHERE $w ORDER BY $order LIMIT " . (($page - 1) * $per) . ", $per", $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) c FROM proposals GROUP BY status') as $r) $counts[$r['status']] = (int) $r['c'];

$pageTitle = 'Proposals';
$activeNav = 'proposals';
include __DIR__ . '/layouts/header.php';
$qs = function (array $over) {
    return url('proposals.php?' . http_build_query(array_merge($_GET, $over)));
};
?>
<div class="page-head">
  <div><h1>Proposals</h1><p><?= $totalRows ?> proposal<?= $totalRows === 1 ? '' : 's' ?> found</p></div>
  <div class="actions"><a href="<?= url('proposal-create.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Create Proposal</a></div>
</div>

<div class="card-x">
  <div class="card-head flex-wrap">
    <ul class="nav nav-tabs-x border-0 flex-nowrap overflow-auto">
      <li class="nav-item"><a class="nav-link <?= $status === '' ? 'active' : '' ?>" href="<?= $qs(['status' => '', 'page' => 1]) ?>">All <span class="chip ms-1"><?= array_sum($counts) ?></span></a></li>
      <?php foreach (status_meta() as $k => $m): ?>
        <li class="nav-item"><a class="nav-link <?= $status === $k ? 'active' : '' ?>" href="<?= $qs(['status' => $k, 'page' => 1]) ?>"><?= $m['label'] ?> <span class="chip ms-1"><?= $counts[$k] ?? 0 ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <form class="ms-auto d-flex gap-2 flex-wrap" method="get">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <input class="form-control form-control-sm" style="width:220px" name="q" placeholder="Search…" value="<?= e($q) ?>">
      <select name="template" class="form-select form-select-sm" style="width:170px" onchange="this.form.submit()">
        <option value="0">All templates</option>
        <?php foreach (pm_templates() as $id => $t): ?><option value="<?= $id ?>" <?= $tpl === $id ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
      <select name="sort" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
        <?php foreach (['updated' => 'Last updated', 'created' => 'Newest', 'amount' => 'Amount', 'client' => 'Client'] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-responsive"><table class="table table-x">
    <thead><tr><th>Proposal</th><th>Client</th><th>Service</th><th>Amount</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
    <tbody><?= proposal_rows($rows) ?></tbody>
  </table></div>
  <?php if ($pages > 1): ?>
  <div class="p-3 d-flex justify-content-end">
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $qs(['page' => $i]) ?>"><?= $i ?></a></li><?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/layouts/footer.php'; ?>
