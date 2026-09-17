<?php
require __DIR__ . '/../config.php';
wh_require_page_access('halls');
$businessId = wh_current_business_id();

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    $hasBookings = wh_fetch_one('SELECT id FROM bookings WHERE hall_id=? AND business_id=? LIMIT 1', 'ii', [$id, $businessId]);
    if ($hasBookings) {
        wh_flash_set('error', 'This hall has bookings and cannot be deleted. Deactivate it instead.');
    } else {
        wh_execute('DELETE FROM halls WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        wh_flash_set('success', 'Hall deleted.');
    }
    wh_redirect(BASE_URL . '/admin/halls.php');
}
if (isset($_GET['toggle'])) {
    wh_csrf_verify();
    $id = (int) $_GET['toggle'];
    wh_execute("UPDATE halls SET status = IF(status='active','inactive','active') WHERE id=? AND business_id=?", 'ii', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/halls.php');
}

$halls = wh_get_halls($businessId, false);

$pageTitle = 'Halls';
$activePage = 'halls';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>All Halls</h3>
    <a href="<?= e(BASE_URL) ?>/admin/hall-form.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Hall</a>
  </div>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Hall</th><th>City</th><th>Capacity</th><th>Price</th><th>Status</th><th>Public</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($halls as $hall): ?>
      <tr>
        <td><strong><?= e($hall['name']) ?></strong></td>
        <td><?= e($hall['city']) ?></td>
        <td><?= (int) $hall['capacity_min'] ?>–<?= (int) $hall['capacity_max'] ?></td>
        <td><?= $hall['price_type'] === 'per_person' ? wh_format_money($hall['per_person_price']) . '/person' : wh_format_money($hall['base_price']) ?></td>
        <td><span class="badge badge-<?= e($hall['status']) ?>"><?= e(ucfirst($hall['status'])) ?></span></td>
        <td><?= $hall['is_public'] ? '<i class="fa-solid fa-check" style="color:var(--a-success)"></i>' : '<i class="fa-solid fa-xmark" style="color:var(--a-muted)"></i>' ?></td>
        <td style="white-space:nowrap;">
          <a href="<?= e(BASE_URL) ?>/admin/hall-form.php?id=<?= (int) $hall['id'] ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-pen"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/halls.php?toggle=<?= (int) $hall['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-power-off"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/halls.php?delete=<?= (int) $hall['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this hall?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$halls): ?><tr><td colspan="7">No halls yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
