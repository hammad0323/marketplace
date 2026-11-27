<?php
/** /manage/venue-edit?id= — create / edit a mosque or organization, its login, and quick links. */
require_admin('venues');
$id = int_param('id');
$v = $id ? get_venue_any($id) : null;
if ($id && !$v) {
    flash('danger', 'Profile not found.');
    bo_redirect('admin', 'venues');
}
if (is_post()) {
    require_post_csrf();
    $action = post('action', 'profile');
    if ($action === 'credentials' && $v) {
        if (!admin_can('users')) {
            flash('danger', 'Only super admins can change logins.');
            bo_redirect('admin', 'venue-edit?id=' . $id . '#account');
        }
        $username = mb_substr(post('username'), 0, 200, 'UTF-8');
        $pass = (string)post('password');
        if ($username === '') {
            flash('danger', 'Enter a username.');
        } elseif (db_val('SELECT venue_id FROM th_venue WHERE username = ? AND venue_id <> ? LIMIT 1', 'si', array($username, $id))) {
            flash('danger', 'That username is already used by another profile.');
        } elseif ($pass !== '' && ($p = password_problem($pass, post('password_confirm'))) !== '') {
            flash('danger', $p);
        } else {
            if ($pass !== '') {
                db_exec('UPDATE th_venue SET username = ?, password = ?, reset_token = NULL, token_expiry = NULL WHERE venue_id = ?', 'ssi', array($username, password_make($pass), $id));
                log_me('venue.credentials_set', 'venue', $id, null, array('username' => $username, 'password' => 'changed'));
            } else {
                db_exec('UPDATE th_venue SET username = ? WHERE venue_id = ?', 'si', array($username, $id));
                log_me('venue.username_set', 'venue', $id, null, array('username' => $username));
            }
            flash('success', 'Login details saved.' . ($pass !== '' ? ' Share the new password with the mosque securely.' : ''));
        }
        bo_redirect('admin', 'venue-edit?id=' . $id . '#account');
    }
    $newId = venue_form_submit($id, true);
    if ($newId) {
        bo_redirect('admin', 'venue-edit?id=' . $newId);
    }
    bo_redirect('admin', 'venue-edit?' . ($id ? 'id=' . $id : 'type=' . str_param('type', 10)));
}
if (!empty($_SESSION['form_old']) && is_array($_SESSION['form_old'])) {
    $v = array_merge($v ? $v : array(), array_intersect_key($_SESSION['form_old'], array_flip(array_merge(array_keys(venue_text_fields()), array('about_details', 'city_id', 'role_id')))));
    unset($_SESSION['form_old']);
}
$isMosque = !$v || (int)(isset($v['role_id']) ? $v['role_id'] : (str_param('type', 10) === 'org' ? 2 : 1)) === 1;
$title = $id ? trim($v['venue_title']) : ($isMosque ? 'Add a mosque' : 'Add an organization');
bo_start('admin', $title, array('leaflet' => true, 'editor' => true, 'nav' => 'venues?type=' . ($isMosque ? 'mosque' : 'org')));
if ($id):
    $counts = array();
    foreach (db_all('SELECT grand_category_id gc, COUNT(*) n FROM th_details WHERE venue_id = ? GROUP BY grand_category_id', 'i', array($id)) as $r) {
        $counts[(int)$r['gc']] = (int)$r['n'];
    }
    $pending = pending_profile($id);
?>
<div class="venue-quick mb-4">
  <?php if ((int)$v['verify_status'] === 1): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url(venue_url($v))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View public profile</a><?php else: ?><span class="badge text-bg-warning">Hidden from the website</span><?php endif; ?>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'details?gc=2&venue=' . $id)) ?>"><i class="bi bi-journal-bookmark me-1"></i>Programs (<?= isset($counts[2]) ? $counts[2] : 0 ?>)</a>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'details?gc=3&venue=' . $id)) ?>"><i class="bi bi-hand-thumbs-up me-1"></i>Services (<?= isset($counts[3]) ? $counts[3] : 0 ?>)</a>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'details?gc=1&venue=' . $id)) ?>"><i class="bi bi-calendar-event me-1"></i>Events (<?= isset($counts[1]) ? $counts[1] : 0 ?>)</a>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'announcements?venue=' . $id)) ?>"><i class="bi bi-megaphone me-1"></i>Announcements</a>
  <?php if ($isMosque): ?>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'prayer?venue=' . $id)) ?>"><i class="bi bi-clock me-1"></i>Prayer times</a>
  <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'jummah?venue=' . $id)) ?>"><i class="bi bi-people me-1"></i>Jumu'ah</a>
  <?php endif; ?>
</div>
<?php if ($pending): ?><div class="alert alert-info">This profile has changes submitted from the portal waiting for review. <a href="<?= e(bo_url('admin', 'approvals')) ?>">Review them</a>.</div><?php endif; ?>
<?php endif; ?>

<?php venue_form($v ? $v : array(), true, ADMIN_SELF . ($id ? '?id=' . $id : '?type=' . ($isMosque ? 'mosque' : 'org'))); ?>

<?php if ($id && admin_can('users')): ?>
<form method="post" class="card bo-card mt-4" id="account" style="max-width:760px" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="action" value="credentials">
  <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-person-badge me-1"></i> Portal login</h2></div>
  <div class="card-body row g-3">
    <div class="col-md-4"><label class="form-label small">Username</label><input class="form-control" name="username" value="<?= e((string)$v['username']) ?>" maxlength="200" autocomplete="off" required></div>
    <div class="col-md-4"><label class="form-label small">New password</label><div class="input-group"><input class="form-control" id="vpw" name="password" type="password" autocomplete="new-password" placeholder="Leave empty to keep"><button class="btn btn-outline-secondary" type="button" data-gen-pass="#vpw,#vpw2" title="Generate"><i class="bi bi-magic"></i></button></div></div>
    <div class="col-md-4"><label class="form-label small">Repeat password</label><input class="form-control" id="vpw2" name="password_confirm" type="password" autocomplete="new-password"></div>
    <p class="small text-muted mb-0">Passwords are stored as secure hashes and can never be displayed. The mosque signs in at <code><?= e(SITE_URL . url(PORTAL_PATH . '/login')) ?></code>.</p>
  </div>
  <div class="card-footer"><button class="btn btn-outline-primary">Save login</button></div>
</form>
<?php endif; ?>
<?php bo_end();
