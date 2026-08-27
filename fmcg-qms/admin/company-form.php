<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$id = get_int('id');
$company = $id ? db_one("SELECT * FROM companies WHERE id=?", [$id]) : null;
if ($id && !$company) { flash_set('danger', 'Company not found.'); redirect(base_url('admin/companies.php')); }

$plans = db_all("SELECT * FROM subscription_plans WHERE status='active' ORDER BY price_monthly", []);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $data = [
        'name' => post('name'), 'code' => strtoupper(post('code') ?: generate_code('CO', 3)),
        'industry' => post('industry'), 'address' => post('address'), 'city' => post('city'), 'country' => post('country'),
        'contact_person' => post('contact_person'), 'email' => post('email'), 'phone' => post('phone'), 'website' => post('website'),
        'employee_limit' => post_int('employee_limit', 15), 'department_limit' => post_int('department_limit', 5),
        'tool_limit' => post_int('tool_limit', 15), 'storage_limit_mb' => post_int('storage_limit_mb', 500),
        'ai_usage_limit' => post_int('ai_usage_limit', 100), 'subscription_plan_id' => post_int('subscription_plan_id') ?: null,
        'start_date' => post('start_date') ?: date('Y-m-d'), 'expiry_date' => post('expiry_date') ?: null,
        'status' => post('status', 'active'),
    ];
    if (!$data['name']) $errors[] = 'Company name is required.';
    if (!validate_email($data['email'])) $errors[] = 'A valid company email is required.';
    if (!$company && (!validate_email(post('manager_email')) )) $errors[] = 'A valid manager email is required.';

    if (!$errors) {
        if ($company) {
            db_exec("UPDATE companies SET name=?,code=?,industry=?,address=?,city=?,country=?,contact_person=?,email=?,phone=?,website=?,
                employee_limit=?,department_limit=?,tool_limit=?,storage_limit_mb=?,ai_usage_limit=?,subscription_plan_id=?,start_date=?,expiry_date=?,status=?
                WHERE id=?", [
                $data['name'],$data['code'],$data['industry'],$data['address'],$data['city'],$data['country'],$data['contact_person'],
                $data['email'],$data['phone'],$data['website'],$data['employee_limit'],$data['department_limit'],$data['tool_limit'],
                $data['storage_limit_mb'],$data['ai_usage_limit'],$data['subscription_plan_id'],$data['start_date'],$data['expiry_date'],$data['status'],$id,
            ]);
            log_activity(null, current_user_id(), 'update', 'company', $id, 'Updated company ' . $data['name']);
            flash_set('success', 'Company updated successfully.');
            redirect(base_url('admin/companies.php'));
        } else {
            $companyId = db_exec("INSERT INTO companies (name,code,industry,address,city,country,contact_person,email,phone,website,
                employee_limit,department_limit,tool_limit,storage_limit_mb,ai_usage_limit,subscription_plan_id,start_date,expiry_date,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [
                $data['name'],$data['code'],$data['industry'],$data['address'],$data['city'],$data['country'],$data['contact_person'],
                $data['email'],$data['phone'],$data['website'],$data['employee_limit'],$data['department_limit'],$data['tool_limit'],
                $data['storage_limit_mb'],$data['ai_usage_limit'],$data['subscription_plan_id'],$data['start_date'],$data['expiry_date'],$data['status'],
            ]);

            $mgrName = post('manager_name'); $mgrEmail = post('manager_email');
            $mgrPassword = post_raw('manager_password') ?: generate_random_password();
            db_exec("INSERT INTO users (company_id, role, name, email, username, password, status, joining_date)
                     VALUES (?,'manager',?,?,?,?,'active',CURDATE())",
                [$companyId, $mgrName, $mgrEmail, strstr($mgrEmail, '@', true), hash_password($mgrPassword)]);

            db_exec("INSERT INTO company_subscriptions (company_id, plan_id, start_date, status) VALUES (?,?,?, 'active')",
                [$companyId, $data['subscription_plan_id'], $data['start_date']]);

            send_event_email(null, $mgrEmail, $mgrName, 'employee_created', [
                'employee_name' => $mgrName, 'company_name' => $data['name'], 'department_name' => 'Quality Management',
                'username' => strstr($mgrEmail, '@', true), 'temp_password' => $mgrPassword,
            ]);

            log_activity(null, current_user_id(), 'create', 'company', $companyId, 'Created company ' . $data['name'] . ' with manager ' . $mgrEmail);
            flash_set('success', "Company created. Manager account for $mgrEmail has been provisioned and emailed.");
            redirect(base_url('admin/companies.php'));
        }
    }
}

$pageTitle = $company ? 'Edit Company' : 'New Company';
$activeMenu = 'companies';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0"><?= out($pageTitle) ?></h4></div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . out($e) . '</li>'; ?></ul></div><?php endif; ?>

<form method="POST">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="qc-card mb-3">
        <h3 class="mb-3">Company Details</h3>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-semibold">Company Name *</label><input class="form-control" name="name" required value="<?= out($company['name'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Company Code</label><input class="form-control" name="code" placeholder="auto-generated" value="<?= out($company['code'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Industry</label><input class="form-control" name="industry" value="<?= out($company['industry'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Website</label><input class="form-control" name="website" value="<?= out($company['website'] ?? '') ?>"></div>
          <div class="col-md-12"><label class="form-label small fw-semibold">Address</label><input class="form-control" name="address" value="<?= out($company['address'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">City</label><input class="form-control" name="city" value="<?= out($company['city'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Country</label><input class="form-control" name="country" value="<?= out($company['country'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Contact Person</label><input class="form-control" name="contact_person" value="<?= out($company['contact_person'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Company Email *</label><input type="email" class="form-control" name="email" required value="<?= out($company['email'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Phone</label><input class="form-control" name="phone" value="<?= out($company['phone'] ?? '') ?>"></div>
        </div>
      </div>

      <?php if (!$company): ?>
      <div class="qc-card mb-3">
        <h3 class="mb-3">Company Manager Account</h3>
        <p class="small text-muted">This account is created automatically and credentials are emailed to the manager.</p>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-semibold">Manager Name *</label><input class="form-control" name="manager_name" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Manager Email *</label><input type="email" class="form-control" name="manager_email" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Manager Password</label><input class="form-control" name="manager_password" placeholder="Leave blank to auto-generate"></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="qc-card">
        <h3 class="mb-3">Limits</h3>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label small fw-semibold">Employee Limit</label><input type="number" class="form-control" name="employee_limit" value="<?= (int)($company['employee_limit'] ?? 15) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Department Limit</label><input type="number" class="form-control" name="department_limit" value="<?= (int)($company['department_limit'] ?? 5) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Tool Limit</label><input type="number" class="form-control" name="tool_limit" value="<?= (int)($company['tool_limit'] ?? 15) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Storage Limit (MB)</label><input type="number" class="form-control" name="storage_limit_mb" value="<?= (int)($company['storage_limit_mb'] ?? 500) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">AI Usage Limit</label><input type="number" class="form-control" name="ai_usage_limit" value="<?= (int)($company['ai_usage_limit'] ?? 100) ?>"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="qc-card mb-3">
        <h3 class="mb-3">Subscription</h3>
        <label class="form-label small fw-semibold">Plan</label>
        <select class="form-select mb-3" name="subscription_plan_id">
          <?php foreach ($plans as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($company['subscription_plan_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= out($p['name']) ?> - $<?= out($p['price_monthly']) ?>/mo</option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Start Date</label>
        <input type="date" class="form-control mb-3" name="start_date" value="<?= out($company['start_date'] ?? date('Y-m-d')) ?>">
        <label class="form-label small fw-semibold">Expiry Date</label>
        <input type="date" class="form-control mb-3" name="expiry_date" value="<?= out($company['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'))) ?>">
        <label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status">
          <option value="active" <?= ($company['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($company['status'] ?? '')==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary w-100"><?= $company ? 'Save Changes' : 'Create Company' ?></button>
      <a href="<?= base_url('admin/companies.php') ?>" class="btn btn-light border w-100 mt-2">Cancel</a>
    </div>
  </div>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
