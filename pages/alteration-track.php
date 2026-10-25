<?php
seo_set(['title' => 'Track an Alteration', 'noindex' => true]);
$error = null;
if (is_post()) {
    csrf_check();
    if (!rate_limit('alt-track:' . client_ip(), 10, 600)) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $r = db_row('SELECT * FROM alteration_requests WHERE reference = ?', [strtoupper(input('reference', '', 'post'))]);
        $digits = fn($s) => substr(preg_replace('/\D/', '', $s), -10);
        if ($r && $digits(input('phone', '', 'post')) !== '' && $digits(input('phone', '', 'post')) === $digits($r['phone'])) {
            redirect('alterations/request/' . $r['reference'] . '?t=' . $r['access_token']);
        }
        $error = 'We could not find a request with those details.';
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-narrow">
    <h1 class="uc-page-title text-center">Track an Alteration</h1>
    <?php if ($error): ?><div class="uc-alert uc-alert--error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="uc-card-box"><?= csrf_field() ?>
        <label class="uc-field"><span>Reference</span><input type="text" name="reference" required placeholder="e.g. ALT2610251234"></label>
        <label class="uc-field"><span>Phone number</span><input type="tel" name="phone" required></label>
        <button class="uc-btn uc-btn--block">View request</button>
    </form>
</section>
<?php require UC_ROOT . '/includes/footer.php';
