<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$id = get_int('id');
$tool = $id ? get_tool($id) : null;
if ($id && !$tool) { flash_set('danger', 'Tool not found.'); redirect(base_url('admin/tools.php')); }
$existingFields = $id ? get_tool_fields($id) : [];
$categories = get_tool_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name = post('name');
    $categoryId = post_int('category_id') ?: null;
    $description = post('description');
    $icon = post('icon', 'bi-clipboard-check');
    $instructions = post('instructions');
    $frequency = post('frequency', 'daily');
    $toolType = post('tool_type', 'form');
    $status = post('status', 'active');
    $aiInstructions = post('ai_instructions');
    $fieldsRaw = $_POST['fields'] ?? [];

    if (!$name) {
        flash_set('danger', 'Tool name is required.');
    } else {
        $slug = slugify($name);
        if ($tool) {
            db_exec("UPDATE tools SET category_id=?,name=?,slug=?,description=?,icon=?,instructions=?,frequency=?,tool_type=?,status=?,ai_instructions=? WHERE id=?",
                [$categoryId,$name,$slug,$description,$icon,$instructions,$frequency,$toolType,$status,$aiInstructions,$id]);
            db_exec("DELETE FROM tool_fields WHERE tool_id=?", [$id]);
            $toolId = $id;
        } else {
            $toolId = db_exec("INSERT INTO tools (category_id,name,slug,description,icon,instructions,frequency,tool_type,status,ai_instructions,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [$categoryId,$name,$slug,$description,$icon,$instructions,$frequency,$toolType,$status,$aiInstructions,current_user_id()]);
        }

        $sort = 0;
        foreach ($fieldsRaw as $f) {
            $label = trim(clean_input($f['label'] ?? ''));
            if ($label === '') continue;
            $sort++;
            $fieldName = $f['field_name'] ?? '' ? slugify($f['field_name']) : slugify($label);
            $fieldName = str_replace('-', '_', $fieldName);
            $fieldId = db_exec(
                "INSERT INTO tool_fields (tool_id,label,field_name,field_type,placeholder,description,is_required,unit,
                 min_value,max_value,warning_threshold_low,warning_threshold_high,critical_threshold_low,critical_threshold_high,
                 default_value,formula,sort_order,visibility) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $toolId, $label, $fieldName, clean_input($f['field_type'] ?? 'text'), clean_input($f['placeholder'] ?? ''),
                    clean_input($f['description'] ?? ''), !empty($f['is_required']) ? 1 : 0, clean_input($f['unit'] ?? '') ?: null,
                    $f['min_value'] !== '' && isset($f['min_value']) ? (float)$f['min_value'] : null,
                    $f['max_value'] !== '' && isset($f['max_value']) ? (float)$f['max_value'] : null,
                    $f['warning_low'] !== '' && isset($f['warning_low']) ? (float)$f['warning_low'] : null,
                    $f['warning_high'] !== '' && isset($f['warning_high']) ? (float)$f['warning_high'] : null,
                    $f['critical_low'] !== '' && isset($f['critical_low']) ? (float)$f['critical_low'] : null,
                    $f['critical_high'] !== '' && isset($f['critical_high']) ? (float)$f['critical_high'] : null,
                    clean_input($f['default_value'] ?? '') ?: null, clean_input($f['formula'] ?? '') ?: null,
                    $sort, 'visible',
                ]
            );
            $optionsRaw = trim($f['options'] ?? '');
            if ($optionsRaw !== '' && in_array($f['field_type'] ?? '', ['dropdown', 'multiselect', 'radio', 'checkbox'], true)) {
                $optSort = 0;
                foreach (explode(',', $optionsRaw) as $opt) {
                    $opt = trim($opt);
                    if ($opt === '') continue;
                    $optSort++;
                    db_exec("INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES (?,?,?,?)",
                        [$fieldId, $opt, slugify($opt), $optSort]);
                }
            }
        }

        log_activity(null, current_user_id(), $tool ? 'update' : 'create', 'tool', $toolId, "Saved tool $name with $sort field(s)");
        flash_set('success', 'Tool saved with ' . $sort . ' field(s).');
        redirect(base_url('admin/tools.php'));
    }
}

$pageTitle = $tool ? 'Edit Tool' : 'New Quality Tool';
$activeMenu = 'tools';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= out($pageTitle) ?></h4>
  <a href="<?= base_url('admin/tools.php') ?>" class="small text-muted">Cancel</a>
</div>

<form method="POST" id="toolForm">
  <?= csrf_field() ?>
  <div class="qc-card mb-3">
    <h3 class="mb-3">Tool Definition</h3>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label small fw-semibold">Tool Name *</label><input class="form-control" name="name" required value="<?= out($tool['name'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Category</label>
        <select class="form-select" name="category_id">
          <option value="">-- None --</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($tool['category_id'] ?? 0)==$c['id']?'selected':'' ?>><?= out($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Icon (Bootstrap Icons)</label><input class="form-control" name="icon" value="<?= out($tool['icon'] ?? 'bi-clipboard-check') ?>"></div>
      <div class="col-md-12"><label class="form-label small fw-semibold">Description</label><textarea class="form-control" name="description" rows="2"><?= out($tool['description'] ?? '') ?></textarea></div>
      <div class="col-md-12"><label class="form-label small fw-semibold">Instructions for Employees</label><textarea class="form-control" name="instructions" rows="2"><?= out($tool['instructions'] ?? '') ?></textarea></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Frequency</label>
        <select class="form-select" name="frequency">
          <?php foreach (['daily'=>'Daily','per_shift'=>'Per Shift','per_batch'=>'Per Batch','per_production_run'=>'Per Production Run','hourly'=>'Hourly','weekly'=>'Weekly','monthly'=>'Monthly','on_demand'=>'On Demand'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($tool['frequency'] ?? 'daily')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Tool Type</label>
        <select class="form-select" name="tool_type">
          <?php foreach (['form'=>'Form','matrix'=>'Matrix','diagram'=>'Diagram','calculator'=>'Calculator','checklist'=>'Checklist'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($tool['tool_type'] ?? 'form')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status">
          <option value="active" <?= ($tool['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($tool['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-12"><label class="form-label small fw-semibold">AI Instructions <span class="text-muted fw-normal">(guides the AI assistant for this tool)</span></label>
        <textarea class="form-control" name="ai_instructions" rows="2"><?= out($tool['ai_instructions'] ?? '') ?></textarea></div>
    </div>
  </div>

  <div class="qc-card mb-3">
    <div class="qc-card-header"><h3>Field Configuration</h3><button type="button" class="btn btn-soft-primary btn-sm" id="addFieldBtn"><i class="bi bi-plus-lg"></i> Add Field</button></div>
    <div id="fieldsContainer"></div>
  </div>

  <button type="submit" class="btn btn-primary px-4">Save Tool</button>
</form>

<template id="fieldRowTemplate">
  <div class="field-row border rounded-3 p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="badge bg-secondary-subtle text-secondary">Field</span>
      <button type="button" class="btn btn-sm btn-outline-danger remove-field"><i class="bi bi-trash"></i></button>
    </div>
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label small">Label *</label><input class="form-control form-control-sm f-label" name="__NAME__[label]"></div>
      <div class="col-md-3"><label class="form-label small">Field Name</label><input class="form-control form-control-sm" name="__NAME__[field_name]" placeholder="auto from label"></div>
      <div class="col-md-3"><label class="form-label small">Field Type</label>
        <select class="form-select form-select-sm f-type" name="__NAME__[field_type]">
          <?php foreach (['text'=>'Text','number'=>'Number','decimal'=>'Decimal','date'=>'Date','time'=>'Time','datetime'=>'DateTime','dropdown'=>'Dropdown','multiselect'=>'Multi-select','radio'=>'Radio','checkbox'=>'Checkbox','yes_no'=>'Yes/No','pass_fail'=>'Pass/Fail','rating'=>'Rating','percentage'=>'Percentage','measurement'=>'Measurement','formula'=>'Formula','file'=>'File','image'=>'Image','signature'=>'Signature','long_text'=>'Long Text'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><label class="form-label small">Unit</label><input class="form-control form-control-sm" name="__NAME__[unit]" placeholder="C, g, pH..."></div>
      <div class="col-md-6 options-wrap d-none"><label class="form-label small">Options (comma-separated)</label><input class="form-control form-control-sm" name="__NAME__[options]" placeholder="Option A, Option B, Option C"></div>
      <div class="col-md-6 formula-wrap d-none"><label class="form-label small">Formula</label><input class="form-control form-control-sm" name="__NAME__[formula]" placeholder="(availability*performance*quality)/10000"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Min</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[min_value]"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Max</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[max_value]"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Warning Low</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[warning_low]"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Warning High</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[warning_high]"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Critical Low</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[critical_low]"></div>
      <div class="col-6 col-md-2 threshold-wrap"><label class="form-label small">Critical High</label><input type="number" step="any" class="form-control form-control-sm" name="__NAME__[critical_high]"></div>
      <div class="col-md-3"><label class="form-label small">Default Value</label><input class="form-control form-control-sm" name="__NAME__[default_value]"></div>
      <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="__NAME__[is_required]" value="1"><label class="form-check-label small">Required</label></div></div>
    </div>
  </div>
</template>

<?php
$existingFieldsJson = json_encode(array_map(function ($f) {
    $f['options_csv'] = implode(', ', array_column($f['field_options'] ?? [], 'option_label'));
    return $f;
}, $existingFields));

$extraScripts = '<script>
var fieldIndex = 0;
var existingFields = ' . $existingFieldsJson . ';
var thresholdTypes = ["number","decimal","measurement","percentage"];
var optionTypes = ["dropdown","multiselect","radio","checkbox"];

function addFieldRow(data){
  data = data || {};
  var tpl = document.getElementById("fieldRowTemplate").content.cloneNode(true);
  var name = "fields[" + fieldIndex + "]";
  fieldIndex++;
  tpl.querySelectorAll("[name]").forEach(function(el){ el.setAttribute("name", el.getAttribute("name").replace("__NAME__", name)); });
  var row = tpl.querySelector(".field-row");
  document.getElementById("fieldsContainer").appendChild(tpl);
  row = document.getElementById("fieldsContainer").lastElementChild;
  if(data.label) row.querySelector(".f-label").value = data.label;
  if(data.field_name) row.querySelector("[name$=\\"[field_name]\\"]").value = data.field_name;
  if(data.field_type) row.querySelector(".f-type").value = data.field_type;
  if(data.unit) row.querySelector("[name$=\\"[unit]\\"]").value = data.unit;
  if(data.options_csv) row.querySelector("[name$=\\"[options]\\"]").value = data.options_csv;
  if(data.formula) row.querySelector("[name$=\\"[formula]\\"]").value = data.formula;
  if(data.min_value !== null && data.min_value !== undefined) row.querySelector("[name$=\\"[min_value]\\"]").value = data.min_value;
  if(data.max_value !== null && data.max_value !== undefined) row.querySelector("[name$=\\"[max_value]\\"]").value = data.max_value;
  if(data.warning_threshold_low !== null && data.warning_threshold_low !== undefined) row.querySelector("[name$=\\"[warning_low]\\"]").value = data.warning_threshold_low;
  if(data.warning_threshold_high !== null && data.warning_threshold_high !== undefined) row.querySelector("[name$=\\"[warning_high]\\"]").value = data.warning_threshold_high;
  if(data.critical_threshold_low !== null && data.critical_threshold_low !== undefined) row.querySelector("[name$=\\"[critical_low]\\"]").value = data.critical_threshold_low;
  if(data.critical_threshold_high !== null && data.critical_threshold_high !== undefined) row.querySelector("[name$=\\"[critical_high]\\"]").value = data.critical_threshold_high;
  if(data.default_value) row.querySelector("[name$=\\"[default_value]\\"]").value = data.default_value;
  if(data.is_required == 1) row.querySelector("[name$=\\"[is_required]\\"]").checked = true;
  toggleRowSections(row);
  row.querySelector(".f-type").addEventListener("change", function(){ toggleRowSections(row); });
  row.querySelector(".remove-field").addEventListener("click", function(){ row.remove(); });
}
function toggleRowSections(row){
  var type = row.querySelector(".f-type").value;
  row.querySelectorAll(".threshold-wrap").forEach(function(el){ el.classList.toggle("d-none", thresholdTypes.indexOf(type) === -1); });
  row.querySelector(".options-wrap").classList.toggle("d-none", optionTypes.indexOf(type) === -1);
  row.querySelector(".formula-wrap").classList.toggle("d-none", type !== "formula");
}
document.getElementById("addFieldBtn").addEventListener("click", function(){ addFieldRow(); });
if (existingFields.length) { existingFields.forEach(function(f){ addFieldRow(f); }); } else { addFieldRow(); }
</script>';
include __DIR__ . '/../includes/layout_end.php';
