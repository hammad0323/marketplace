<?php
/** /support — help and contact (?topic=correction|listing|demo&about=...) */
$topic = str_param('topic', 20);
$about = str_param('about', 150);
$prefill = $about !== '' ? 'About: ' . $about . "\n\n" : '';
page_start(array(
    'title' => 'Support & Contact | The Hashir',
    'description' => 'Get help with your account, report incorrect prayer times or listing details, or ask to add your mosque to The Hashir.',
    'canonical' => '/support',
    'noindex' => $topic !== '' || $about !== '',
));
page_hero('Support', 'We usually reply within 1–2 business days.', array(array('Home', '/'), array('Support', null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="h4">Common requests</h2>
        <ul class="facts mb-4">
          <li><i class="bi bi-clock-history" aria-hidden="true"></i><div><strong>Wrong prayer or Jumu'ah time?</strong><br><span class="text-muted">Tell us the mosque and the correct time. We confirm with the mosque and update it.</span></div></li>
          <li><i class="bi bi-building-add" aria-hidden="true"></i><div><strong>Add or claim a mosque</strong><br><span class="text-muted">Mosque committees can manage their own profile, timetable, programs and announcements.</span></div></li>
          <li><i class="bi bi-journal-text" aria-hidden="true"></i><div><strong>Program or service listings</strong><br><span class="text-muted">Send updates for classes, Nikah, funeral or counselling services.</span></div></li>
          <li><i class="bi bi-person-lock" aria-hidden="true"></i><div><strong>Account or login help</strong><br><span class="text-muted">Use “Forgot password” on the <a href="<?= e(url(LOGIN_URL)) ?>">login page</a>, or contact us.</span></div></li>
          <li><i class="bi bi-bug" aria-hidden="true"></i><div><strong>Something not working?</strong><br><span class="text-muted">Tell us the page and what happened.</span></div></li>
        </ul>
        <h2 class="h5">Reach us directly</h2>
        <ul class="contact-list">
          <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></li>
          <li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:<?= e(CONTACT_PHONE_E164) ?>"><?= e(CONTACT_PHONE_DISPLAY) ?></a></li>
        </ul>
      </div>
      <div class="col-lg-7" id="contact">
        <div class="footer-form-card">
          <span class="accent-bar" aria-hidden="true"></span>
          <h2 class="footer-form-title">Send us a message</h2>
          <p class="text-muted small mb-4">Choose a topic so it reaches the right person.</p>
          <?php contact_form('support', $topic, $prefill); ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_end(); ?>
