<?php
require __DIR__ . '/../config.php';
wh_require_page_access('event-types');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $name = wh_input_post('name');
    if ($name === '') {
        wh_flash_set('error', 'Please enter an event type name.');
        wh_redirect(BASE_URL . '/admin/event-types.php');
    }
    $data = [
        'name' => $name,
        'slug' => wh_slugify($name),
        'sort_order' => (int) wh_input_post('sort_order', 0),
        'status' => wh_input_post('status') === 'inactive' ? 'inactive' : 'active',
    ];
    if ($id) {
        wh_update('event_types', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
        wh_flash_set('success', 'Event type updated.');
    } else {
        $data['business_id'] = $businessId;
        wh_insert('event_types', $data);
        wh_flash_set('success', 'Event type added.');
    }
    wh_redirect(BASE_URL . '/admin/event-types.php');
}

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    $used = wh_fetch_one('SELECT id FROM bookings WHERE event_type_id=? AND business_id=? LIMIT 1', 'ii', [$id, $businessId]);
    if ($used) {
        wh_flash_set('error', 'This event type is used by existing bookings and cannot be deleted. Deactivate it instead.');
    } else {
        wh_execute('DELETE FROM event_types WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        wh_flash_set('success', 'Event type deleted.');
    }
    wh_redirect(BASE_URL . '/admin/event-types.php');
}
if (isset($_GET['toggle'])) {
    wh_csrf_verify();
    $id = (int) $_GET['toggle'];
    wh_execute("UPDATE event_types SET status = IF(status='active','inactive','active') WHERE id=? AND business_id=?", 'ii', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/event-types.php');
}

$types = wh_get_event_types($businessId, false);

$pageTitle = 'Event Types';
$activePage = 'event-types';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Event Types</h3>
    <button type="button" class="btn btn-primary btn-sm" onclick="openTypeModal()"><i class="fa-solid fa-plus"></i> Add Event Type</button>
  </div>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($types as $t): ?>
      <tr>
        <td><strong><?= e($t['name']) ?></strong></td>
        <td><?= (int) $t['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($t['status']) ?>"><?= e(ucfirst($t['status'])) ?></span></td>
        <td style="white-space:nowrap;">
          <button type="button" class="btn btn-light btn-sm" onclick='openTypeModal(<?= json_encode($t) ?>)'><i class="fa-solid fa-pen"></i></button>
          <a href="<?= e(BASE_URL) ?>/admin/event-types.php?toggle=<?= (int) $t['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-power-off"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/event-types.php?delete=<?= (int) $t['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this event type?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$types): ?><tr><td colspan="4">No event types yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="typeModal">
  <div class="modal">
    <div class="modal-head"><h3 id="typeModalTitle">Add Event Type</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="id" id="typeId">
      <div class="form-group"><label>Event Type Name</label><input type="text" name="name" id="typeName" required placeholder="e.g. Walima"></div>
      <div class="form-grid">
        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="typeOrder" value="0"></div>
        <div class="form-group"><label>Status</label>
          <select name="status" id="typeStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save</button>
    </form>
  </div>
</div>
<script>
function openTypeModal(t) {
  document.getElementById('typeModalTitle').textContent = t ? 'Edit Event Type' : 'Add Event Type';
  document.getElementById('typeId').value = t ? t.id : '';
  document.getElementById('typeName').value = t ? t.name : '';
  document.getElementById('typeOrder').value = t ? t.sort_order : 0;
  document.getElementById('typeStatus').value = t ? t.status : 'active';
  document.getElementById('typeModal').classList.add('open');
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
