<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$employee = $id ? db_one("SELECT * FROM users WHERE id=? AND company_id=? AND role='employee'", [$id, $cid]) : null;
if ($id && !$employee) { flash_set('danger', 'Employee not found.'); redirect(base_url('manager/employees.php')); }

$departments = db_all("SELECT * FROM departments WHERE company_id=? AND status='active' ORDER BY name", [$cid]);
$shifts = db_all("SELECT * FROM shifts WHERE company_id=? AND status='active' ORDER BY name", [$cid]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $name = post('name'); $email = post('email'); $phone = post('phone');
    $departmentId = post_int('department_id') ?: null; $designation = post('designation');
    $shiftId = post_int('shift_id') ?: null; $joiningDate = post('joining_date') ?: date('Y-m-d');
    $employeeCode = post('employee_code');
    if (!$name) $errors[] = 'Name is required.';
    if (!validate_email($email)) $errors[] = 'A valid email is required.';

    if (!$errors) {
        if ($employee) {
            db_exec("UPDATE users SET name=?,email=?,phone=?,department_id=?,designation=?,shift_id=?,joining_date=?,employee_code=? WHERE id=? AND company_id=?",
                [$name,$email,$phone,$departmentId,$designation,$shiftId,$joiningDate,$employeeCode,$id,$cid]);
            log_activity($cid, current_user_id(), 'update', 'employee', $id, "Updated employee $name");
            flash_set('success', 'Employee updated.');
        } else {
            $limit = check_company_limit($cid, 'employee_limit', 'users', "role='employee'");
            if (!$limit['allowed']) {
                $errors[] = 'Employee limit reached for your subscription plan.';
            } else {
                $password = post_raw('password') ?: generate_random_password();
                $username = post('username') ?: strstr($email, '@', true);
                $newId = db_exec("INSERT INTO users (company_id, role, employee_code, name, email, phone, department_id, designation, shift_id, joining_date, username, password, status)
                    VALUES (?,'employee',?,?,?,?,?,?,?,?,?,?,'active')",
                    [$cid, $employeeCode ?: generate_code('EMP', 3), $name, $email, $phone, $departmentId, $designation, $shiftId, $joiningDate, $username, hash_password($password)]);

                $deptName = '';
                foreach ($departments as $d) { if ($d['id'] == $departmentId) { $deptName = $d['name']; } }
                send_event_email($cid, $email, $name, 'employee_created', [
                    'employee_name' => $name, 'company_name' => db_val("SELECT name FROM companies WHERE id=?", [$cid]),
                    'department_name' => $deptName ?: 'Unassigned', 'username' => $username, 'temp_password' => $password,
                ]);
                log_activity($cid, current_user_id(), 'create', 'employee', $newId, "Created employee $name");
                flash_set('success', "Employee created. Login credentials have been emailed to $email.");
            }
        }
        if (!$errors) redirect(base_url('manager/employees.php'));
    }
}

$pageTitle = $employee ? 'Edit Employee' : 'New Employee';
$activeMenu = 'employees';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0"><?= out($pageTitle) ?></h4></div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . out($e) . '</li>'; ?></ul></div><?php endif; ?>

<form method="POST" class="qc-card" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-semibold">Full Name *</label><input class="form-control" name="name" required value="<?= out($employee['name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Employee ID</label><input class="form-control" name="employee_code" value="<?= out($employee['employee_code'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Email *</label><input type="email" class="form-control" name="email" required value="<?= out($employee['email'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Phone</label><input class="form-control" name="phone" value="<?= out($employee['phone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Department</label>
      <select class="form-select" name="department_id">
        <option value="">-- Select --</option>
        <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= ($employee['department_id'] ?? 0)==$d['id']?'selected':'' ?>><?= out($d['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Designation</label><input class="form-control" name="designation" value="<?= out($employee['designation'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Shift</label>
      <select class="form-select" name="shift_id">
        <option value="">-- Select --</option>
        <?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>" <?= ($employee['shift_id'] ?? 0)==$s['id']?'selected':'' ?>><?= out($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Joining Date</label><input type="date" class="form-control" name="joining_date" value="<?= out($employee['joining_date'] ?? date('Y-m-d')) ?>"></div>
    <?php if (!$employee): ?>
    <div class="col-md-6"><label class="form-label small fw-semibold">Username</label><input class="form-control" name="username" placeholder="auto from email"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Password</label><input class="form-control" name="password" placeholder="Leave blank to auto-generate"></div>
    <?php endif; ?>
  </div>
  <div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><?= $employee ? 'Save Changes' : 'Create Employee' ?></button>
    <a href="<?= base_url('manager/employees.php') ?>" class="btn btn-light border">Cancel</a>
  </div>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
