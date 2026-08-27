<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'add_kaizen') {
        db_exec("INSERT INTO kaizen_events (company_id,title,problem,idea,team,savings,status,created_by,created_at) VALUES (?,?,?,?,?,?, 'planned',?,NOW())",
            [$cid, post('title'), post('problem'), post('idea'), post('team'), post_float('savings') ?: null, current_user_id()]);
        flash_set('success', 'Kaizen idea logged.');
    } elseif ($action === 'add_5s') {
        $scores = [post_int('sort_score'), post_int('set_in_order_score'), post_int('shine_score'), post_int('standardize_score'), post_int('sustain_score')];
        $total = round(array_sum($scores) / (5 * 5) * 100, 1);
        db_exec("INSERT INTO five_s_audits (company_id,area,sort_score,set_in_order_score,shine_score,standardize_score,sustain_score,total_score,auditor_id,audit_date,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
            [$cid, post('area'), ...$scores, $total, current_user_id(), post('audit_date') ?: date('Y-m-d')]);
        flash_set('success', "5S audit recorded: $total%");
    } elseif ($action === 'add_gemba') {
        db_exec("INSERT INTO gemba_walks (company_id,area,observer_id,finding,action,responsible_person,due_date,status,created_at) VALUES (?,?,?,?,?,?,?, 'open',NOW())",
            [$cid, post('area'), current_user_id(), post('finding'), post('action'), post_int('responsible_person') ?: null, post('due_date') ?: null]);
        flash_set('success', 'Gemba observation recorded.');
    } elseif ($action === 'andon') {
        db_exec("INSERT INTO andon_events (company_id,production_line_id,status,event_type,description,raised_by,created_at) VALUES (?,?,?,?,?,?,NOW())",
            [$cid, post_int('production_line_id') ?: null, post('status','red'), post('event_type','quality_alert'), post('description'), current_user_id()]);
        flash_set('success', 'Andon event raised.');
    }
    redirect(base_url('manager/lean.php'));
}

$kaizens = db_all("SELECT * FROM kaizen_events WHERE company_id=? ORDER BY created_at DESC LIMIT 20", [$cid]);
$fiveS = db_all("SELECT * FROM five_s_audits WHERE company_id=? ORDER BY audit_date DESC LIMIT 20", [$cid]);
$gembas = db_all("SELECT g.*, u.name AS responsible_name FROM gemba_walks g LEFT JOIN users u ON u.id=g.responsible_person WHERE g.company_id=? ORDER BY g.created_at DESC LIMIT 20", [$cid]);
$andonEvents = db_all("SELECT a.*, pl.name AS line_name FROM andon_events a LEFT JOIN production_lines pl ON pl.id=a.production_line_id WHERE a.company_id=? ORDER BY a.created_at DESC LIMIT 20", [$cid]);
$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);
$lines = db_all("SELECT id, name FROM production_lines WHERE company_id=? ORDER BY name", [$cid]);

$pageTitle = 'Lean & Continuous Improvement';
$activeMenu = 'lean';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Lean &amp; Continuous Improvement</h4></div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#kaizen">Kaizen Events</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#fivesTab">5S Audits</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gembaTab">Gemba Walks</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#andonTab">Andon</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane fade show active" id="kaizen">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_kaizen">
        <h3 class="mb-3">New Kaizen Idea</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="title" placeholder="Title" required></div>
        <div class="mb-2"><textarea class="form-control form-control-sm" name="problem" rows="2" placeholder="Problem"></textarea></div>
        <div class="mb-2"><textarea class="form-control form-control-sm" name="idea" rows="2" placeholder="Idea"></textarea></div>
        <div class="mb-2"><input class="form-control form-control-sm" name="team" placeholder="Team members"></div>
        <div class="mb-2"><input type="number" step="0.01" class="form-control form-control-sm" name="savings" placeholder="Estimated savings ($)"></div>
        <button class="btn btn-sm btn-primary w-100">Save</button></form></div>
      <div class="col-lg-8"><div class="row g-2">
        <?php foreach ($kaizens as $k): ?><div class="col-md-6"><div class="qc-card h-100"><div class="d-flex justify-content-between"><h6 class="fw-bold"><?= out($k['title']) ?></h6><?= status_badge($k['status']) ?></div>
          <p class="small text-muted mb-1"><?= out($k['problem']) ?></p><p class="small mb-0"><i class="bi bi-lightbulb text-warning"></i> <?= out($k['idea']) ?></p>
          <?php if ($k['savings']): ?><div class="small text-success fw-semibold mt-1">$<?= fmt_number($k['savings'],0) ?> savings</div><?php endif; ?></div></div><?php endforeach; ?>
        <?php if (!$kaizens): ?><p class="text-muted">No Kaizen events yet.</p><?php endif; ?>
      </div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="fivesTab">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_5s">
        <h3 class="mb-3">5S Audit</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="area" placeholder="Area" required></div>
        <?php foreach (['sort_score'=>'Sort','set_in_order_score'=>'Set in Order','shine_score'=>'Shine','standardize_score'=>'Standardize','sustain_score'=>'Sustain'] as $k=>$label): ?>
          <div class="mb-2"><label class="form-label small mb-0"><?= $label ?> (1-5)</label><input type="number" min="1" max="5" class="form-control form-control-sm" name="<?= $k ?>" value="3" required></div>
        <?php endforeach; ?>
        <button class="btn btn-sm btn-primary w-100">Save Audit</button></form></div>
      <div class="col-lg-8"><div class="qc-card"><table class="table table-sm mb-0"><thead><tr><th>Area</th><th>Date</th><th>Score</th></tr></thead>
        <tbody><?php foreach ($fiveS as $f): ?><tr><td class="small"><?= out($f['area']) ?></td><td class="small"><?= fmt_date($f['audit_date']) ?></td><td class="fw-bold"><?= $f['total_score'] ?>%</td></tr><?php endforeach; ?>
        <?php if (!$fiveS): ?><tr><td colspan="3" class="text-center text-muted py-3">No 5S audits yet.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="gembaTab">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="add_gemba">
        <h3 class="mb-3">Gemba Walk</h3>
        <div class="mb-2"><input class="form-control form-control-sm" name="area" placeholder="Area" required></div>
        <div class="mb-2"><textarea class="form-control form-control-sm" name="finding" rows="2" placeholder="Finding" required></textarea></div>
        <div class="mb-2"><input class="form-control form-control-sm" name="action" placeholder="Action"></div>
        <div class="mb-2"><select class="form-select form-select-sm" name="responsible_person"><option value="">Responsible</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= out($e['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><input type="date" class="form-control form-control-sm" name="due_date"></div>
        <button class="btn btn-sm btn-primary w-100">Save</button></form></div>
      <div class="col-lg-8"><div class="qc-card">
        <?php foreach ($gembas as $g): ?><div class="border-bottom py-2"><div class="d-flex justify-content-between"><span class="small fw-semibold"><?= out($g['area']) ?></span><?= status_badge($g['status']) ?></div><div class="small text-muted"><?= out($g['finding']) ?></div></div><?php endforeach; ?>
        <?php if (!$gembas): ?><p class="text-muted small mb-0">No Gemba walks yet.</p><?php endif; ?>
      </div></div>
    </div>
  </div>
  <div class="tab-pane fade" id="andonTab">
    <div class="row g-3">
      <div class="col-lg-4"><form method="POST" class="qc-card"><?= csrf_field() ?><input type="hidden" name="form_action" value="andon">
        <h3 class="mb-3">Raise Andon Event</h3>
        <div class="mb-2"><select class="form-select form-select-sm" name="production_line_id"><?php foreach ($lines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><select class="form-select form-select-sm" name="status"><option value="red">Red - Stop</option><option value="yellow">Yellow - Attention</option><option value="green">Green - Resolved</option></select></div>
        <div class="mb-2"><select class="form-select form-select-sm" name="event_type"><option value="line_stop">Line Stop</option><option value="quality_alert">Quality Alert</option><option value="maintenance_alert">Maintenance Alert</option><option value="material_alert">Material Alert</option></select></div>
        <div class="mb-2"><input class="form-control form-control-sm" name="description" placeholder="Description"></div>
        <button class="btn btn-sm btn-primary w-100">Raise</button></form></div>
      <div class="col-lg-8"><div class="row g-2">
        <?php foreach ($andonEvents as $a): ?><div class="col-md-6"><div class="qc-card d-flex flex-row align-items-center gap-2">
          <span class="rag-dot <?= $a['status']==='red'?'bg-danger':($a['status']==='yellow'?'bg-warning':'bg-success') ?>" style="width:16px;height:16px;"></span>
          <div><div class="small fw-semibold"><?= out($a['line_name']) ?> - <?= out(str_replace('_',' ',$a['event_type'])) ?></div><div class="small text-muted"><?= out($a['description']) ?></div></div>
        </div></div><?php endforeach; ?>
        <?php if (!$andonEvents): ?><p class="text-muted">No Andon events yet.</p><?php endif; ?>
      </div></div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
