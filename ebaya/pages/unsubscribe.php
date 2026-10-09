<?php
/** Newsletter unsubscribe via the token in every marketing email. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$token = (string)get('token', post('token'));
$sub = preg_match('/^[a-f0-9]{64}$/', $token) ? db_one('SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ?', [$token]) : null;
$done = false;
if ($sub && is_post()) {
    csrf_check();
    db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [(int)$sub['id']]);
    $done = true;
}
seo_set(['title' => 'Unsubscribe', 'noindex' => true]);
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section"><div class="container-eb auth-wrap"><div class="auth-card text-center">
  <h1 class="page-title">Newsletter</h1>
  <?php if (!$sub): ?><p>This unsubscribe link is not valid.</p>
  <?php elseif ($done || $sub['status'] === 'unsubscribed'): ?><p>You have been unsubscribed. We're sorry to see you go.</p>
  <?php else: ?>
    <p>Unsubscribe <strong><?= e($sub['email']) ?></strong> from Ebaya emails?</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-eb btn-primary-eb">Unsubscribe</button></form>
  <?php endif; ?>
</div></div></section>
<?php require ROOT_PATH . '/templates/footer.php';
