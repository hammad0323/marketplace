<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'deadlines') {
        set_company_setting($cid, 'submission_deadline', post('submission_deadline', '17:00'));
        set_company_setting($cid, 'reminder_time', post('reminder_time', '15:00'));
        set_company_setting($cid, 'final_reminder_time', post('final_reminder_time', '16:30'));
        flash_set('success', 'Deadline settings saved.');
    } elseif ($action === 'targets') {
        set_company_setting($cid, 'quality_target_defect_rate', post('quality_target_defect_rate', '3'));
        set_company_setting($cid, 'quality_target_fpy', post('quality_target_fpy', '95'));
        foreach (post('weight', []) as $key => $weight) {
            db_exec("INSERT INTO kpi_definitions (company_id, kpi_key, name, weight, green_threshold, amber_threshold, direction, is_active)
                     SELECT ?, kpi_key, name, ?, green_threshold, amber_threshold, direction, 1 FROM kpi_definitions WHERE kpi_key=? AND company_id IS NULL LIMIT 1
                     ON DUPLICATE KEY UPDATE weight=VALUES(weight)", [$cid, (float)$weight, $key]);
        }
        flash_set('success', 'Quality targets and KPI weights saved.');
    } elseif ($action === 'add_line') {
        db_exec("INSERT INTO production_lines (company_id, name, code, status) VALUES (?,?,?,'active')", [$cid, post('line_name'), post('line_code')]);
        flash_set('success', 'Production line added.');
    } elseif ($action === 'add_shift') {
        db_exec("INSERT INTO shifts (company_id, name, start_time, end_time, status) VALUES (?,?,?,?,'active')", [$cid, post('shift_name'), post('shift_start') ?: null, post('shift_end') ?: null]);
        flash_set('success', 'Shift added.');
    } elseif ($action === 'add_machine') {
        db_exec("INSERT INTO machines (company_id, production_line_id, name, code, status) VALUES (?,?,?,?,'active')", [$cid, post_int('machine_line_id') ?: null, post('machine_name'), post('machine_code')]);
        flash_set('success', 'Machine added.');
    } elseif ($action === 'regenerate_api_key') {
        set_company_setting($cid, 'api_key', bin2hex(random_bytes(24)));
        flash_set('success', 'API key regenerated.');
    } elseif ($action === 'add_share') {
        $viewerId = post_int('viewer_department_id');
        $sourceId = post_int('source_department_id');
        if ($viewerId === $sourceId) {
            flash_set('danger', 'A department always sees its own data - choose two different departments.');
        } elseif (add_department_share($cid, current_user_id(), $viewerId, $sourceId)) {
            flash_set('success', 'Data sharing rule added.');
        } else {
            flash_set('danger', 'That sharing rule already exists.');
        }
    } elseif ($action === 'remove_share') {
        remove_department_share(post_int('share_id'), $cid, current_user_id());
        flash_set('success', 'Data sharing rule removed.');
    }
    redirect(base_url('manager/settings.php'));
}

$weights = db_all("SELECT * FROM kpi_definitions WHERE company_id IS NULL ORDER BY kpi_key", []);
$companyWeights = [];
foreach (db_all("SELECT kpi_key, weight FROM kpi_definitions WHERE company_id=?", [$cid]) as $w) { $companyWeights[$w['kpi_key']] = $w['weight']; }
$lines = db_all("SELECT * FROM production_lines WHERE company_id=? ORDER BY name", [$cid]);
$shifts = db_all("SELECT * FROM shifts WHERE company_id=? ORDER BY name", [$cid]);
$machines = db_all("SELECT m.*, pl.name AS line_name FROM machines m LEFT JOIN production_lines pl ON pl.id=m.production_line_id WHERE m.company_id=? ORDER BY m.name", [$cid]);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=? AND status='active' ORDER BY name", [$cid]);
$shares = get_department_shares($cid);

$pageTitle = 'Settings';
$activeMenu = 'settings';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Company Settings</h4></div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#deadlines">Deadlines &amp; Reminders</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#targets">Quality Targets &amp; Weights</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#lines">Production Lines</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#shifts">Shifts</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#machines">Machines</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#apiTab">API Access</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#sharingTab">Department Data Sharing</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="deadlines">
    <form method="POST" class="qc-card" style="max-width:500px;">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="deadlines">
      <h3 class="mb-3">Daily Submission Deadline</h3>
      <div class="mb-2"><label class="form-label small">Reminder Time</label><input type="time" class="form-control" name="reminder_time" value="<?= out(get_company_setting($cid,'reminder_time','15:00')) ?>"></div>
      <div class="mb-2"><label class="form-label small">Final Reminder Time</label><input type="time" class="form-control" name="final_reminder_time" value="<?= out(get_company_setting($cid,'final_reminder_time','16:30')) ?>"></div>
      <div class="mb-3"><label class="form-label small">Submission Deadline</label><input type="time" class="form-control" name="submission_deadline" value="<?= out(get_company_setting($cid,'submission_deadline','17:00')) ?>"></div>
      <p class="small text-muted">After the deadline, unsubmitted tools are automatically marked <strong>missed</strong>, employees and managers are notified, and an email alert is sent. Runs via the <code>cron/daily-deadline-check.php</code> scheduled task.</p>
      <button class="btn btn-primary btn-sm">Save</button>
    </form>
  </div>
  <div class="tab-pane fade" id="targets">
    <form method="POST" class="qc-card" style="max-width:700px;">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="targets">
      <h3 class="mb-3">Quality Targets</h3>
      <div class="row g-2 mb-3">
        <div class="col-6"><label class="form-label small">Target Defect Rate (%)</label><input type="number" step="0.1" class="form-control" name="quality_target_defect_rate" value="<?= out(get_company_setting($cid,'quality_target_defect_rate','3')) ?>"></div>
        <div class="col-6"><label class="form-label small">Target First Pass Yield (%)</label><input type="number" step="0.1" class="form-control" name="quality_target_fpy" value="<?= out(get_company_setting($cid,'quality_target_fpy','95')) ?>"></div>
      </div>
      <h3 class="mb-2">Company Quality Score Weighting</h3>
      <p class="small text-muted">Adjust how much each KPI contributes to your overall Quality Score (defaults shown from platform configuration).</p>
      <?php foreach ($weights as $w): ?>
        <div class="row g-2 mb-2 align-items-center">
          <div class="col-6 small"><?= out($w['name']) ?></div>
          <div class="col-6"><input type="number" step="0.01" min="0" max="1" class="form-control form-control-sm" name="weight[<?= out($w['kpi_key']) ?>]" value="<?= out($companyWeights[$w['kpi_key']] ?? $w['weight']) ?>"></div>
        </div>
      <?php endforeach; ?>
      <button class="btn btn-primary btn-sm mt-2">Save Targets</button>
    </form>
  </div>
  <div class="tab-pane fade" id="lines">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_line">
        <h3 class="mb-3">Add Production Line</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="line_name" placeholder="Line name" required></div>
        <div class="mb-2"><input class="form-control form-control-sm" name="line_code" placeholder="Code"></div>
        <button class="btn btn-sm btn-primary w-100">Add</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Code</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($lines as $l): ?><tr><td class="small"><?= out($l['name']) ?></td><td class="small"><?= out($l['code']) ?></td><td><?= status_badge($l['status']) ?></td></tr><?php endforeach; ?>
        <?php if (!$lines): ?><tr><td colspan="3" class="text-center text-muted py-3">No production lines yet.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="shifts">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_shift">
        <h3 class="mb-3">Add Shift</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="shift_name" placeholder="Shift name" required></div>
        <div class="row g-2 mb-2"><div class="col-6"><input type="time" class="form-control form-control-sm" name="shift_start"></div><div class="col-6"><input type="time" class="form-control form-control-sm" name="shift_end"></div></div>
        <button class="btn btn-sm btn-primary w-100">Add</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Start</th><th>End</th></tr></thead>
        <tbody><?php foreach ($shifts as $s): ?><tr><td class="small"><?= out($s['name']) ?></td><td class="small"><?= out($s['start_time']) ?></td><td class="small"><?= out($s['end_time']) ?></td></tr><?php endforeach; ?>
        <?php if (!$shifts): ?><tr><td colspan="3" class="text-center text-muted py-3">No shifts yet.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="machines">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_machine">
        <h3 class="mb-3">Add Machine</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="machine_name" placeholder="Machine name" required></div>
        <div class="mb-2"><input class="form-control form-control-sm" name="machine_code" placeholder="Code"></div>
        <div class="mb-2"><select class="form-select form-select-sm" name="machine_line_id"><option value="">Production Line</option><?php foreach ($lines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-sm btn-primary w-100">Add</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0"><thead><tr><th>Machine</th><th>Line</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($machines as $m): ?><tr><td class="small"><?= out($m['name']) ?></td><td class="small"><?= out($m['line_name']) ?></td><td><?= status_badge($m['status']) ?></td></tr><?php endforeach; ?>
        <?php if (!$machines): ?><tr><td colspan="3" class="text-center text-muted py-3">No machines yet.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="apiTab">
    <div class="qc-card" style="max-width:600px;">
      <h3 class="mb-2">API Access</h3>
      <p class="small text-muted">Use this key to authenticate read-only <code>/api/v1/</code> requests for ERP, MES, inventory, HR, production or BI integrations: <code>Authorization: Bearer &lt;key&gt;</code></p>
      <div class="input-group mb-3">
        <input type="text" class="form-control" readonly value="<?= out(get_company_setting($cid, 'api_key', 'Not generated yet')) ?>">
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="form_action" value="regenerate_api_key"><button class="btn btn-outline-secondary">Regenerate</button></form>
      </div>
      <a href="<?= base_url('api/v1/index.php') ?>" target="_blank" class="small">View API documentation &rarr;</a>
    </div>
  </div>
  <div class="tab-pane fade" id="sharingTab">
    <div class="row g-3">
      <div class="col-lg-5">
        <form method="POST" class="qc-card">
          <?= csrf_field() ?><input type="hidden" name="form_action" value="add_share">
          <h3 class="mb-2">Grant Read-Only Access</h3>
          <p class="small text-muted">Let one department view another department's quality data (dashboard, issues, submissions) without being able to change anything. Example: give Supply Chain visibility into Production's data.</p>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Department that should be able to view</label>
            <select class="form-select" name="viewer_department_id" required>
              <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Department whose data will be visible</label>
            <select class="form-select" name="source_department_id" required>
              <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary btn-sm">Grant Access</button>
        </form>
      </div>
      <div class="col-lg-7">
        <div class="qc-card">
          <h3 class="mb-3">Active Sharing Rules</h3>
          <table class="table table-sm mb-0">
            <thead><tr><th>Can View</th><th></th><th>Department Data</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($shares as $s): ?>
              <tr>
                <td class="small fw-semibold"><?= out($s['viewer_department_name']) ?></td>
                <td class="text-muted"><i class="bi bi-arrow-right"></i></td>
                <td class="small"><?= out($s['source_department_name']) ?></td>
                <td class="text-end">
                  <form method="POST" onsubmit="return confirm('Remove this sharing rule?');">
                    <?= csrf_field() ?><input type="hidden" name="form_action" value="remove_share"><input type="hidden" name="share_id" value="<?= $s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$shares): ?><tr><td colspan="4" class="text-center text-muted py-3">No sharing rules configured yet - every department only sees its own data by default.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
