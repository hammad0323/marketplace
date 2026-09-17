<?php
require __DIR__ . '/../config.php';
wh_require_page_access('time-slots');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $data = [
        'name' => wh_input_post('name'),
        'start_time' => wh_input_post('start_time'),
        'end_time' => wh_input_post('end_time'),
        'sort_order' => (int) wh_input_post('sort_order', 0),
        'status' => wh_input_post('status') === 'inactive' ? 'inactive' : 'active',
    ];
    if ($data['name'] === '' || !$data['start_time'] || !$data['end_time']) {
        wh_flash_set('error', 'Please fill in the slot name, start time and end time.');
    } elseif ($id) {
        wh_update('time_slots', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
        wh_flash_set('success', 'Time slot updated.');
    } else {
        $data['business_id'] = $businessId;
        wh_insert('time_slots', $data);
        wh_flash_set('success', 'Time slot added.');
    }
    wh_redirect(BASE_URL . '/admin/time-slots.php');
}

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    $used = wh_fetch_one('SELECT id FROM bookings WHERE time_slot_id=? AND business_id=? LIMIT 1', 'ii', [$id, $businessId]);
    if ($used) {
        wh_flash_set('error', 'This time slot is used by existing bookings and cannot be deleted. Deactivate it instead.');
    } else {
        wh_execute('DELETE FROM time_slots WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        wh_flash_set('success', 'Time slot deleted.');
    }
    wh_redirect(BASE_URL . '/admin/time-slots.php');
}
if (isset($_GET['toggle'])) {
    wh_csrf_verify();
    $id = (int) $_GET['toggle'];
    wh_execute("UPDATE time_slots SET status = IF(status='active','inactive','active') WHERE id=? AND business_id=?", 'ii', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/time-slots.php');
}

$slots = wh_get_time_slots($businessId, false);

$pageTitle = 'Time Slots';
$activePage = 'time-slots';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Time Slots</h3>
    <button type="button" class="btn btn-primary btn-sm" data-modal-open="#slotModal" onclick="openSlotModal()"><i class="fa-solid fa-plus"></i> Add Slot</button>
  </div>
  <p class="hint">Booking time slots (e.g. Morning, Evening, Night) — fully customizable, nothing is hard-coded.</p>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($slots as $slot): ?>
      <tr>
        <td><strong><?= e($slot['name']) ?></strong></td>
        <td><?= wh_format_time($slot['start_time']) ?></td>
        <td><?= wh_format_time($slot['end_time']) ?></td>
        <td><?= (int) $slot['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($slot['status']) ?>"><?= e(ucfirst($slot['status'])) ?></span></td>
        <td style="white-space:nowrap;">
          <button type="button" class="btn btn-light btn-sm" onclick='openSlotModal(<?= json_encode($slot) ?>)'><i class="fa-solid fa-pen"></i></button>
          <a href="<?= e(BASE_URL) ?>/admin/time-slots.php?toggle=<?= (int) $slot['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-power-off"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/time-slots.php?delete=<?= (int) $slot['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this time slot?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$slots): ?><tr><td colspan="6">No time slots yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="slotModal">
  <div class="modal">
    <div class="modal-head"><h3 id="slotModalTitle">Add Time Slot</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post" id="slotForm">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="id" id="slotId">
      <div class="form-group"><label>Slot Name</label><input type="text" name="name" id="slotName" required placeholder="e.g. Morning"></div>
      <div class="form-grid">
        <div class="form-group"><label>Start Time</label><input type="time" name="start_time" id="slotStart" required></div>
        <div class="form-group"><label>End Time</label><input type="time" name="end_time" id="slotEnd" required></div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="slotOrder" value="0"></div>
        <div class="form-group"><label>Status</label>
          <select name="status" id="slotStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save Slot</button>
    </form>
  </div>
</div>
<script>
function openSlotModal(slot) {
  document.getElementById('slotModalTitle').textContent = slot ? 'Edit Time Slot' : 'Add Time Slot';
  document.getElementById('slotId').value = slot ? slot.id : '';
  document.getElementById('slotName').value = slot ? slot.name : '';
  document.getElementById('slotStart').value = slot ? slot.start_time : '';
  document.getElementById('slotEnd').value = slot ? slot.end_time : '';
  document.getElementById('slotOrder').value = slot ? slot.sort_order : 0;
  document.getElementById('slotStatus').value = slot ? slot.status : 'active';
  document.getElementById('slotModal').classList.add('open');
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
