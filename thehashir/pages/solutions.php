<?php
/** /solution-mosque — Hashir for mosques */
$mosqueCount = (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1');
$withTimes = (int)db_val('SELECT COUNT(DISTINCT venue_id) FROM th_jummah_prayers WHERE to_date >= CURDATE()');
$showcase = db_all('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . " WHERE v.verify_status = 1 AND v.role_id = 1 AND v.venue_id IN (SELECT DISTINCT venue_id FROM prayer_timings)
    ORDER BY v.venue_title LIMIT 8");
$features = array(
    array('bi-code-square', 'Website prayer-time widget', 'Embed your live prayer times on your own website. It stays in sync with your Hashir timetable and fits your design on any screen.'),
    array('bi-people', 'Multi-admin access', 'Give committee members their own logins with role-based permissions and an activity log of every change.'),
    array('bi-bell', 'Push notifications', 'Send prayer reminders, Jumu\'ah reminders, event notices and urgent announcements to everyone who follows your masjid.'),
    array('bi-calendar-week', 'Prayer time management', 'Calendar view, bulk adjustments, automatic or custom calculations and special prayers such as Eid.'),
    array('bi-mic', 'Jumu\'ah scheduler', 'Plan multiple Jumu\'ah sessions, khateeb profiles and khutbah topics.'),
    array('bi-moon-stars', 'Ramadan management', 'Taraweeh plans, Suhoor and Iftar times and special-night programs.'),
    array('bi-tv', 'Masjid TV display', 'Digital signage for your prayer hall with live times and announcements.'),
);
page_start(array(
    'title' => 'Hashir for Mosques – Prayer Times, Announcements & Digital Tools for Your Masjid',
    'description' => 'Manage prayer and Jumu\'ah times, announcements, programs and your masjid TV display from one place — and reach your community on The Hashir.',
    'canonical' => '/solution-mosque',
    'active' => 'solutions',
));
?>
<section class="home-hero bg-mint">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-7">
        <span class="eyebrow">Solutions for mosques</span>
        <h1 class="mt-2">Connect your masjid with your local Muslim community</h1>
        <p class="lead">Keep prayer times, Jumu'ah, programs and announcements up to date in one place — on The Hashir, in the Hashir app, on your website and on your masjid screens.</p>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary btn-lg" href="#get-started" data-contact-topic="demo">Book a free demo</a>
          <a class="btn btn-outline-primary btn-lg" href="<?= e(url('/mosques')) ?>">Find your mosque's profile</a>
        </div>
        <p class="small text-muted mt-3 mb-0">Already have an admin account? <a href="<?= e(url(LOGIN_URL)) ?>">Log in</a></p>
      </div>
      <div class="col-lg-5">
        <div class="stat-band">
          <div class="stat-big"><span class="num"><?= number_format($mosqueCount) ?></span>mosques listed</div>
          <div class="stat-big"><span class="num"><?= number_format($withTimes) ?></span>publish Jumu'ah times</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <div class="text-center mb-4">
      <h2>Everything your masjid needs online</h2>
      <p class="text-muted">One dashboard for your digital presence.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($features as $f): ?>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <span class="icon"><i class="bi <?= e($f[0]) ?>" aria-hidden="true"></i></span>
          <h3><?= e($f[1]) ?></h3>
          <p class="mb-0 text-muted"><?= e($f[2]) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($showcase): ?>
<section class="section pt-0">
  <div class="container-xl">
    <h2 class="mb-1">Mosques publishing their timetable on Hashir</h2>
    <p class="text-muted mb-4">See how a complete profile looks.</p>
    <div class="card-grid"><?php $sb = venue_badges($showcase); foreach ($showcase as $s) { venue_card($s, $sb); } ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section pt-0">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2>How to get started</h2>
        <ol class="mt-3">
          <li class="mb-2"><strong>Find your mosque</strong> — <?= number_format($mosqueCount) ?> mosques are already listed. <a href="<?= e(url('/mosques')) ?>">Search the directory</a>.</li>
          <li class="mb-2"><strong>Tell us who you are</strong> — send the form with your role at the masjid.</li>
          <li class="mb-2"><strong>Get your admin login</strong> — we verify you with the masjid and set up your account.</li>
          <li><strong>Publish</strong> — add prayer times, Jumu'ah, programs and announcements.</li>
        </ol>
        <div class="accordion faq mt-4" id="solFaq">
          <div class="accordion-item">
            <h3 class="accordion-header m-0"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sf1" aria-expanded="false" aria-controls="sf1">Is my mosque already on The Hashir?</button></h3>
            <div id="sf1" class="accordion-collapse collapse" data-bs-parent="#solFaq"><div class="accordion-body">Most likely — search the <a href="<?= e(url('/mosques')) ?>">mosque directory</a>. If it's missing, use the form and we'll add it.</div></div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header m-0"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sf2" aria-expanded="false" aria-controls="sf2">Who can update our listing?</button></h3>
            <div id="sf2" class="accordion-collapse collapse" data-bs-parent="#solFaq"><div class="accordion-body">Verified admins from your masjid. You can add several committee members, each with their own login.</div></div>
          </div>
          <div class="accordion-item">
            <h3 class="accordion-header m-0"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sf3" aria-expanded="false" aria-controls="sf3">Can we upload a whole month of prayer times?</button></h3>
            <div id="sf3" class="accordion-collapse collapse" data-bs-parent="#solFaq"><div class="accordion-body">Yes. Send us your timetable and we'll help you load it, then you can adjust individual days from the dashboard.</div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-7" id="get-started">
        <div class="footer-form-card">
          <span class="accent-bar" aria-hidden="true"></span>
          <h2 class="footer-form-title">Book a demo or claim your mosque</h2>
          <p class="text-muted small mb-4">Include your masjid's name, city and your role.</p>
          <?php contact_form('solutions', 'demo'); ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php page_end(); ?>
