<?php
/**
 * /manage/users — admins (th_admin_table), venue portal accounts (th_venue.username/password), password security.
 * tab=admins | venues | security
 */
$me = require_admin('users');
$tab = in_array(str_param('tab', 10), array('admins', 'venues', 'security'), true) ? str_param('tab', 10) : 'admins';
$legacySql = "password IS NOT NULL AND password <> '' AND password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon%'";
if (is_post()) {
    require_post_csrf();
    $action = post('action');
    if ($action === 'save_admin') {
        $id = post_int('admin_id');
        $name = mb_substr(post('admin_name'), 0, 150, 'UTF-8');
        $user = mb_substr(post('user_name'), 0, 100, 'UTF-8');
        $email = mb_substr(post('admin_email'), 0, 100, 'UTF-8');
        $role = post('role') === 'editor' ? 'editor' : 'super';
        $status = post('admin_status') === '1' ? 1 : 0;
        $pass = (string)post('password');
        $err = '';
        if ($name === '' || $user === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Name, username and a valid email are required.';
        } elseif (db_val('SELECT admin_id FROM th_admin_table WHERE (user_name = ? OR admin_email = ?) AND admin_id <> ?', 'ssi', array($user, $email, $id))) {
            $err = 'That username or email is already used.';
        } elseif ((!$id || $pass !== '') && ($p = password_problem($pass, post('password_confirm'))) !== '') {
            $err = $p;
        } elseif ($id === $me['id'] && ($status === 0 || $role !== 'super')) {
            $err = 'You can\'t deactivate or downgrade your own account.';
        }
        if ($err !== '') {
            flash('danger', $err);
        } else {
            if ($id) {
                db_exec('UPDATE th_admin_table SET admin_name = ?, user_name = ?, admin_email = ?, admin_status = ? WHERE admin_id = ?', 'sssii', array($name, $user, $email, $status, $id));
                if ($pass !== '') {
                    db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make($pass), $id));
                }
                log_me('admin.update', 'admin', $id, null, array('name' => $name, 'user' => $user, 'email' => $email, 'role' => $role, 'status' => $status, 'password' => $pass !== '' ? 'changed' : 'unchanged'));
            } else {
                db_exec('INSERT INTO th_admin_table (admin_name, user_name, admin_email, admin_password, admin_status) VALUES (?, ?, ?, ?, ?)', 'ssssi', array($name, $user, $email, password_make($pass), $status));
                $id = db_last_id();
                log_me('admin.create', 'admin', $id, null, array('name' => $name, 'user' => $user, 'email' => $email, 'role' => $role));
            }
            $roles = admin_roles();
            $roles[(string)$id] = $role;
            storage_write('admin_roles.json', $roles);
            flash('success', 'Admin saved.');
        }
    } elseif ($action === 'venue_reset') {
        $id = post_int('venue_id');
        $pass = (string)post('password');
        $p = password_problem($pass, post('password_confirm'));
        if ($p !== '') {
            flash('danger', $p);
        } elseif (db_exec('UPDATE th_venue SET password = ?, reset_token = NULL, token_expiry = NULL WHERE venue_id = ?', 'si', array(password_make($pass), $id))) {
            log_me('venue.password_reset_by_admin', 'venue', $id);
            flash('success', 'Password changed. Share it with the mosque securely.');
        }
    } elseif ($action === 'migrate_all') {
        if (strtoupper(post('confirm_word')) !== 'HASH') {
            flash('danger', 'Type HASH to confirm.');
        } else {
            @set_time_limit(300);
            $n = 0;
            foreach (db_all("SELECT venue_id, password FROM th_venue WHERE $legacySql") as $r) {
                // MD5 digests can't be turned into bcrypt without the original password; they are migrated at the next login instead
                if (preg_match('/^[a-f0-9]{32}$/i', $r['password'])) {
                    continue;
                }
                db_exec('UPDATE th_venue SET password = ? WHERE venue_id = ?', 'si', array(password_make($r['password']), (int)$r['venue_id']));
                $n++;
            }
            foreach (db_all("SELECT admin_id, admin_password FROM th_admin_table WHERE admin_password NOT LIKE '\$2y\$%' AND admin_password NOT LIKE '\$argon%' AND admin_password <> ''") as $r) {
                if (preg_match('/^[a-f0-9]{32}$/i', $r['admin_password'])) {
                    continue;
                }
                db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make($r['admin_password']), (int)$r['admin_id']));
                $n++;
            }
            log_me('auth.migrate_all_passwords', 'system', null, null, array('accounts' => $n));
            flash('success', 'Hashed ' . plural($n, 'password') . '. Existing passwords keep working; they are just no longer stored as plain text.');
        }
    }
    bo_redirect('admin', 'users?tab=' . $tab);
}
bo_start('admin', 'Users');
?>
<ul class="nav nav-pills bo-pills mb-4">
  <li class="nav-item"><a class="nav-link<?= $tab === 'admins' ? ' active' : '' ?>" href="<?= e(ADMIN_SELF) ?>">Admins</a></li>
  <li class="nav-item"><a class="nav-link<?= $tab === 'venues' ? ' active' : '' ?>" href="<?= e(ADMIN_SELF . '?tab=venues') ?>">Mosque &amp; organization accounts</a></li>
  <li class="nav-item"><a class="nav-link<?= $tab === 'security' ? ' active' : '' ?>" href="<?= e(ADMIN_SELF . '?tab=security') ?>">Password security</a></li>
</ul>
<?php if ($tab === 'admins'):
    $admins = db_all('SELECT admin_id, admin_name, user_name, admin_email, admin_status, date, (admin_password LIKE \'$2y$%\') hashed FROM th_admin_table ORDER BY admin_id');
    $edit = isset($_GET['edit']) ? db_one('SELECT admin_id, admin_name, user_name, admin_email, admin_status FROM th_admin_table WHERE admin_id = ?', 'i', array(int_param('edit'))) : null;
?>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Name</th><th>Username / email</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr><td class="fw-semibold"><?= e($a['admin_name']) ?><?= (int)$a['admin_id'] === $me['id'] ? ' <span class="badge text-bg-light">you</span>' : '' ?></td><td class="small"><?= e($a['user_name']) ?><br><span class="text-muted"><?= e($a['admin_email']) ?></span></td>
        <td><?= admin_role((int)$a['admin_id']) === 'super' ? 'Super admin' : 'Editor' ?></td><td><?= status_badge((int)$a['admin_status'] === 1, 'Active', 'Inactive') ?><?= !$a['hashed'] ? ' <span class="badge text-bg-danger" title="Password stored as plain text">legacy password</span>' : '' ?></td>
        <td class="text-end"><a class="btn btn-sm btn-light" href="<?= e(ADMIN_SELF . '?edit=' . (int)$a['admin_id']) ?>"><i class="bi bi-pencil"></i></a></td></tr>
      <?php endforeach; ?>
      </tbody></table></div></div>
    <p class="small text-muted mt-2"><strong>Super admin</strong>: everything. <strong>Editor</strong>: directory, programs/services/events, prayer times, blogs, pages and approvals — not users, settings or the activity log.</p>
  </div>
  <div class="col-xl-5">
    <form method="post" class="card bo-card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="save_admin"><input type="hidden" name="admin_id" value="<?= $edit ? (int)$edit['admin_id'] : 0 ?>">
      <div class="card-header"><h2 class="h6 m-0"><?= $edit ? 'Edit admin' : 'Add an admin' ?></h2></div>
      <div class="card-body row g-3">
        <div class="col-12"><label class="form-label small">Full name</label><input class="form-control" name="admin_name" value="<?= e($edit ? $edit['admin_name'] : '') ?>" maxlength="150" required></div>
        <div class="col-sm-6"><label class="form-label small">Username</label><input class="form-control" name="user_name" value="<?= e($edit ? $edit['user_name'] : '') ?>" maxlength="100" required autocomplete="off"></div>
        <div class="col-sm-6"><label class="form-label small">Email</label><input class="form-control" type="email" name="admin_email" value="<?= e($edit ? $edit['admin_email'] : '') ?>" maxlength="100" required></div>
        <div class="col-sm-6"><label class="form-label small">Role</label><select class="form-select" name="role"><option value="super">Super admin</option><option value="editor"<?= $edit && admin_role((int)$edit['admin_id']) === 'editor' ? ' selected' : '' ?>>Editor</option></select></div>
        <div class="col-sm-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="admin_status" value="1" id="ast"<?= chk(!$edit || (int)$edit['admin_status'] === 1) ?>><label class="form-check-label" for="ast">Active</label></div></div>
        <div class="col-sm-6"><label class="form-label small"><?= $edit ? 'New password (optional)' : 'Password' ?></label><div class="input-group"><input class="form-control" id="apw" type="password" name="password" autocomplete="new-password" <?= $edit ? '' : 'required' ?>><button class="btn btn-outline-secondary" type="button" data-gen-pass="#apw,#apw2" title="Generate"><i class="bi bi-magic"></i></button></div></div>
        <div class="col-sm-6"><label class="form-label small">Repeat password</label><input class="form-control" id="apw2" type="password" name="password_confirm" autocomplete="new-password"></div>
      </div>
      <div class="card-footer d-flex gap-2"><button class="btn btn-primary">Save</button><?php if ($edit): ?><a class="btn btn-light" href="<?= e(ADMIN_SELF) ?>">Cancel</a><?php endif; ?></div>
    </form>
  </div>
</div>

<?php elseif ($tab === 'venues'):
    $q = str_param('q', 80);
    $page = max(1, int_param('page', 1));
    $per = 30;
    $w = "username IS NOT NULL AND username <> ''";
    $types = '';
    $params = array();
    if ($q !== '') {
        $w .= ' AND (username LIKE ? OR venue_title LIKE ? OR email_address LIKE ?)';
        $types = 'sss';
        $params = array('%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
    }
    $total = (int)db_val("SELECT COUNT(*) FROM th_venue WHERE $w", $types, $params);
    $rows = db_all("SELECT venue_id, venue_title, username, email_address, role_id, verify_status, (password LIKE '\$2y\$%' OR password LIKE '\$argon%') hashed FROM th_venue WHERE $w ORDER BY venue_title LIMIT " . (($page - 1) * $per) . ", $per", $types, $params);
?>
<form class="d-flex gap-2 mb-3" method="get"><input type="hidden" name="tab" value="venues"><input class="form-control" style="max-width:320px" name="q" value="<?= e($q) ?>" placeholder="Search username, name or email"><button class="btn btn-outline-primary">Search</button></form>
<div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Profile</th><th>Username</th><th>Password</th><th class="text-end">Set a new password</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'] . '#account')) ?>"><?= e(trim($r['venue_title'])) ?></a><div class="small text-muted"><?= e($r['email_address']) ?></div></td>
      <td class="small"><?= e($r['username']) ?></td>
      <td><?= $r['hashed'] ? '<span class="badge text-bg-success-subtle text-success-emphasis">Hashed</span>' : '<span class="badge text-bg-danger-subtle text-danger-emphasis">Legacy</span>' ?></td>
      <td class="text-end">
        <form method="post" class="d-inline-flex gap-1" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="venue_reset"><input type="hidden" name="venue_id" value="<?= (int)$r['venue_id'] ?>">
          <input class="form-control form-control-sm" type="password" name="password" id="vp<?= (int)$r['venue_id'] ?>" placeholder="New password" autocomplete="new-password" style="width:150px" required>
          <input class="form-control form-control-sm" type="password" name="password_confirm" id="vq<?= (int)$r['venue_id'] ?>" placeholder="Repeat" autocomplete="new-password" style="width:110px" required>
          <button class="btn btn-sm btn-light" type="button" data-gen-pass="#vp<?= (int)$r['venue_id'] ?>,#vq<?= (int)$r['venue_id'] ?>" title="Generate"><i class="bi bi-magic"></i></button>
          <button class="btn btn-sm btn-outline-primary">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div></div>

<?php else:
    $legacyV = (int)db_val("SELECT COUNT(*) FROM th_venue WHERE $legacySql");
    $md5V = (int)db_val("SELECT COUNT(*) FROM th_venue WHERE $legacySql AND password REGEXP '^[a-fA-F0-9]{32}$'");
    $legacyA = (int)db_val("SELECT COUNT(*) FROM th_admin_table WHERE admin_password NOT LIKE '\$2y\$%' AND admin_password NOT LIKE '\$argon%' AND admin_password <> ''");
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card bo-card">
      <div class="card-header"><h2 class="h6 m-0">Status</h2></div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex"><span>Venue accounts with a legacy password</span><strong class="ms-auto"><?= number_format($legacyV) ?></strong></li>
        <li class="list-group-item d-flex"><span class="small text-muted">… of which unsalted MD5 (migrated at next login)</span><span class="ms-auto small"><?= number_format($md5V) ?></span></li>
        <li class="list-group-item d-flex"><span>Admin accounts with a legacy password</span><strong class="ms-auto"><?= number_format($legacyA) ?></strong></li>
        <li class="list-group-item d-flex"><span>Automatic migration at login</span><strong class="ms-auto <?= PASSWORD_MIGRATE_ON_LOGIN ? 'text-success' : 'text-warning' ?>"><?= PASSWORD_MIGRATE_ON_LOGIN ? 'On' : 'Off' ?></strong></li>
      </ul>
      <div class="card-body small text-muted">New and changed passwords are always stored with <code>password_hash()</code>. With automatic migration on, a legacy password is replaced by a hash the moment its owner signs in successfully here.</div>
    </div>
  </div>
  <div class="col-lg-5">
    <form method="post" class="card bo-card border-danger-subtle"><?= csrf_field() ?><input type="hidden" name="action" value="migrate_all">
      <div class="card-header"><h2 class="h6 m-0 text-danger"><i class="bi bi-shield-lock me-1"></i> Hash all legacy passwords now</h2></div>
      <div class="card-body small">
        <p>Converts every plain-text password to a secure hash in one go. Users keep their current passwords.</p>
        <p class="text-danger fw-semibold">Before you do this: any other system that reads these columns directly (your old admin, the mobile app, display screens) must already check passwords with <code>password_verify()</code>, or its logins will stop working. Take a database backup first.</p>
        <input class="form-control" name="confirm_word" placeholder="Type HASH to confirm" autocomplete="off">
      </div>
      <div class="card-footer"><button class="btn btn-danger" <?= $legacyV + $legacyA ? '' : 'disabled' ?> data-confirm-click="Hash all legacy passwords now? Make sure you have a backup.">Hash passwords</button></div>
    </form>
  </div>
</div>
<?php endif;
bo_end();
