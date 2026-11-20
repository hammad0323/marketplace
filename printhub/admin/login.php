<?php
require __DIR__ . '/_init.php';
if (current_admin()) {
    redirect('admin/');
}
$email = '';
if (is_post()) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $err = admin_login($email, (string) ($_POST['password'] ?? ''));
    if (!$err) {
        redirect('admin/');
    }
    flash('error', $err);
}
panel_auth_header('Sign in', 'admin');
?>
<h2>Welcome back</h2><p class="text-muted small mb-4">Sign in to manage print shops and subscriptions.</p>
<form method="post" autocomplete="on"><?= csrf_field() ?>
    <?= f_input('email', 'Email', $email, 'email', ['required' => true, 'autofocus' => true, 'autocomplete' => 'username']) ?>
    <?= f_input('password', 'Password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn btn-primary w-100 py-2">Sign in</button>
</form>
<?php panel_auth_footer();
