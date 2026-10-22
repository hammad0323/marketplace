<?php
seo_set(['title' => 'Unsubscribe', 'noindex' => true]);
$token = input('token', '', 'get');
$sub = preg_match('/^[a-f0-9]{40}$/', $token) ? db_row('SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ?', [$token]) : null;
$done = false;
if ($sub && is_post()) {
    csrf_check();
    db_exec("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?", [(int) $sub['id']]);
    db_exec('UPDATE customers SET newsletter = 0 WHERE email = ?', [$sub['email']]);
    $done = true;
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-narrow text-center">
    <h1 class="uc-page-title">Newsletter</h1>
    <?php if (!$sub): ?>
        <p>This unsubscribe link is not valid. If you keep receiving emails, please <a href="<?= e(url('contact')) ?>">contact us</a>.</p>
    <?php elseif ($done || $sub['status'] === 'unsubscribed'): ?>
        <p>You have been unsubscribed. <strong><?= e($sub['email']) ?></strong> will no longer receive marketing emails.</p>
    <?php else: ?>
        <p>Unsubscribe <strong><?= e($sub['email']) ?></strong> from <?= e(store_name()) ?> marketing emails?</p>
        <form method="post"><?= csrf_field() ?><button class="uc-btn" type="submit">Unsubscribe</button></form>
    <?php endif; ?>
</section>
<?php require UC_ROOT . '/includes/footer.php';
