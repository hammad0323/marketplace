<?php
require __DIR__ . '/includes/bootstrap.php';

$sent = false;
$errors = [];
if (is_post()) {
    require_csrf();
    if (post('website') !== '') { // honeypot
        redirect('contact');
    }
    $d = ['name' => mb_substr(post('name'), 0, 120), 'email' => mb_substr(post('email'), 0, 190), 'phone' => mb_substr(post('phone'), 0, 40), 'subject' => mb_substr(post('subject'), 0, 200), 'message' => mb_substr(post('message'), 0, 5000)];
    if ($d['name'] === '') $errors[] = 'Please enter your name.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    if ($d['email'] === '' && $d['phone'] === '') $errors[] = 'Please share an email or phone number so we can reply.';
    if (mb_strlen($d['message']) < 5) $errors[] = 'Please write a message.';
    if (!$errors) {
        q('INSERT INTO messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)', array_values($d));
        send_mail(setting('email'), 'New enquiry: ' . ($d['subject'] ?: $d['name']), '<p><b>' . e($d['name']) . '</b> · ' . e($d['email']) . ' · ' . e($d['phone']) . '</p><p>' . nl2br(e($d['message'])) . '</p>');
        $sent = true;
    }
}
$seo = ['title' => 'Contact Us | ' . setting('site_name'), 'description' => 'Get in touch with ' . setting('site_name') . ' for orders, bridal appointments and enquiries.'];
require ROOT . '/includes/header.php';
?>
<section class="page-hero" style="--bh:320px">
  <div class="page-hero__bg" data-parallax="0.3" style="background-image:url('<?= e(img('assets/images/demo/banner-1.svg')) ?>')"></div>
  <div class="page-hero__overlay"></div>
  <div class="container page-hero__content"><span class="eyebrow" data-reveal>We'd love to hear from you</span><h1 class="display" data-reveal data-delay="1">Contact Us</h1></div>
</section>
<section class="section">
  <div class="container contact-layout">
    <div class="contact-info" data-reveal>
      <h2 class="section-title sm">Visit or Reach Us</h2>
      <p>For orders, bridal consultations, custom sizing or any question — our team is here to help.</p>
      <ul class="contact-list lg">
        <?php if (setting('address')): ?><li><?= icon('pin', 20) ?><span><?= e(setting('address')) ?></span></li><?php endif; ?>
        <?php if (setting('phone')): ?><li><?= icon('phone', 20) ?><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
        <?php if (setting('whatsapp')): ?><li><?= icon('whatsapp', 20) ?><a href="https://wa.me/<?= e(preg_replace('~\D~', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></li><?php endif; ?>
        <?php if (setting('email')): ?><li><?= icon('mail', 20) ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        <?php if (setting('business_hours')): ?><li><?= icon('clock', 20) ?><span><?= e(setting('business_hours')) ?></span></li><?php endif; ?>
      </ul>
      <?php if (setting('map_embed')): ?><div class="map"><?= setting('map_embed') ?></div><?php endif; ?>
    </div>
    <div class="panel" data-reveal data-delay="1">
      <?php if ($sent): ?>
        <div class="success-head"><div class="success-check"><?= icon('check', 36) ?></div><h3>Thank you!</h3><p>Your message has been sent. We will get back to you shortly.</p></div>
      <?php else: ?>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
          <label>Your Name *<input type="text" name="name" value="<?= e(post('name')) ?>" required></label>
          <label>Phone<input type="tel" name="phone" value="<?= e(post('phone')) ?>"></label>
          <label>Email<input type="email" name="email" value="<?= e(post('email')) ?>"></label>
          <label>Subject<input type="text" name="subject" value="<?= e(post('subject')) ?>"></label>
          <label class="span-2">Message *<textarea name="message" rows="5" required><?= e(post('message')) ?></textarea></label>
          <div class="span-2"><button class="btn btn-gold" type="submit">Send Message</button></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
