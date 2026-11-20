<?php
require __DIR__ . '/_init.php';
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (is_post()) {
    if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        flash('error', 'The passwords do not match.');
    } elseif ($err = owner_reset_apply($token, (string) $_POST['password'])) {
        flash('error', $err);
    } else {
        flash('success', 'Password updated. Please sign in.');
        redirect('owner/login.php');
    }
    redirect('owner/reset.php?token=' . urlencode($token));
}
panel_auth_header('New password', 'owner');
?>
<h2>Choose a new password</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
<?= f_input('password', 'New password', '', 'password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password'], 'At least 8 characters with letters and numbers.') ?>
<?= f_input('confirm', 'Confirm password', '', 'password', ['required' => true, 'autocomplete' => 'new-password']) ?><button class="btn btn-primary w-100">Save password</button></form>
<?php panel_auth_footer();
