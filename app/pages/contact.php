<?php
meta_set(['title' => setting('contact_seo_title', 'Contact us'), 'description' => setting('contact_meta_description', 'Questions about an order or a product? Get in touch with the ' . setting('site_name', 'Beglet') . ' team.')]);
$errors = [];
$sent = false;
if (is_post()) {
    require_csrf();
    $d = ['name' => input('name'), 'email' => mb_strtolower(input('email')), 'phone' => input('phone'), 'subject' => input('subject'), 'message' => input('message')];
    if (input('website') !== '') {
        $sent = true; // honeypot: silently accept
    } else {
        if (mb_strlen($d['name']) < 2 || mb_strlen($d['name']) > 120) { $errors['name'] = 'Please enter your name.'; }
        if (!valid_email($d['email'])) { $errors['email'] = 'Please enter a valid email.'; }
        if ($d['phone'] !== '' && !valid_phone($d['phone'])) { $errors['phone'] = 'Please enter a valid phone number.'; }
        if (mb_strlen($d['subject']) < 2 || mb_strlen($d['subject']) > 190) { $errors['subject'] = 'Please add a subject.'; }
        if (mb_strlen($d['message']) < 10 || mb_strlen($d['message']) > 5000) { $errors['message'] = 'Your message should be 10–5000 characters.'; }
        if (!$errors && !rate_limit('contact', client_ip(), 5, 3600)) { $errors['message'] = 'Too many messages sent. Please try again later.'; }
        if (!$errors) {
            db_insert('contact_messages', $d + ['ip_address' => client_ip()]);
            $to = setting('notification_email', setting('support_email', ''));
            if ($to) {
                send_template_email($to, 'Contact form: ' . $d['subject'], 'contact_message', ['m' => $d]);
            }
            $sent = true;
        }
    }
}
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$whatsapp = preg_replace('/[^0-9]/', '', (string) setting('whatsapp_number', ''));
partial('header');
?>
<section class="page-hero"><div class="container container--wide page-hero__inner"><p class="eyebrow">Customer care</p><h1 class="page-title" data-reveal="fade-up">Contact us</h1><p class="page-intro"><?= e(setting('contact_intro', 'We usually reply within one working day.')) ?></p></div></section>
<div class="container container--wide page-pad contact">
  <div class="contact__grid">
    <div class="contact__info" data-reveal="fade-up">
      <?php if (setting('contact_phone')): ?><div class="info-card"><h3><i class="bi bi-telephone"></i> Call</h3><p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></p></div><?php endif; ?>
      <?php if ($whatsapp): ?><div class="info-card"><h3><i class="bi bi-whatsapp"></i> WhatsApp</h3><p><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">Chat with us</a></p></div><?php endif; ?>
      <?php if (setting('support_email')): ?><div class="info-card"><h3><i class="bi bi-envelope"></i> Email</h3><p><a href="mailto:<?= e(setting('support_email')) ?>"><?= e(setting('support_email')) ?></a></p></div><?php endif; ?>
      <?php if (setting('business_address')): ?><div class="info-card"><h3><i class="bi bi-geo-alt"></i> Visit</h3><p><?= nl2br(e(setting('business_address'))) ?></p><?php if (setting('business_hours')): ?><p class="text-muted"><?= e(setting('business_hours')) ?></p><?php endif; ?></div><?php endif; ?>
    </div>
    <div class="contact__form" data-reveal="fade-up" style="--reveal-delay:120ms">
      <?php if ($sent): ?>
        <div class="empty-state empty-state--compact"><i class="bi bi-envelope-check"></i><h2>Thank you</h2><p>Your message has been received. We will be in touch shortly.</p></div>
      <?php else: ?>
        <form method="post" class="row g-3" novalidate>
          <?= csrf_field() ?>
          <div class="col-md-6"><label class="form-label" for="c-name">Name</label><input class="form-control" id="c-name" name="name" maxlength="120" required value="<?= e(input('name')) ?>"><?= $err('name') ?></div>
          <div class="col-md-6"><label class="form-label" for="c-email">Email</label><input class="form-control" type="email" id="c-email" name="email" maxlength="190" required value="<?= e(input('email')) ?>"><?= $err('email') ?></div>
          <div class="col-md-6"><label class="form-label" for="c-phone">Phone <span class="text-muted">(optional)</span></label><input class="form-control" id="c-phone" name="phone" maxlength="30" value="<?= e(input('phone')) ?>"><?= $err('phone') ?></div>
          <div class="col-md-6"><label class="form-label" for="c-subject">Subject</label><input class="form-control" id="c-subject" name="subject" maxlength="190" required value="<?= e(input('subject')) ?>"><?= $err('subject') ?></div>
          <div class="col-12"><label class="form-label" for="c-message">Message</label><textarea class="form-control" id="c-message" name="message" rows="6" maxlength="5000" required><?= e(input('message')) ?></textarea><?= $err('message') ?></div>
          <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="col-12"><button class="btn-lux">Send message</button></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php partial('footer');
