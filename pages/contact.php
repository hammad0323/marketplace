<?php
/** /contact/ */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (is_post()) {
    csrf_check();
    $name = input('name'); $phone = input('phone'); $email = input('email'); $msg = input('message');
    if (input('website') !== '') redirect('contact/'); // honeypot
    if (mb_strlen($name) < 2 || mb_strlen($msg) < 5) {
        flash('danger', 'Please enter your name and message.');
        keep_old($_POST);
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Please enter a valid email.');
        keep_old($_POST);
    } elseif (!form_throttle('contact', 30)) {
        flash('warning', 'Please wait a little before sending another message.');
    } else {
        db_insert('INSERT INTO contact_messages (name, phone, email, subject, message) VALUES (?, ?, ?, ?, ?)', [mb_substr($name, 0, 120), mb_substr($phone, 0, 30), $email ?: null, mb_substr(input('subject'), 0, 190), mb_substr($msg, 0, 5000)]);
        notify_admin('contact', 'New message from ' . $name, str_limit($msg, 140), 'messages.php');
        clear_old();
        flash('success', 'Thank you! We will get back to you shortly.');
    }
    redirect('contact/');
}
$crumbs = [['Home', url('')], ['Contact Us', null]];
seo_apply(seo_get('static', 0, 'contact'), ['title' => 'Contact Us', 'description' => 'Contact Fahim Store - phone, WhatsApp, email and store address.', 'canonical' => abs_url('contact/')]);
page_set(['breadcrumbs' => $crumbs]);
page_add_schema(schema_local_business());
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html($crumbs) ?><h1>Contact Us</h1></div>
  <?= render_flashes() ?>
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="box h-100">
        <h2 class="box-title">Visit or call us</h2>
        <ul class="list-unstyled footer-contact">
          <li><i class="bi bi-geo-alt text-brand"></i><span><?= e(setting('store_address')) ?>, <?= e(setting('store_city')) ?></span></li>
          <li><i class="bi bi-telephone text-brand"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li>
          <?php if ($wa = setting('store_whatsapp')): ?><li><i class="bi bi-whatsapp text-success"></i><a href="<?= e(whatsapp_link($wa, 'Assalam-o-Alaikum Fahim Store')) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></li><?php endif; ?>
          <li><i class="bi bi-envelope text-brand"></i><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li>
          <li><i class="bi bi-clock text-brand"></i><?= e(setting('opening_hours')) ?></li>
        </ul>
        <?php if (setting('store_lat') && setting('store_lng')): ?>
        <a class="btn btn-outline-brand mt-2" target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= e(setting('store_lat')) ?>,<?= e(setting('store_lng')) ?>"><i class="bi bi-map me-1"></i>Open in Google Maps</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-7">
      <form method="post" class="box">
        <?= csrf_field() ?>
        <h2 class="box-title">Send us a message</h2>
        <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label" for="cName">Name *</label><input id="cName" name="name" class="form-control" required maxlength="120" value="<?= e(old('name')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="cPhone">Phone</label><input id="cPhone" name="phone" class="form-control" maxlength="30" value="<?= e(old('phone')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="cEmail">Email</label><input id="cEmail" type="email" name="email" class="form-control" maxlength="190" value="<?= e(old('email')) ?>"></div>
          <div class="col-md-6"><label class="form-label" for="cSub">Subject</label><input id="cSub" name="subject" class="form-control" maxlength="190" value="<?= e(old('subject')) ?>"></div>
          <div class="col-12"><label class="form-label" for="cMsg">Message *</label><textarea id="cMsg" name="message" class="form-control" rows="5" required><?= e(old('message')) ?></textarea></div>
        </div>
        <button class="btn btn-brand mt-3">Send message</button>
      </form>
    </div>
  </div>
</div>
<?php clear_old(); require FS_ROOT . '/partials/footer.php';
