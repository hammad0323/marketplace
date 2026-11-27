<?php
/**
 * /portal/forgot — sends a reset link to the profile's email address.
 * Uses the existing th_venue.reset_token (stores a SHA-256 hash of the token) and token_expiry columns.
 */
$sent = false;
if (is_post()) {
    require_post_csrf();
    $ident = post('identity');
    $key = 'venuereset|ip|' . client_ip();
    if (throttle_wait($key) === 0) {
        throttle_fail($key);
        $v = db_one('SELECT venue_id, venue_title, email_address FROM th_venue WHERE (username = ? OR email_address = ?) AND username IS NOT NULL AND username <> \'\' LIMIT 1', 'ss', array($ident, $ident));
        if ($v && filter_var($v['email_address'], FILTER_VALIDATE_EMAIL)) {
            $token = bin2hex(random_bytes(32));
            db_exec('UPDATE th_venue SET reset_token = ?, token_expiry = ? WHERE venue_id = ?', 'ssi', array(hash('sha256', $token), (string)(time() + 3600), (int)$v['venue_id']));
            $link = SITE_URL . url(PORTAL_PATH . '/reset?id=' . (int)$v['venue_id'] . '&token=' . $token);
            send_mail($v['email_address'], 'Reset your The Hashir portal password', "Assalamu alaikum,\n\nSomeone (hopefully you) asked to reset the password for " . trim($v['venue_title']) . " on The Hashir.\nUse this link within one hour:\n" . $link . "\n\nIf you didn't ask for this, ignore this email.\n\nThe Hashir");
            log_activity('venue', (int)$v['venue_id'], 'auth.reset_requested', 'venue', (int)$v['venue_id']);
        }
    }
    $sent = true;
}
auth_card_start('venue', 'Reset your password', 'Enter your username or the email address on your profile.');
if ($sent): ?>
  <div class="alert alert-success">If we found a matching account with an email address, a reset link is on its way. No email? <a href="<?= e(url('/support?topic=listing#contact')) ?>">Contact us</a>.</div>
<?php else: ?>
<form method="post"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="id">Username or email</label><input class="form-control form-control-lg" id="id" name="identity" required autofocus></div>
  <button class="btn btn-primary btn-lg w-100">Send reset link</button>
</form>
<?php endif;
auth_card_end(array(array('Back to sign in', url(PORTAL_PATH . '/login'))));
