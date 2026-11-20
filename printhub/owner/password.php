<?php
require __DIR__ . '/_init.php';
if (is_post()) {
    if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        flash('error', 'The new passwords do not match.');
    } elseif ($err = owner_change_password($OWNER['user'], (string) ($_POST['current_password'] ?? ''), (string) $_POST['new_password'], $OWNER['token'])) {
        flash('error', $err);
    } else {
        flash('success', 'Password changed. Other devices were signed out.');
        redirect('owner/');
    }
    redirect('owner/password.php');
}
owner_header('Change password');
?>
<div class="row g-3"><div class="col-lg-5"><div class="p-card"><div class="p-card-body">
<form method="post"><?= csrf_field() ?>
<?= f_input('current_password', 'Current password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
<?= f_input('new_password', 'New password', '', 'password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password'], 'At least 8 characters with letters and numbers.') ?>
<?= f_input('confirm_password', 'Confirm new password', '', 'password', ['required' => true, 'autocomplete' => 'new-password']) ?>
<button class="btn btn-primary">Update password</button></form></div></div></div></div>
<?php owner_footer();
