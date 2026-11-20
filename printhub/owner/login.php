<?php
require __DIR__ . '/_init.php';
if (current_owner()) {
    redirect('owner/');
}
$username = '';
if (is_post()) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $r = owner_login($username, (string) ($_POST['password'] ?? ''));
    if (is_array($r)) {
        [$user, $shop] = $r;
        $token = token_issue('owner', (int) $user['id'], (int) $shop['id'], 'Web · ' . mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80));
        set_cookie('ph_owner', $token, TOKEN_TTL_DAYS * 86400);
        session_regenerate_id(true);
        redirect('owner/');
    }
    flash('error', $r);
}
panel_auth_header('Shop sign in', 'owner');
?>
<h2>Sign in to your shop</h2><p class="text-muted small mb-4">Use the username and password you received from <?= e(platform_name()) ?>.</p>
<form method="post"><?= csrf_field() ?>
    <?= f_input('username', 'Username or email', $username, 'text', ['required' => true, 'autofocus' => true, 'autocomplete' => 'username', 'autocapitalize' => 'none']) ?>
    <?= f_input('password', 'Password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn btn-primary w-100 py-2">Sign in</button>
</form>
<p class="text-center small mt-3 mb-0"><a href="<?= e(path('owner/forgot.php')) ?>">Forgot password?</a></p>
<p class="text-center small text-muted mt-2 mb-0">New shop? Contact <?= e(platform_name()) ?> to get an account<?= setting('support_phone') ? ' — ' . e(setting('support_phone')) : '' ?>.</p>
<?php panel_auth_footer();
