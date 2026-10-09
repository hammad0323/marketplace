<?php
meta_set(['title' => 'Unsubscribe', 'noindex' => true]);
$token = input('token', input('token', '', 'get'));
$sub = preg_match('/^[a-f0-9]{64}$/', $token) ? db_one('SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ?', [$token]) : null;
$done = false;
if ($sub && is_post()) {
    require_csrf();
    db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [$sub['id']]);
    $done = true;
}
partial('header');
?>
<div class="container container--narrow page-pad">
  <div class="auth-card text-center">
    <h1 class="page-title">Newsletter</h1>
    <?php if (!$sub): ?>
      <p>This unsubscribe link is not valid. If you keep receiving emails, please <a href="<?= e(path_url('contact')) ?>">contact us</a>.</p>
    <?php elseif ($done || $sub['status'] === 'unsubscribed'): ?>
      <p><?= e($sub['email']) ?> has been unsubscribed. You will no longer receive marketing emails.</p>
    <?php else: ?>
      <p>Unsubscribe <strong><?= e($sub['email']) ?></strong> from <?= e(setting('site_name', 'Beglet')) ?> emails?</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn-lux">Unsubscribe</button></form>
    <?php endif; ?>
  </div>
</div>
<?php partial('footer');
