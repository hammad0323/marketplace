<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

$toolId = get_int('tool_id');
$tool = get_tool($toolId);
if (!$tool || !employee_can_use_tool($uid, $toolId)) {
    flash_set('danger', 'This tool is not assigned to you.');
    redirect(base_url('employee/dashboard.php'));
}
$fields = get_tool_fields($toolId);
$departments = db_all("SELECT id, name FROM departments WHERE company_id=?", [$cid]);
$batches = db_all("SELECT id, batch_number FROM batches WHERE company_id=? ORDER BY created_at DESC LIMIT 50", [$cid]);
$productionLines = db_all("SELECT id, name FROM production_lines WHERE company_id=?", [$cid]);

$pageTitle = $tool['name'];
$activeMenu = 'dashboard';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('employee/dashboard.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Dashboard</a>
<div class="mb-4 mt-1"><h4 class="fw-bold mb-0"><i class="bi <?= out($tool['icon'] ?: 'bi-clipboard-check') ?> text-primary"></i> <?= out($tool['name']) ?></h4>
<?php if ($tool['instructions']): ?><p class="text-muted small mb-0"><?= out($tool['instructions']) ?></p><?php endif; ?></div>

<div class="row g-3">
  <div class="col-lg-8">
    <form id="toolForm" class="qc-card <?= get_param('floor') ? 'floor-mode' : '' ?>">
      <input type="hidden" name="tool_id" value="<?= $toolId ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Department</label>
          <select class="form-select form-select-sm" name="context[department_id]"><option value="">--</option><?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= (current_role()==='employee' && $_SESSION['department_id']==$d['id'])?'selected':'' ?>><?= out($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Batch</label>
          <select class="form-select form-select-sm" name="context[batch_id]"><option value="">--</option><?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>"><?= out($b['batch_number']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Production Line</label>
          <select class="form-select form-select-sm" name="context[production_line_id]"><option value="">--</option><?php foreach ($productionLines as $l): ?><option value="<?= $l['id'] ?>"><?= out($l['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <hr>
      <?php foreach ($fields as $f):
        $name = "values[{$f['id']}]";
        $thresholdAttrs = '';
        if ($f['min_value'] !== null) $thresholdAttrs .= ' data-min="' . out($f['min_value']) . '"';
        if ($f['max_value'] !== null) $thresholdAttrs .= ' data-max="' . out($f['max_value']) . '"';
        if ($f['critical_threshold_low'] !== null) $thresholdAttrs .= ' data-crit-low="' . out($f['critical_threshold_low']) . '"';
        if ($f['critical_threshold_high'] !== null) $thresholdAttrs .= ' data-crit-high="' . out($f['critical_threshold_high']) . '"';
      ?>
      <div class="mb-3 field-wrap">
        <label class="form-label small fw-semibold"><?= out($f['label']) ?><?= $f['unit'] ? ' (' . out($f['unit']) . ')' : '' ?> <?= $f['is_required'] ? '<span class="text-danger">*</span>' : '' ?></label>
        <?php if ($f['description']): ?><div class="form-text small mt-0 mb-1"><?= out($f['description']) ?></div><?php endif; ?>
        <?php switch ($f['field_type']):
          case 'long_text': ?>
            <textarea class="form-control" name="<?= $name ?>" rows="3" <?= $f['is_required']?'required':'' ?>><?= out($f['default_value']) ?></textarea>
          <?php break; case 'dropdown': ?>
            <select class="form-select" name="<?= $name ?>" <?= $f['is_required']?'required':'' ?>>
              <option value="">-- Select --</option>
              <?php foreach ($f['field_options'] as $opt): ?><option value="<?= out($opt['option_value']) ?>"><?= out($opt['option_label']) ?></option><?php endforeach; ?>
            </select>
          <?php break; case 'radio': ?>
            <div>
              <?php foreach ($f['field_options'] as $opt): ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="<?= $name ?>" value="<?= out($opt['option_value']) ?>" <?= $f['is_required']?'required':'' ?>>
                  <label class="form-check-label small"><?= out($opt['option_label']) ?></label></div>
              <?php endforeach; ?>
            </div>
          <?php break; case 'multiselect': case 'checkbox': ?>
            <div>
              <?php foreach ($f['field_options'] as $opt): ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="<?= $name ?>[]" value="<?= out($opt['option_value']) ?>">
                  <label class="form-check-label small"><?= out($opt['option_label']) ?></label></div>
              <?php endforeach; ?>
            </div>
          <?php break; case 'yes_no': ?>
            <div class="btn-group" role="group">
              <input type="radio" class="btn-check" name="<?= $name ?>" id="yn_<?= $f['id'] ?>_y" value="Yes" <?= $f['is_required']?'required':'' ?>><label class="btn btn-outline-success" for="yn_<?= $f['id'] ?>_y">Yes</label>
              <input type="radio" class="btn-check" name="<?= $name ?>" id="yn_<?= $f['id'] ?>_n" value="No"><label class="btn btn-outline-danger" for="yn_<?= $f['id'] ?>_n">No</label>
            </div>
          <?php break; case 'pass_fail': ?>
            <div class="btn-group pass-fail-toggle" role="group">
              <input type="radio" class="btn-check" name="<?= $name ?>" id="pf_<?= $f['id'] ?>_p" value="Pass" <?= $f['is_required']?'required':'' ?>><label class="btn btn-outline-success" for="pf_<?= $f['id'] ?>_p"><i class="bi bi-check-circle"></i> Pass</label>
              <input type="radio" class="btn-check" name="<?= $name ?>" id="pf_<?= $f['id'] ?>_f" value="Fail"><label class="btn btn-outline-danger" for="pf_<?= $f['id'] ?>_f"><i class="bi bi-x-circle"></i> Fail</label>
            </div>
          <?php break; case 'rating': ?>
            <select class="form-select" name="<?= $name ?>" <?= $f['is_required']?'required':'' ?>>
              <option value="">-- Rate --</option>
              <?php for ($r = (int)($f['min_value'] ?? 1); $r <= (int)($f['max_value'] ?? 5); $r++): ?><option value="<?= $r ?>"><?= $r ?> <?= str_repeat('★', $r) ?></option><?php endfor; ?>
            </select>
          <?php break; case 'date': ?><input type="date" class="form-control" name="<?= $name ?>" <?= $f['is_required']?'required':'' ?>>
          <?php break; case 'time': ?><input type="time" class="form-control" name="<?= $name ?>" <?= $f['is_required']?'required':'' ?>>
          <?php break; case 'datetime': ?><input type="datetime-local" class="form-control" name="<?= $name ?>" <?= $f['is_required']?'required':'' ?>>
          <?php break; case 'file': case 'image': case 'signature': ?><input type="file" class="form-control" name="<?= $name ?>_file">
          <?php break; case 'number': case 'decimal': case 'measurement': case 'percentage': ?>
            <input type="number" step="any" class="form-control threshold-field" name="<?= $name ?>" placeholder="<?= out($f['placeholder']) ?>" <?= $thresholdAttrs ?> <?= $f['is_required']?'required':'' ?>>
            <div class="small threshold-hint mt-1"></div>
          <?php break; default: ?>
            <input type="text" class="form-control" name="<?= $name ?>" placeholder="<?= out($f['placeholder']) ?>" value="<?= out($f['default_value']) ?>" <?= $f['is_required']?'required':'' ?>>
        <?php endswitch; ?>
      </div>
      <?php endforeach; ?>
      <?php if (!$fields): ?><p class="text-muted">This tool has no configured fields yet.</p><?php endif; ?>
      <button type="submit" class="btn btn-primary px-4" <?= !$fields ? 'disabled' : '' ?>>Submit</button>
    </form>
  </div>
  <div class="col-lg-4">
    <div class="qc-card" id="aiPanel">
      <div class="qc-card-header"><h3><i class="bi bi-robot text-primary"></i> AI Assistance</h3></div>
      <p class="small text-muted mb-0">Fill the form and submit - the AI assistant will check your reading against the configured specification and suggest next steps if anything is out of range.</p>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
document.querySelectorAll(".threshold-field").forEach(function(input){
  input.addEventListener("input", function(){
    var v = parseFloat(input.value); var hint = input.parentElement.querySelector(".threshold-hint");
    if (isNaN(v)) { hint.innerHTML = ""; return; }
    var min = input.dataset.min !== undefined ? parseFloat(input.dataset.min) : null;
    var max = input.dataset.max !== undefined ? parseFloat(input.dataset.max) : null;
    var critLow = input.dataset.critLow !== undefined ? parseFloat(input.dataset.critLow) : null;
    var critHigh = input.dataset.critHigh !== undefined ? parseFloat(input.dataset.critHigh) : null;
    if ((critLow !== null && v <= critLow) || (critHigh !== null && v >= critHigh)) {
      hint.innerHTML = "<span class=\\"text-danger fw-semibold\\"><i class=\\"bi bi-exclamation-octagon-fill\\"></i> Critical deviation</span>";
    } else if ((min !== null && v < min) || (max !== null && v > max)) {
      hint.innerHTML = "<span class=\\"text-danger\\"><i class=\\"bi bi-exclamation-triangle-fill\\"></i> Out of specification</span>";
    } else {
      hint.innerHTML = "<span class=\\"text-success\\"><i class=\\"bi bi-check-circle-fill\\"></i> Within specification</span>";
    }
  });
});
$("#toolForm").on("submit", function(e){
  e.preventDefault();
  var formData = new FormData(this);
  formData.append("csrf_token", QMS.csrfToken);
  $.ajax({ url: QMS.baseUrl + "/ajax/employee/submit-tool.php", type: "POST", data: formData, processData: false, contentType: false })
    .done(function(res){
      if (!res.success) { QMS.toast("error", res.message || "Submission failed"); return; }
      var panel = document.getElementById("aiPanel");
      var html = "<div class=\\"qc-card-header\\"><h3><i class=\\"bi bi-robot text-primary\\"></i> AI Assistance</h3></div>";
      html += "<div class=\\"ai-suggestion-box mb-2\\"><i class=\\"bi bi-stars mt-1\\"></i><span>" + QMS.escapeHtml(res.ai_suggestion) + "</span></div>";
      if (res.deviations && res.deviations.length) {
        html += "<div class=\\"alert alert-warning small\\">" + res.deviations.length + " deviation(s) detected - a quality issue has been created and your manager notified.</div>";
      } else {
        html += "<div class=\\"alert alert-success small\\">All values within specification.</div>";
      }
      panel.innerHTML = html;
      Swal.fire({ icon: res.deviations && res.deviations.length ? "warning" : "success", title: "Submitted", text: res.deviations && res.deviations.length ? "Deviation detected - issue created." : "Recorded successfully.", timer: 2200, showConfirmButton:false })
        .then(function(){ window.location.href = QMS.baseUrl + "/employee/dashboard.php"; });
    })
    .fail(function(){ QMS.toast("error", "Submission failed. Please try again."); });
});
</script>';
include __DIR__ . '/../includes/layout_end.php';
