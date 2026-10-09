<?php
/** Contact page: editable intro (pages.slug = contact) + message form stored in contact_messages. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$page = $page ?? db_one("SELECT * FROM pages WHERE slug = 'contact'");
$errors = [];
$sent = false;
$v = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
if (is_post()) {
    csrf_check();
    foreach ($v as $k => $_) $v[$k] = trim((string)post($k));
    if (post('website') !== '') { $sent = true; } // honeypot: pretend success
    else {
        rate_limit_or_fail('contact', 5, 3600);
        if (!v_len($v['name'], 2, 120)) $errors['name'] = 'Please enter your name.';
        if (!v_email($v['email'])) $errors['email'] = 'Please enter a valid email.';
        if ($v['phone'] !== '' && !v_phone($v['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
        if (!v_len($v['message'], 10, 5000)) $errors['message'] = 'Please write a message (at least 10 characters).';
        if (!$errors) {
            db_insert('INSERT INTO contact_messages (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)',
                [$v['name'], strtolower($v['email']), $v['phone'] ?: null, mb_substr($v['subject'], 0, 190) ?: null, $v['message'], client_ip()]);
            if ($to = setting('admin_notify_email')) send_mail($to, 'New enquiry from ' . $v['name'], '<p>' . nl2br(e($v['message'])) . '</p><p>' . e($v['email']) . ' ' . e($v['phone']) . '</p>');
            $sent = true;
        }
    }
}
seo_set(['title' => $page['seo_title'] ?? 'Contact Us', 'description' => $page['meta_description'] ?? 'Get in touch with Ebaya customer care.', 'canonical' => abs_url('contact')]);
seo_breadcrumbs([['Contact', null]]);
require ROOT_PATH . '/templates/header.php';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
?>
<section class="listing-hero"><div class="container-eb">
  <?php include ROOT_PATH . '/templates/breadcrumbs.php'; ?>
  <h1 class="page-title" data-reveal><?= e($page['title'] ?? 'Contact Us') ?></h1>
  <?php if (!empty($page['subtitle'])): ?><p class="page-intro"><?= e($page['subtitle']) ?></p><?php endif; ?>
</div></section>
<section class="page-section pt-0"><div class="container-eb">
  <div class="row g-5">
    <div class="col-lg-5">
      <div class="rich"><?= sanitize_html($page['content'] ?? '') ?></div>
      <ul class="contact-list">
        <?php if ($x = setting('contact_email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e($x) ?>"><?= e($x) ?></a></li><?php endif; ?>
        <?php if ($x = setting('contact_phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $x)) ?>"><?= e($x) ?></a></li><?php endif; ?>
        <?php if ($x = setting('whatsapp_number')): ?><li><i class="bi bi-whatsapp"></i><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $x)) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></li><?php endif; ?>
        <?php if ($x = setting('business_hours')): ?><li><i class="bi bi-clock"></i><?= e($x) ?></li><?php endif; ?>
        <?php if ($x = setting('address')): ?><li><i class="bi bi-geo-alt"></i><?= nl2br(e($x)) ?></li><?php endif; ?>
      </ul>
    </div>
    <div class="col-lg-7">
      <?php if ($sent): ?>
        <div class="card-eb text-center"><i class="bi bi-envelope-heart fs-1 text-accent"></i><h2 class="h4 mt-2">Thank you</h2><p>We have received your message and will reply as soon as we can.</p></div>
      <?php else: ?>
      <form method="post" class="card-eb row g-3" novalidate>
        <?= csrf_field() ?>
        <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="col-md-6"><label class="form-label" for="cn">Name</label><input class="form-control" id="cn" name="name" required value="<?= e($v['name']) ?>"><?= $err('name') ?></div>
        <div class="col-md-6"><label class="form-label" for="ce">Email</label><input class="form-control" id="ce" type="email" name="email" required value="<?= e($v['email']) ?>"><?= $err('email') ?></div>
        <div class="col-md-6"><label class="form-label" for="cp">Phone (optional)</label><input class="form-control" id="cp" name="phone" value="<?= e($v['phone']) ?>"><?= $err('phone') ?></div>
        <div class="col-md-6"><label class="form-label" for="cs">Subject</label><select class="form-select" id="cs" name="subject"><?php foreach (['General enquiry', 'Order question', 'Sizing & customisation', 'Returns & exchanges', 'Wholesale & collaborations'] as $s): ?><option<?= $v['subject'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label" for="cm">Message</label><textarea class="form-control" id="cm" name="message" rows="6" required maxlength="5000"><?= e($v['message']) ?></textarea><?= $err('message') ?></div>
        <div class="col-12"><button class="btn btn-eb btn-primary-eb">Send message</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div></section>
<?php require ROOT_PATH . '/templates/footer.php';
