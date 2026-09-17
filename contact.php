<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
$page = wh_fetch_one("SELECT * FROM pages WHERE business_id=? AND page_key='contact'", 'i', [$businessId]);

$errors = [];
$sent = false;
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $name = wh_input_post('name');
    $email = wh_input_post('email');
    $phone = wh_input_post('phone');
    $subject = wh_input_post('subject');
    $message = wh_input_post('message');

    if ($name === '') $errors[] = 'Please enter your name.';
    if ($phone === '' && $email === '') $errors[] = 'Please provide a phone number or email.';
    if ($message === '') $errors[] = 'Please enter a message.';

    if (!$errors) {
        wh_execute(
            'INSERT INTO contact_messages (business_id, name, email, phone, subject, message) VALUES (?,?,?,?,?,?)',
            'isssss',
            [$businessId, $name, $email, $phone, $subject, $message]
        );
        wh_add_notification($businessId, 'contact_message', 'New contact message from ' . $name,
            ($phone ?: $email) . ' — ' . mb_strimwidth($message, 0, 100, '…'), null);
        $sent = true;
        $old = [];
    }
}

$pageTitle = $page['title'] ?? 'Contact Us';
$seoPageKey = 'contact';
$activeNav = 'contact';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1><?= e($page['title'] ?? 'Contact Us') ?></h1>
    <p>We'd love to hear about your event.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="two-col" style="display:grid;grid-template-columns:1fr 1.2fr;gap:40px;">
      <div class="reveal">
        <?= $page['content'] ?? '' ?>
        <div style="margin-top:24px;display:flex;flex-direction:column;gap:14px;">
          <?php if (!empty($settings['phone'])): ?><div><i class="fa-solid fa-phone" style="color:var(--primary);width:22px;"></i> <a href="tel:<?= e($settings['phone']) ?>"><?= e($settings['phone']) ?></a></div><?php endif; ?>
          <?php if (!empty($settings['whatsapp'])): ?><div><i class="fa-brands fa-whatsapp" style="color:var(--primary);width:22px;"></i> <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $settings['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp Us</a></div><?php endif; ?>
          <?php if (!empty($settings['email'])): ?><div><i class="fa-solid fa-envelope" style="color:var(--primary);width:22px;"></i> <a href="mailto:<?= e($settings['email']) ?>"><?= e($settings['email']) ?></a></div><?php endif; ?>
          <?php if (!empty($settings['address'])): ?><div><i class="fa-solid fa-location-dot" style="color:var(--primary);width:22px;"></i> <?= e($settings['address']) ?></div><?php endif; ?>
          <?php if (!empty($settings['opening_hours'])): ?><div><i class="fa-solid fa-clock" style="color:var(--primary);width:22px;"></i> <?= e($settings['opening_hours']) ?></div><?php endif; ?>
        </div>
        <?php if (!empty($settings['google_maps_embed'])): ?>
        <div style="margin-top:24px;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);"><?= $settings['google_maps_embed'] ?></div>
        <?php endif; ?>
      </div>
      <div class="form-card reveal">
        <?php if ($sent): ?>
          <div class="alert alert-success">Thank you! Your message has been sent — our team will get back to you shortly.</div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
          <?= wh_csrf_field() ?>
          <div class="form-row">
            <div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($old['phone'] ?? '') ?>"></div>
          </div>
          <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>"></div>
          <div class="form-group"><label>Subject</label><input type="text" name="subject" value="<?= e($old['subject'] ?? '') ?>"></div>
          <div class="form-group"><label>Message <span class="req">*</span></label><textarea name="message" required><?= e($old['message'] ?? '') ?></textarea></div>
          <button type="submit" class="btn btn-primary btn-block">Send Message</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
