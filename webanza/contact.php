<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$package = null;
if (!empty($_REQUEST['package'])) {
    $package = row('SELECT p.*, c.name AS category_name FROM packages p LEFT JOIN package_categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_active = 1', [(int) $_REQUEST['package']]);
}
$services = active('services', 'ORDER BY sort_order, id');
$errors = [];
$sent = false;
$old = [
    'name' => '', 'email' => '', 'phone' => '', 'company' => '', 'budget' => '', 'message' => '',
    'service' => $package['category_name'] ?? ($_GET['service'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $v) {
        $old[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if (!empty($_POST['website'])) {            // honeypot
        $errors[] = 'Spam detected.';
    }
    if ((time() - (int) ($_SESSION['last_inquiry'] ?? 0)) < 30) {
        $errors[] = 'Please wait a few seconds before sending another message.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 190) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['message'] === '' && !$package) {
        $errors[] = 'Please tell us a little about your project.';
    }
    if (mb_strlen($old['message']) > 5000) {
        $errors[] = 'Your message is too long.';
    }

    if (!$errors) {
        q('INSERT INTO inquiries (name, email, phone, company, service, package_id, package_name, budget, message, ip) VALUES (?,?,?,?,?,?,?,?,?,?)', [
            $old['name'], $old['email'], mb_substr($old['phone'], 0, 60), mb_substr($old['company'], 0, 190),
            mb_substr($old['service'], 0, 190), $package['id'] ?? null,
            $package ? $package['name'] . ' (' . ($package['category_name'] ?? '') . ') — ' . ((float) $package['price'] > 0 ? money($package['price']) : 'Custom') : null,
            mb_substr($old['budget'], 0, 80), $old['message'], $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $_SESSION['last_inquiry'] = time();

        $to = setting('notify_email') ?: setting('email');
        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $body = "New inquiry from your website\n\n"
                . "Name: {$old['name']}\nEmail: {$old['email']}\nPhone: {$old['phone']}\nCompany: {$old['company']}\n"
                . "Service: {$old['service']}\nPackage: " . ($package['name'] ?? '-') . "\nBudget: {$old['budget']}\n\n{$old['message']}\n";
            $host = preg_replace('~[^a-z0-9.\-]~i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
            @mail($to, 'New inquiry: ' . str_replace(["\r", "\n"], ' ', $old['name']), $body,
                "From: no-reply@{$host}\r\nReply-To: " . str_replace(["\r", "\n"], '', $old['email']) . "\r\nContent-Type: text/plain; charset=UTF-8");
        }
        $sent = true;
        $old = array_map(fn() => '', $old);
    }
}

$page_title = $package ? 'Order: ' . $package['name'] : 'Contact Us';
$page_desc  = 'Get a free consultation and quote from ' . setting('site_name') . '.';
require __DIR__ . '/partials/header.php';

page_hero($package ? 'Let\'s get *started*' : 'Let\'s *talk*', $package ? 'You are one step away from your ' . $package['name'] . ' package.' : setting('cta_text'), ['Contact' => ''], 'Contact');
$budgets = ['Under $500', '$500 – $1,000', '$1,000 – $3,000', '$3,000 – $10,000', '$10,000+', 'Not sure yet'];
?>
<section class="section">
  <div class="container contact-grid">
    <div class="contact-info" data-reveal="left">
      <h3>Get in touch</h3>
      <p>Tell us about your project. We reply within 24 hours with ideas, a timeline and a clear quote.</p>
      <?php if (setting('email')): ?><div class="ci-item"><i class="fa-solid fa-envelope"></i><div><small>Email us</small><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></div><?php endif; ?>
      <?php if (setting('phone')): ?><div class="ci-item"><i class="fa-solid fa-phone"></i><div><small>Call us</small><a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></div></div><?php endif; ?>
      <?php if ($wa = whatsapp_link()): ?><div class="ci-item"><i class="fa-brands fa-whatsapp"></i><div><small>WhatsApp</small><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(setting('whatsapp')) ?></a></div></div><?php endif; ?>
      <?php if (setting('address')): ?><div class="ci-item"><i class="fa-solid fa-location-dot"></i><div><small>Office</small><span><?= e(setting('address')) ?></span></div></div><?php endif; ?>
      <?php if (setting('working_hours')): ?><div class="ci-item"><i class="fa-regular fa-clock"></i><div><small>Working hours</small><span><?= e(setting('working_hours')) ?></span></div></div><?php endif; ?>
      <div class="socials" style="margin-top:28px">
        <?php foreach (social_links() as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['name']) ?>"><i class="<?= e($s['icon']) ?>"></i></a><?php endforeach; ?>
      </div>
    </div>

    <div class="form-card" data-reveal="right" id="form">
      <?php if ($sent): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Thank you! Your message has been received — we'll get back to you within 24 hours.</div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($err) ?></div>
      <?php endforeach; ?>

      <?php if ($package): ?>
        <div class="selected-pkg">
          <div><small>Selected package · <?= e($package['category_name']) ?></small><strong><?= e($package['name']) ?></strong></div>
          <div class="price"><?= (float) $package['price'] > 0 ? e(money($package['price'])) : 'Custom' ?> <small style="font-size:13px;color:var(--muted)"><?= e($package['price_suffix']) ?></small></div>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('contact.php' . ($package ? '?package=' . $package['id'] : ''))) ?>#form" novalidate>
        <?= csrf_field() ?>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="form-row">
          <div class="field"><label for="name">Full name *</label><input id="name" name="name" required value="<?= e($old['name']) ?>" placeholder="John Doe"></div>
          <div class="field"><label for="email">Email *</label><input id="email" type="email" name="email" required value="<?= e($old['email']) ?>" placeholder="you@company.com"></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="phone">Phone / WhatsApp</label><input id="phone" name="phone" value="<?= e($old['phone']) ?>" placeholder="+92 300 1234567"></div>
          <div class="field"><label for="company">Company</label><input id="company" name="company" value="<?= e($old['company']) ?>" placeholder="Your business name"></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="service">Service</label>
            <select id="service" name="service">
              <option value="">Select a service</option>
              <?php $matched = false; foreach ($services as $s): $sel = strcasecmp($s['title'], $old['service']) === 0; $matched = $matched || $sel; ?>
                <option <?= $sel ? 'selected' : '' ?>><?= e($s['title']) ?></option>
              <?php endforeach; ?>
              <?php if (!$matched && $old['service'] !== ''): ?><option selected><?= e($old['service']) ?></option><?php endif; ?>
              <option>Other</option>
            </select>
          </div>
          <div class="field"><label for="budget">Budget</label>
            <select id="budget" name="budget">
              <option value="">Select budget</option>
              <?php foreach ($budgets as $b): ?><option <?= $old['budget'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field"><label for="message">Project details<?= $package ? '' : ' *' ?></label><textarea id="message" name="message" placeholder="Tell us about your goals, features you need and your timeline…"><?= e($old['message']) ?></textarea></div>
        <button type="submit" class="btn btn-block"><?= $package ? 'Place Order Request' : 'Send Message' ?> <i class="fa-solid fa-paper-plane"></i></button>
        <p class="muted" style="font-size:13px;margin:14px 0 0;text-align:center"><i class="fa-solid fa-lock"></i> Your information is safe with us. No spam, ever.</p>
      </form>
    </div>
  </div>
  <?php if (setting('map_embed')): ?>
    <div class="container"><div class="map-embed" data-reveal="up"><iframe src="<?= e(setting('map_embed')) ?>" loading="lazy" title="Office location" referrerpolicy="no-referrer-when-downgrade"></iframe></div></div>
  <?php endif; ?>
</section>
<?php
section_faq();
require __DIR__ . '/partials/footer.php';
