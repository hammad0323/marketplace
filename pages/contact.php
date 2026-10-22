<?php
/** Contact page with message form (stored in contact_messages; emailed to the store if email is set up). */
$pageRow = $pageRow ?? page_by_slug('contact');
seo_set([
    'title' => $pageRow['seo_title'] ?? 'Contact Us',
    'description' => $pageRow['meta_description'] ?? 'Get in touch with Urban Collections.',
    'breadcrumbs' => [['Home', url()], ['Contact', url('contact')]],
]);
$sent = false;
$errors = [];
if (is_post()) {
    csrf_check();
    $d = [
        'name' => mb_substr(input('name', '', 'post'), 0, 120), 'email' => mb_substr(input('email', '', 'post'), 0, 190),
        'phone' => mb_substr(input('phone', '', 'post'), 0, 30), 'subject' => mb_substr(input('subject', '', 'post'), 0, 190),
        'message' => mb_substr(input('message', '', 'post'), 0, 5000),
    ];
    if (input('website', '', 'post') !== '') {
        $errors[] = 'Message rejected.'; // honeypot
    }
    if (mb_strlen($d['name']) < 2) $errors[] = 'Please enter your name.';
    if (!valid_email($d['email'])) $errors[] = 'Please enter a valid email address.';
    if (mb_strlen($d['message']) < 10) $errors[] = 'Please write a message (at least 10 characters).';
    if (!$errors && !rate_limit('contact:' . client_ip(), 5, 3600)) $errors[] = 'You have sent several messages recently. Please try again later.';
    if (!$errors) {
        db_insert('contact_messages', $d + ['ip_address' => client_ip()]);
        if ($to = setting('store_email')) {
            send_mail($to, 'Contact form: ' . ($d['subject'] ?: $d['name']), mail_layout('New message', '<p><strong>' . e($d['name']) . '</strong> (' . e($d['email']) . ', ' . e($d['phone']) . ')</p><p>' . nl2br(e($d['message'])) . '</p>'));
        }
        $sent = true;
        clear_old();
    } else {
        remember_input($_POST);
    }
}
$bodyClass = 'page-contact';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-listing-hero">
    <div class="uc-container uc-listing-hero-inner">
        <?= breadcrumbs_html([['Home', url()], ['Contact', null]]) ?>
        <h1 class="uc-page-title"><?= e($pageRow['title'] ?? 'Contact Us') ?></h1>
        <?php if (!empty($pageRow['excerpt'])): ?><p class="uc-page-intro"><?= e($pageRow['excerpt']) ?></p><?php endif; ?>
    </div>
</section>
<section class="uc-container uc-page-pad">
    <div class="uc-contact-grid">
        <div>
            <?php if (!empty($pageRow['content'])): ?><div class="uc-rich mb-4"><?= sanitize_html($pageRow['content']) ?></div><?php endif; ?>
            <ul class="uc-contact-cards">
                <?php if (setting('store_phone')): ?><li><i class="bi bi-telephone"></i><div><strong>Call us</strong><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></div></li><?php endif; ?>
                <?php if (setting('store_whatsapp')): ?><li><i class="bi bi-whatsapp"></i><div><strong>WhatsApp</strong><a href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('store_whatsapp'))) ?>" target="_blank" rel="noopener"><?= e(setting('store_whatsapp')) ?></a></div></li><?php endif; ?>
                <?php if (setting('store_email')): ?><li><i class="bi bi-envelope"></i><div><strong>Email</strong><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></div></li><?php endif; ?>
                <?php if (setting('store_hours')): ?><li><i class="bi bi-clock"></i><div><strong>Hours</strong><span><?= e(setting('store_hours')) ?></span></div></li><?php endif; ?>
                <?php if (setting('store_address')): ?><li><i class="bi bi-geo-alt"></i><div><strong>Address</strong><span><?= nl2br(e(setting('store_address'))) ?></span></div></li><?php endif; ?>
                <?php if (setting('social_instagram')): ?><li><i class="bi bi-instagram"></i><div><strong>Instagram</strong><a href="<?= e(setting('social_instagram')) ?>" target="_blank" rel="noopener">Follow us</a></div></li><?php endif; ?>
            </ul>
        </div>
        <div class="uc-card-box">
            <?php if ($sent): ?>
                <div class="uc-confirm"><span class="uc-confirm-icon"><i class="bi bi-check2"></i></span><h2 class="h4">Message sent</h2><p>Thank you — we will get back to you as soon as possible.</p></div>
            <?php else: ?>
                <?php if ($errors): ?><div class="uc-alert uc-alert--error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="d-none" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="uc-field"><span>Name *</span><input type="text" name="name" required value="<?= e(old('name', current_customer()['first_name'] ?? '')) ?>"></label></div>
                        <div class="col-md-6"><label class="uc-field"><span>Email *</span><input type="email" name="email" required value="<?= e(old('email', current_customer()['email'] ?? '')) ?>"></label></div>
                        <div class="col-md-6"><label class="uc-field"><span>Phone</span><input type="tel" name="phone" value="<?= e(old('phone')) ?>"></label></div>
                        <div class="col-md-6"><label class="uc-field"><span>Subject</span><input type="text" name="subject" value="<?= e(old('subject')) ?>" placeholder="Order number, sizing…"></label></div>
                        <div class="col-12"><label class="uc-field"><span>Message *</span><textarea name="message" rows="6" required><?= e(old('message')) ?></textarea></label></div>
                    </div>
                    <button class="uc-btn" type="submit">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php clear_old(); require UC_ROOT . '/includes/footer.php';
