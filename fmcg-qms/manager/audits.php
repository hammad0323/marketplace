<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $auditId = db_exec("INSERT INTO audits (company_id,audit_type,title,department_id,auditor_id,max_score,status,scheduled_date,created_at)
             VALUES (?,?,?,?,?,?, 'scheduled',?,NOW())",
        [$cid, post('audit_type','internal'), post('title'), post_int('department_id') ?: null, current_user_id(), 100, post('scheduled_date') ?: date('Y-m-d')]);
    $items = array_filter(array_map('trim', explode("\n", post_raw('checklist_items', ''))));
    foreach ($items as $i => $item) {
        db_exec("INSERT INTO audit_checklists (audit_id, item_text, sort_order) VALUES (?,?,?)", [$auditId, $item, $i]);
    }
    log_activity($cid, current_user_id(), 'create', 'audit', $auditId, 'Created audit ' . post('title'));
    flash_set('success', 'Audit created.');
    redirect(base_url('manager/audit-view.php?id=' . $auditId));
}

$audits = db_all("SELECT a.*, d.name AS department_name FROM audits a LEFT JOIN departments d ON d.id=a.department_id WHERE a.company_id=? ORDER BY a.created_at DESC", [$cid]);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=? ORDER BY name", [$cid]);
$frameworks = db_all("SELECT * FROM compliance_frameworks WHERE status='active' ORDER BY name", []);

$pageTitle = 'Audits';
$activeMenu = 'audits';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Audits &amp; Compliance</h4>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#auditModal"><i class="bi bi-plus-lg"></i> New Audit</button>
</div>
<div class="qc-card">
  <table id="auditTable" class="table table-hover align-middle">
    <thead><tr><th>Title</th><th>Type</th><th>Department</th><th>Score</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($audits as $a): ?>
      <tr>
        <td class="fw-semibold small"><?= out($a['title']) ?></td>
        <td class="small text-uppercase"><?= out($a['audit_type']) ?></td>
        <td class="small"><?= out($a['department_name']) ?></td>
        <td class="small"><?= $a['score'] !== null ? $a['score'] . '/' . $a['max_score'] : '-' ?></td>
        <td><?= status_badge($a['status']) ?></td>
        <td class="small text-muted"><?= fmt_date($a['scheduled_date']) ?></td>
        <td class="text-end"><a href="<?= base_url('manager/audit-view.php?id=' . $a['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-right"></i></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$audits): ?><tr><td colspan="7" class="text-center text-muted py-4">No audits yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="auditModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="POST">
    <div class="modal-header"><h5 class="modal-title">New Audit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label small">Title *</label><input class="form-control" name="title" required></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small">Audit Type</label>
          <select class="form-select" name="audit_type">
            <option value="internal">Internal Quality Audit</option>
            <option value="layered_process">Layered Process Audit</option>
            <option value="gmp">GMP Audit</option>
            <option value="iso9001">ISO 9001</option>
            <?php foreach ($frameworks as $f): ?><option value="<?= out(strtolower($f['code'])) ?>"><?= out($f['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-6"><label class="form-label small">Department</label><select class="form-select" name="department_id"><option value="">--</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= out($d['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-2"><label class="form-label small">Scheduled Date</label><input type="date" class="form-control" name="scheduled_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="mb-1"><label class="form-label small">Checklist Items (one per line)</label>
        <textarea class="form-control" name="checklist_items" rows="5" placeholder="Personnel hygiene compliant&#10;Facility clean and organized&#10;Equipment calibration current&#10;..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm">Create Audit</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = '<script>$(function(){ $("#auditTable").DataTable({ order: [], pageLength: 20 }); });</script>';
include __DIR__ . '/../includes/layout_end.php';
