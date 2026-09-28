<?php
require __DIR__ . '/config.php';

$errors = [];
$sent   = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post('name'); $phone = post('phone'); $email = post('email');
    $subject = post('subject'); $message = post('message');

    if (!csrf_ok())                          $errors[] = 'Your session expired — please submit the form again.';
    if (post('website') !== '')              $errors[] = 'Spam check failed.';
    if (mb_strlen($name) < 2)                $errors[] = 'Please enter your name.';
    if ($phone === '' && $email === '')      $errors[] = 'Please give us a phone number or email so we can reply.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (mb_strlen($message) < 5)             $errors[] = 'Please write a short message.';

    if (!$errors) {
        q('INSERT INTO messages (name, phone, email, subject, message) VALUES (?,?,?,?,?)', [$name, $phone, $email, $subject, $message]);
        $sent = true;
    }
}

$pageTitle  = 'Contact Us';
$activeNav  = 'contact';
$heroTitle  = 'Contact Us';
$heroScript = 'We would love to hear from you';
$heroText   = 'Questions about a service, a bridal package or a course? Send us a message or drop by the studio.';
require __DIR__ . '/inc/header.php';
require __DIR__ . '/inc/page-hero.php';
?>
<section class="section contact-sec">
    <div class="container contact-grid">
        <div class="contact-cards reveal-left">
            <div class="info-card"><i class="fa-solid fa-location-dot"></i><div><strong>Address</strong><span><?= e(setting('address')) ?></span></div></div>
            <div class="info-card"><i class="fa-solid fa-phone"></i><div><strong>Call / WhatsApp</strong><a href="tel:<?= e(preg_replace('/\s/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></div></div>
            <?php if (setting('email')): ?><div class="info-card"><i class="fa-regular fa-envelope"></i><div><strong>Email</strong><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></div><?php endif; ?>
            <div class="info-card"><i class="fa-regular fa-clock"></i><div><strong>Hours</strong><span><?= e(setting('hours')) ?></span></div></div>
            <div class="map-wrap" style="min-height:300px"><iframe title="Map" loading="lazy" src="https://maps.google.com/maps?q=<?= rawurlencode(setting('map_query')) ?>&z=16&output=embed" style="min-height:300px"></iframe></div>
        </div>
        <div class="card reveal-right">
            <?php if ($sent): ?>
                <div class="success-box">
                    <div class="big-icon"><i class="fa-solid fa-envelope-circle-check"></i></div>
                    <h2>Message sent!</h2>
                    <p>Thank you, <?= e($name) ?>. We'll get back to you as soon as possible.</p>
                    <a href="index.php" class="btn btn-primary">Back to Home</a>
                </div>
            <?php else: ?>
                <h2 style="font-size:36px">Send a Message</h2>
                <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                    <label>Your name *<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
                    <div class="form-row">
                        <label>Phone<input name="phone" type="tel" value="<?= e($_POST['phone'] ?? '') ?>"></label>
                        <label>Email<input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
                    </div>
                    <label>Subject<input name="subject" value="<?= e($_POST['subject'] ?? '') ?>"></label>
                    <label>Message *<textarea name="message" required rows="6"><?= e($_POST['message'] ?? '') ?></textarea></label>
                    <button class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
