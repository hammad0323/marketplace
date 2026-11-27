<?php
/** /about */
$stats = array(
    'mosques' => (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1'),
    'orgs' => (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id <> 1'),
    'listings' => (int)db_val('SELECT COUNT(*) FROM th_details d WHERE ' . DETAIL_ACTIVE),
    'cities' => (int)db_val('SELECT COUNT(DISTINCT city_id) FROM th_venue WHERE verify_status = 1'),
);
page_start(array(
    'title' => 'About The Hashir – A Community-First Muslim Discovery Platform',
    'description' => 'The Hashir connects Muslims with the mosques, organizations and programs that serve them — ' . number_format($stats['mosques']) . ' mosques across Canada and the United States.',
    'canonical' => '/about',
    'active' => 'about',
));
page_hero('About The Hashir', 'A community-first discovery platform that strengthens the connection between Muslims and the institutions that serve them.', array(array('Home', '/'), array('About', null)));
?>
<section class="section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <span class="eyebrow">Our mission</span>
        <h2 class="mt-2">Less fragmentation. More clarity, access and connection.</h2>
        <p>Information about mosques, classes and community services is often spread across flyers, WhatsApp groups and outdated websites. The Hashir brings it together in one place, so anyone can find a place to pray, learn, seek help or get involved — wherever they are.</p>
        <p>Hashir is built for Muslims, by Muslims, with a focus on authenticity, clarity and long-term community value.</p>
      </div>
      <div class="col-lg-6">
        <div class="stat-band">
          <div class="stat-big"><span class="num"><?= number_format($stats['mosques']) ?></span>mosques listed</div>
          <div class="stat-big"><span class="num"><?= number_format($stats['orgs']) ?></span>organizations</div>
          <div class="stat-big"><span class="num"><?= number_format($stats['listings']) ?></span>programs &amp; services</div>
          <div class="stat-big"><span class="num"><?= number_format($stats['cities']) ?></span>cities</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section bg-mint">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <span class="icon"><i class="bi bi-people" aria-hidden="true"></i></span>
          <h3>For the community</h3>
          <p class="mb-0">Find nearby mosques, see their prayer and Jumu'ah times, and discover programs, services and events in your city.</p>
          <a class="d-inline-block mt-3" href="<?= e(url('/mosques')) ?>">Find a mosque</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <span class="icon"><i class="bi bi-building" aria-hidden="true"></i></span>
          <h3>For mosques &amp; organizations</h3>
          <p class="mb-0">Present everything you offer in one place — timings, programs, services and announcements — instead of relying on scattered channels.</p>
          <a class="d-inline-block mt-3" href="<?= e(url('/solution-mosque')) ?>">Solutions for mosques</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <span class="icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
          <h3>The Hashir app</h3>
          <ul>
            <li>Prayer times and Azan notifications</li>
            <li>Qibla direction</li>
            <li>Sehar and Iftar timings</li>
            <li>Follow mosques and get their updates</li>
          </ul>
          <?php if (APP_STORE_URL || PLAY_STORE_URL): ?>
          <div class="d-flex gap-2 mt-3">
            <?php if (APP_STORE_URL): ?><a class="btn btn-primary btn-sm" href="<?= e(APP_STORE_URL) ?>" target="_blank" rel="noopener"><i class="bi bi-apple me-1" aria-hidden="true"></i>App Store</a><?php endif; ?>
            <?php if (PLAY_STORE_URL): ?><a class="btn btn-primary btn-sm" href="<?= e(PLAY_STORE_URL) ?>" target="_blank" rel="noopener"><i class="bi bi-google-play me-1" aria-hidden="true"></i>Google Play</a><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <h2 class="text-center mb-4">Our vision</h2>
    <div class="row g-4">
      <div class="col-md-4"><div class="feature-card"><span class="icon"><i class="bi bi-house-heart" aria-hidden="true"></i></span><h3>Universal belonging</h3><p class="mb-0">No Muslim should feel disconnected from their local community, wherever they are.</p></div></div>
      <div class="col-md-4"><div class="feature-card"><span class="icon"><i class="bi bi-eye" aria-hidden="true"></i></span><h3>Institutional visibility</h3><p class="mb-0">Mosques and Islamic institutions that are visible, accessible and easy to support through modern tools.</p></div></div>
      <div class="col-md-4"><div class="feature-card"><span class="icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span><h3>Sustainable engagement</h3><p class="mb-0">Community engagement that is informed, intentional and built to last for generations.</p></div></div>
    </div>
    <div class="claim-band mt-5">
      <p><strong>Questions, corrections or partnership ideas?</strong> We'd love to hear from you.</p>
      <a class="btn btn-primary" href="<?= e(url('/support#contact')) ?>">Contact us</a>
    </div>
  </div>
</section>
<?php page_end(); ?>
