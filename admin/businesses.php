<?php
require __DIR__ . '/../config.php';
wh_require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $data = [
        'business_name' => wh_input_post('business_name'),
        'owner_name' => wh_input_post('owner_name'),
        'email' => wh_input_post('email'),
        'phone' => wh_input_post('phone'),
        'plan' => in_array(wh_input_post('plan'), ['basic', 'professional', 'enterprise'], true) ? wh_input_post('plan') : 'basic',
        'status' => in_array(wh_input_post('status'), ['active', 'suspended', 'expired'], true) ? wh_input_post('status') : 'active',
        'expiry_date' => wh_input_post('expiry_date') ?: null,
    ];
    if ($data['business_name'] === '') {
        wh_flash_set('error', 'Business name is required.');
        wh_redirect(BASE_URL . '/admin/businesses.php');
    }

    if ($id) {
        wh_update('businesses', $data, 'id = ?', [$id]);
        wh_flash_set('success', 'Business updated.');
    } else {
        $data['slug'] = wh_slugify($data['business_name']);
        $newId = wh_insert('businesses', $data);

        // Seed the essentials so the new tenant is immediately usable.
        foreach ([['Morning', '08:00:00', '13:00:00', 1], ['Evening', '14:00:00', '19:00:00', 2], ['Night', '20:00:00', '01:00:00', 3]] as $s) {
            wh_insert('time_slots', ['business_id' => $newId, 'name' => $s[0], 'start_time' => $s[1], 'end_time' => $s[2], 'sort_order' => $s[3]]);
        }
        foreach (['Wedding', 'Walima', 'Mehndi', 'Baraat', 'Engagement', 'Nikkah', 'Other'] as $i => $name) {
            wh_insert('event_types', ['business_id' => $newId, 'name' => $name, 'slug' => wh_slugify($name), 'sort_order' => $i]);
        }
        foreach (['show_public_availability' => '1', 'allow_online_booking' => '1', 'require_admin_confirmation' => '1', 'hold_pending_slots' => '1', 'minimum_advance_percent' => '20', 'site_name' => $data['business_name']] as $k => $v) {
            wh_insert('settings', ['business_id' => $newId, 'setting_key' => $k, 'setting_value' => $v]);
        }

        $ownerEmail = wh_input_post('owner_email');
        if ($ownerEmail !== '') {
            $existing = wh_fetch_one('SELECT id FROM admin_users WHERE email=?', 's', [$ownerEmail]);
            if (!$existing) {
                wh_insert('admin_users', [
                    'business_id' => $newId, 'name' => $data['owner_name'] ?: $data['business_name'],
                    'email' => $ownerEmail, 'password_hash' => password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT),
                    'role' => 'admin', 'status' => 'active', 'must_change_password' => 1,
                ]);
                wh_flash_set('success', 'Business created with an owner login. Use "Forgot password" with ' . $ownerEmail . ' to issue their first login link.');
            } else {
                wh_flash_set('success', 'Business created. That owner email already has an admin login elsewhere, so no new login was created.');
            }
        } else {
            wh_flash_set('success', 'Business created.');
        }
    }
    wh_redirect(BASE_URL . '/admin/businesses.php');
}

$businesses = wh_fetch_all(
    "SELECT b.*, (SELECT COUNT(*) FROM halls WHERE business_id=b.id) AS hall_count,
            (SELECT COUNT(*) FROM bookings WHERE business_id=b.id) AS booking_count
     FROM businesses b ORDER BY b.created_at DESC"
);

$pageTitle = 'Businesses';
$activePage = 'businesses';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Wedding Hall Businesses (Tenants)</h3>
    <button type="button" class="btn btn-primary btn-sm" onclick="openBizModal()"><i class="fa-solid fa-plus"></i> Add Business</button>
  </div>
  <p class="hint">Each business is fully isolated — its halls, bookings, customers, settings and admin users belong only to it.</p>
  <table class="admin-table">
    <thead><tr><th>Business</th><th>Owner</th><th>Plan</th><th>Status</th><th>Halls</th><th>Bookings</th><th>Expiry</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($businesses as $b): ?>
      <tr>
        <td><strong><?= e($b['business_name']) ?></strong></td>
        <td><?= e($b['owner_name']) ?><br><span class="hint"><?= e($b['email']) ?></span></td>
        <td><span class="badge badge-confirmed"><?= e(ucfirst($b['plan'])) ?></span></td>
        <td><span class="badge badge-<?= $b['status'] === 'active' ? 'active' : 'cancelled' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
        <td><?= (int) $b['hall_count'] ?></td>
        <td><?= (int) $b['booking_count'] ?></td>
        <td><?= $b['expiry_date'] ? wh_format_date($b['expiry_date']) : '—' ?></td>
        <td><button type="button" class="btn btn-light btn-sm" onclick='openBizModal(<?= json_encode($b) ?>)'><i class="fa-solid fa-pen"></i></button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$businesses): ?><tr><td colspan="8">No businesses yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-overlay" id="bizModal">
  <div class="modal">
    <div class="modal-head"><h3 id="bizModalTitle">Add Business</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="id" id="bizId">
      <div class="form-group"><label>Business Name</label><input type="text" name="business_name" id="bizName" required></div>
      <div class="form-grid">
        <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="bizOwner"></div>
        <div class="form-group"><label>Owner Email</label><input type="email" name="email" id="bizEmail"></div>
      </div>
      <div class="form-group" id="ownerEmailWrap"><label>Owner Login Email <span class="hint">(creates their first admin login)</span></label><input type="email" name="owner_email" id="bizOwnerEmail"></div>
      <div class="form-grid">
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="bizPhone"></div>
        <div class="form-group"><label>Plan</label><select name="plan" id="bizPlan"><option value="basic">Basic</option><option value="professional">Professional</option><option value="enterprise">Enterprise</option></select></div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Status</label><select name="status" id="bizStatus"><option value="active">Active</option><option value="suspended">Suspended</option><option value="expired">Expired</option></select></div>
        <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" id="bizExpiry"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save Business</button>
    </form>
  </div>
</div>
<script>
function openBizModal(b) {
  document.getElementById('bizModalTitle').textContent = b ? 'Edit Business' : 'Add Business';
  document.getElementById('bizId').value = b ? b.id : '';
  document.getElementById('bizName').value = b ? b.business_name : '';
  document.getElementById('bizOwner').value = b ? b.owner_name : '';
  document.getElementById('bizEmail').value = b ? b.email : '';
  document.getElementById('bizPhone').value = b ? b.phone : '';
  document.getElementById('bizPlan').value = b ? b.plan : 'basic';
  document.getElementById('bizStatus').value = b ? b.status : 'active';
  document.getElementById('bizExpiry').value = b ? b.expiry_date : '';
  document.getElementById('ownerEmailWrap').style.display = b ? 'none' : 'block';
  document.getElementById('bizModal').classList.add('open');
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
