<?php
/**
 * Mosque profile  /mosques-details/{slug}/{id}
 * Organization profile  /organization-details/{slug}/{id}
 */
$v = get_venue($ROUTE['id']);
if (!$v) {
    route_page('404');
}
$isMosque = ((int)$v['role_id'] === 1);
$canonical = venue_url($v);
$slugPart = explode('/', $canonical);
if (($isMosque && $ROUTE['kind'] !== 'mosque') || (!$isMosque && $ROUTE['kind'] !== 'org') || slug_key($ROUTE['slug']) !== slug_key($slugPart[2])) {
    redirect($canonical);
}

$id = (int)$v['venue_id'];
$name = trim($v['venue_title']);
$city = trim((string)$v['city_title']);
$tz = venue_timezone($v);
$today = today_in($tz);

// Prayer data (each venue has its own schedule)
$prayerDay = prayer_day($id, $today);
$prayers = prayer_rows($prayerDay);
$jInfo = jummah_for_profile($id, $today, $tz);
$jummah = $jInfo['sessions'];
$jummahStale = $jInfo['stale'];
$hasPrayerCard = $isMosque || $prayers || $jummah;

// Related records
$allDetails = details_for_venue($id);
$byType = array(2 => array(), 3 => array(), 1 => array());
foreach ($allDetails as $d) {
    $byType[(int)$d['grand_category_id']][] = $d;
}
$announcements = venue_announcements($id, $tz);
$people = venue_people($id);
$affiliations = $isMosque ? venue_affiliations($id) : array();
$members = $isMosque ? array() : organization_members($id);
$mapSrc = map_embed_src($v['address_iframe']);
if ($mapSrc === '' && trim((string)$v['street_address']) !== '') {
    // Keyless Google Maps embed built from the saved address
    $mapSrc = 'https://www.google.com/maps?q=' . rawurlencode(implode(', ', array_filter(array(trim($v['venue_title']), trim($v['street_address']), $v['city_title'], $v['state_title'], $v['postal_code'])))) . '&output=embed';
}
$nearby = $v['city_id'] ? city_venues((int)$v['city_id'], $id, 6, $isMosque ? 1 : (int)$v['role_id']) : array();
$nearbyBadges = venue_badges($nearby);

$logo = venue_logo($v);
$cover = venue_cover($v);
$social = venue_social($v);
$whatsappGroup = '';
foreach ($social as $i => $s) {
    if ($s['key'] === 'whatsapp_link') {
        $whatsappGroup = $s['url'];
    }
}
$street = trim((string)$v['street_address']);
$line2 = trim(implode(', ', array_filter(array($city, trim(trim((string)$v['state_title']) . ' ' . trim((string)$v['postal_code'])), trim((string)$v['country_title'])))));
$directions = maps_link(array($name, $street, $city, $v['state_title'], $v['postal_code'], $v['country_title']));
$phoneHref = tel_href($v['phone_number']);
$email = filter_var(trim((string)$v['email_address']), FILTER_VALIDATE_EMAIL) ? trim($v['email_address']) : '';
$website = safe_url($v['website_link']);
$about = safe_html($v['about_details']);
$typeLabel = $isMosque ? 'Mosque' : rtrim((string)$v['role_title'], 's');
$typeLabel = $typeLabel === 'Key bodies & Association' ? 'Key body / association' : $typeLabel;

// Tabs: only sections that have content
$tabs = array();
if ($hasPrayerCard) {
    $tabs['prayer'] = 'Prayer times';
}
if ($announcements) {
    $tabs['announcements'] = 'Announcements';
}
if ($byType[2]) {
    $tabs['programs'] = 'Programs';
}
if ($byType[3]) {
    $tabs['services'] = 'Services';
}
if ($byType[1]) {
    $tabs['events'] = 'Events';
}
if ($people) {
    $tabs['people'] = 'People';
}
if ($members) {
    $tabs['members'] = 'Member mosques';
}
if ($affiliations) {
    $tabs['affiliations'] = 'Affiliations';
}
if ($mapSrc || $street !== '') {
    $tabs['location'] = 'Location';
}

// SEO
$titleBits = $name . ($city !== '' ? ', ' . $city : '');
$metaTitle = $isMosque ? $titleBits . ' – Prayer Times, Jumu\'ah & Programs | The Hashir' : $titleBits . ' – ' . $typeLabel . ' | The Hashir';
$metaDesc = trim((string)$v['meta_description']) !== '' ? excerpt($v['meta_description'], 158) : excerpt(
    $about !== '' ? $about : ($name . ' in ' . $line2 . '. Prayer times, Jumu\'ah, programs, services and contact details on The Hashir.'), 158);
$ld = array(
    '@context' => 'https://schema.org',
    '@type' => $isMosque ? 'Mosque' : 'Organization',
    'name' => $name,
    'url' => abs_url($canonical),
    'address' => array('@type' => 'PostalAddress', 'streetAddress' => $street, 'addressLocality' => $city, 'addressRegion' => (string)$v['state_title'], 'postalCode' => (string)$v['postal_code'], 'addressCountry' => (string)$v['country_title']),
);
if (!empty($v['venue_aka'])) {
    $ld['alternateName'] = clean_text($v['venue_aka']);
}
if ($v['phone_number']) {
    $ld['telephone'] = trim($v['phone_number']);
}
if ($email) {
    $ld['email'] = $email;
}
if ($cover || $logo) {
    $ld['image'] = $cover ? $cover : $logo;
}
$same = array();
if ($website) {
    $same[] = $website;
}
foreach ($social as $s) {
    $same[] = $s['url'];
}
if ($same) {
    $ld['sameAs'] = $same;
}

page_start(array(
    'title' => $metaTitle,
    'description' => $metaDesc,
    'canonical' => $canonical,
    'image' => $cover ? $cover : $logo,
    'type' => 'place',
    'jsonld' => array($ld),
    'active' => $isMosque ? 'mosques' : 'orgs',
    'body_class' => 'page-profile',
));

$crumbs = array(array('Home', '/'));
if ($isMosque) {
    $crumbs[] = array('Mosques', '/mosques');
    if ($city !== '') {
        $crumbs[] = array($city, '/mosques?city=' . (int)$v['city_id']);
    }
} else {
    $crumbs[] = array('Organizations', '/organizations-catalog');
    $role = get_role((int)$v['role_id']);
    if ($role) {
        $crumbs[] = array($role['role_title'], org_role_url($role));
    }
}
$crumbs[] = array($name, null);
?>
<?php
$followers = (int)db_val('SELECT COUNT(*) FROM mosque_followers WHERE venue_id = ? AND follow_status = 1', 's', array((string)$id));
$tabs = array('overview' => 'Overview') + $tabs;
unset($tabs['location']);
if ($mapSrc || $street !== '') {
    $tabs['location'] = 'Location';
}
$prayerName = array('Fajr' => 'bi-sunrise', 'Dhuhr' => 'bi-sun', 'Asr' => 'bi-brightness-high', 'Maghrib' => 'bi-sunset', 'Isha' => 'bi-moon-stars');
?>
<div class="vp" data-venue-id="<?= $id ?>">
  <section class="vp-hero<?= $cover === '' ? ' no-cover' : '' ?>">
    <?php if ($cover !== ''): ?><img class="vp-cover" src="<?= e($cover) ?>" alt="<?= e($name) ?>" fetchpriority="high" onerror="this.parentNode.classList.add('no-cover');this.remove()"><?php endif; ?>
    <div class="vp-hero-shade"></div>
    <div class="container-xl vp-hero-inner"><?php breadcrumb($crumbs); ?></div>
  </section>

  <div class="container-xl">
    <div class="vp-id-card">
      <div class="vp-logo"><?php img_or_initials($logo, $name . ' logo', $name); ?></div>
      <div class="vp-id-main">
        <div class="vp-badges">
          <span class="chip"><i class="bi <?= e(role_icon($v['role_id'])) ?>" aria-hidden="true"></i><?= e($typeLabel) ?></span>
          <span class="chip chip-verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Verified listing</span>
        </div>
        <h1 class="vp-name"><?= e($name) ?></h1>
        <?php if (!empty($v['venue_aka'])): ?><p class="vp-aka">Also known as <?= e(clean_text($v['venue_aka'])) ?></p><?php endif; ?>
        <p class="vp-place"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><?= e(trim(($street !== '' ? $street . ', ' : '') . $line2, ', ')) ?></p>
      </div>
      <div class="vp-actions">
        <button type="button" class="btn btn-primary" data-follow-btn data-venue="<?= $id ?>" aria-pressed="false"><i class="bi bi-bell me-1" aria-hidden="true"></i><span data-follow-label>Follow</span><span class="vp-count" data-follow-count><?= $followers ? number_format($followers) : '' ?></span></button>
        <button type="button" class="btn btn-outline-primary" data-share-btn data-title="<?= e($name) ?>"><i class="bi bi-share me-1" aria-hidden="true"></i>Share</button>
        <a class="btn btn-soft" href="<?= e($directions) ?>" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right me-1" aria-hidden="true"></i>Directions</a>
      </div>
    </div>

    <ul class="vp-quick">
      <?php if ($phoneHref): ?><li><a href="<?= e($phoneHref) ?>"><i class="bi bi-telephone" aria-hidden="true"></i><span><small>Call</small><?= e(trim($v['phone_number'])) ?></span></a></li><?php endif; ?>
      <?php if ($email): ?><li><a href="mailto:<?= e($email) ?>"><i class="bi bi-envelope" aria-hidden="true"></i><span><small>Email</small><?= e($email) ?></span></a></li><?php endif; ?>
      <?php if ($website): ?><li><a href="<?= e($website) ?>" target="_blank" rel="noopener"><i class="bi bi-globe2" aria-hidden="true"></i><span><small>Website</small><?= e(display_host($website)) ?></span></a></li><?php endif; ?>
      <?php if ($prayers): ?><li><a href="#prayer"><i class="bi bi-clock" aria-hidden="true"></i><span><small>Today</small><span data-next-short>Prayer times</span></span></a></li><?php endif; ?>
    </ul>

    <?php if (count($tabs) > 1): ?>
    <nav class="profile-tabs vp-tabs" aria-label="Sections on this page">
      <ul>
        <?php $first = true; foreach ($tabs as $tid => $label): ?>
        <li><a href="#<?= e($tid) ?>"<?= $first ? ' class="active" aria-current="true"' : '' ?>><?= e($label) ?></a></li>
        <?php $first = false; endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>

    <div class="row g-4 g-xl-5 vp-body">
      <div class="col-lg-8 order-2 order-lg-1">
        <section class="vp-section" id="overview" aria-labelledby="h-about">
          <h2 id="h-about"><?= $isMosque ? 'About the mosque' : 'About ' . e($typeLabel === 'Mosque' ? 'us' : 'this ' . strtolower($typeLabel)) ?></h2>
          <?php if ($about !== ''): ?>
            <div class="readmore is-collapsed rich" id="about-text" data-readmore><?= $about ?></div>
            <button type="button" class="readmore-btn" aria-controls="about-text" aria-expanded="false" hidden>Read more</button>
          <?php elseif (trim((string)$v['meta_description']) !== ''): ?>
            <p><?= e(clean_text($v['meta_description'])) ?></p>
          <?php else: ?>
            <p class="text-muted"><?= e($name) ?> is listed on The Hashir<?= $city !== '' ? ' in ' . e($city) : '' ?>. A description hasn't been added yet.</p>
          <?php endif; ?>
          <div class="vp-facts">
            <div><span>Type</span><strong><?= e($typeLabel) ?></strong></div>
            <?php if ($city !== ''): ?><div><span>Location</span><strong><?= e(venue_place_line($v)) ?></strong></div><?php endif; ?>
            <?php if ($byType[2] || $byType[3] || $byType[1]): ?><div><span>On The Hashir</span><strong><?= e(implode(' · ', array_filter(array($byType[2] ? plural(count($byType[2]), 'program') : '', $byType[3] ? plural(count($byType[3]), 'service') : '', $byType[1] ? plural(count($byType[1]), 'event') : '')))) ?></strong></div><?php endif; ?>
            <?php if (valid_date($v['update_date'])): ?><div><span>Last updated</span><strong><?= e(fmt_date($v['update_date'], 'j M Y')) ?></strong></div><?php endif; ?>
          </div>
        </section>

        <?php if ($announcements): ?>
        <section class="vp-section" id="announcements" aria-labelledby="h-announcements">
          <h2 id="h-announcements">Announcements</h2>
          <div class="row g-3">
            <?php foreach ($announcements as $a): ?>
            <div class="col-md-6">
              <article class="announce-card">
                <?php if (!empty($a['news_image'])): ?><img src="<?= e(media('images/announcement/' . $a['news_image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
                <p class="announce-date"><i class="bi bi-megaphone" aria-hidden="true"></i> <?= e(fmt_date($a['created_date'])) ?></p>
                <h3><?= e(clean_text($a['news_title'])) ?></h3>
                <div class="announce-body rich"><?= safe_html($a['news_description']) ?></div>
              </article>
            </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php
        $typeSections = array(2 => array('programs', 'Programs'), 3 => array('services', 'Services'), 1 => array('events', 'Events'));
        foreach ($typeSections as $gc => $info):
            $list = $byType[$gc];
            if (!$list) { continue; }
            $shown = array_slice($list, 0, 6);
        ?>
        <section class="vp-section" id="<?= e($info[0]) ?>" aria-labelledby="h-<?= e($info[0]) ?>">
          <div class="section-head">
            <h2 id="h-<?= e($info[0]) ?>"><?= e($info[1]) ?> <span class="vp-n"><?= count($list) ?></span></h2>
            <?php if (count($list) > count($shown)): ?>
              <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/details-list/' . legacy_slug($v['url'] ? $v['url'] : $name) . '/' . $info[1] . '/' . $id)) ?>">See all <?= count($list) ?></a>
            <?php endif; ?>
          </div>
          <div class="card-grid vp-grid"><?php foreach ($shown as $d) { detail_card($d, false); } ?></div>
        </section>
        <?php endforeach; ?>

        <?php if ($people): ?>
        <section class="vp-section" id="people" aria-labelledby="h-people">
          <h2 id="h-people">People</h2>
          <div class="row g-3">
            <?php foreach ($people as $p): ?>
            <div class="col-sm-6"><div class="person-card">
              <div class="avatar"><?php img_or_initials(!empty($p['image']) ? media('images/people/' . $p['image']) : '', $p['name'], $p['name']); ?></div>
              <div><strong><?= e($p['name']) ?></strong><?php if (!empty($p['designation'])): ?><div class="small text-muted"><?= e($p['designation']) ?></div><?php endif; ?></div>
            </div></div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($members): ?>
        <section class="vp-section" id="members" aria-labelledby="h-members">
          <h2 id="h-members">Member mosques <span class="vp-n"><?= count($members) ?></span></h2>
          <div class="card-grid vp-grid"><?php $mb = venue_badges($members); foreach ($members as $m) { venue_card($m, $mb); } ?></div>
        </section>
        <?php endif; ?>

        <?php if ($affiliations): ?>
        <section class="vp-section" id="affiliations" aria-labelledby="h-aff">
          <h2 id="h-aff">Affiliated organizations</h2>
          <div class="card-grid vp-grid"><?php foreach ($affiliations as $a) { venue_card($a); } ?></div>
        </section>
        <?php endif; ?>

        <?php if ($mapSrc || $street !== ''): ?>
        <section class="vp-section" id="location" aria-labelledby="h-location">
          <div class="section-head">
            <div><h2 id="h-location" class="mb-1">Location</h2><p class="mb-0 text-muted"><?= e(trim(($street !== '' ? $street . ', ' : '') . $line2, ', ')) ?></p></div>
            <a class="btn btn-soft btn-sm" href="<?= e($directions) ?>" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right me-1" aria-hidden="true"></i>Get directions</a>
          </div>
          <?php if ($mapSrc): ?><div class="map-frame"><iframe src="<?= e($mapSrc) ?>" title="Map showing <?= e($name) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div><?php endif; ?>
        </section>
        <?php endif; ?>
      </div>

      <aside class="col-lg-4 order-1 order-lg-2">
        <div class="vp-side">
          <?php if ($hasPrayerCard): ?>
          <section class="vp-prayer" id="prayer" aria-labelledby="h-prayer" data-prayer-card data-tz="<?= e($tz) ?>" data-today="1">
            <div class="vp-prayer-head">
              <div><h2 id="h-prayer">Prayer times</h2><p><?= e(fmt_date($today, 'l, j F Y')) ?><?= !empty($prayerDay['islamic_date']) ? '<br>' . e($prayerDay['islamic_date']) : '' ?></p></div>
              <i class="bi bi-moon-stars" aria-hidden="true"></i>
            </div>
            <?php if ($prayers): ?>
            <div class="vp-next" data-next-banner hidden><span class="lbl">Next</span><strong data-next-name></strong><span data-next-time></span><span class="cd" data-next-left></span></div>
            <table class="vp-ptable">
              <thead><tr><th scope="col">Prayer</th><th scope="col">Adhan</th><th scope="col">Iqamah</th></tr></thead>
              <tbody>
              <?php foreach ($prayers as $p): ?>
                <tr class="prayer-row" data-name="<?= e($p['name']) ?>" data-azan="<?= e($p['azan_min']) ?>" data-iqama="<?= e($p['iqama_min']) ?>" data-azan-text="<?= e($p['azan']) ?>" data-iqama-text="<?= e($p['iqama']) ?>">
                  <th scope="row"><i class="bi <?= e(isset($prayerName[$p['name']]) ? $prayerName[$p['name']] : 'bi-clock') ?>" aria-hidden="true"></i><?= e($p['name']) ?></th>
                  <td><?= e($p['azan'] !== '' ? $p['azan'] : '—') ?></td>
                  <td class="iq"><?= e($p['iqama'] !== '' ? $p['iqama'] : '—') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php $sunrise = fmt_time($prayerDay['sunrise']); $sunset = fmt_time($prayerDay['sunset']); if ($sunrise || $sunset): ?>
            <div class="vp-sun"><?php if ($sunrise): ?><span><i class="bi bi-sunrise" aria-hidden="true"></i> Sunrise <strong><?= e($sunrise) ?></strong></span><?php endif; ?><?php if ($sunset): ?><span><i class="bi bi-sunset" aria-hidden="true"></i> Sunset <strong><?= e($sunset) ?></strong></span><?php endif; ?></div>
            <?php endif; ?>
            <?php elseif (!$jummah): ?>
            <div class="prayer-empty">
              <i class="bi bi-clock-history" aria-hidden="true"></i>
              <h3 class="h6 mt-2">Prayer times not published yet</h3>
              <p class="mb-3"><?= e($name) ?> hasn't shared its timetable on The Hashir. Please check with the <?= $isMosque ? 'mosque' : 'organization' ?> directly.</p>
              <a class="btn btn-outline-primary btn-sm" href="<?= e(url(PORTAL_PATH . '/login')) ?>">Run this <?= $isMosque ? 'mosque' : 'organization' ?>? Add your timetable</a>
            </div>
            <?php endif; ?>

            <?php if ($jummah): ?>
            <div class="vp-jummah<?= $jummahStale ? ' is-stale' : '' ?>">
              <h3><i class="bi bi-people-fill" aria-hidden="true"></i> Jumu'ah</h3>
              <?php if ($jummahStale): ?><p class="jummah-stale"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Last published <?= e($jInfo['season']) ?> schedule (until <?= e(fmt_date($jInfo['to'], 'j M Y')) ?>). Please confirm with the mosque.</p><?php endif; ?>
              <ol class="vp-jlist">
                <?php foreach ($jummah as $i => $j): ?>
                <li>
                  <span class="n"><?= count($jummah) > 1 ? ordinal($i + 1) : '' ?></span>
                  <div class="j-body">
                    <div class="j-times">
                      <?php if ($j['khutbah'] !== ''): ?><span><small>Khutbah</small><?= e($j['khutbah']) ?></span><?php endif; ?>
                      <?php if ($j['iqama'] !== ''): ?><span><small>Salah</small><strong><?= e($j['iqama']) ?></strong></span><?php endif; ?>
                    </div>
                    <?php if ($j['imam'] !== '' || $j['place'] !== ''): ?><div class="j-meta"><?= e(trim(($j['imam'] !== '' ? 'Khateeb: ' . $j['imam'] : '') . ($j['imam'] !== '' && $j['place'] !== '' ? ' · ' : '') . $j['place'])) ?></div><?php endif; ?>
                  </div>
                </li>
                <?php endforeach; ?>
              </ol>
            </div>
            <?php endif; ?>
            <?php if ($prayers || $jummah): ?><p class="prayer-source">Times provided by <?= e($name) ?>. Please confirm locally before travelling.</p><?php endif; ?>
          </section>
          <?php endif; ?>

          <section class="vp-card" aria-labelledby="h-contact">
            <h2 id="h-contact">Contact</h2>
            <ul class="contact-list">
              <?php if ($phoneHref): ?><li><i class="bi bi-telephone" aria-hidden="true"></i><a href="<?= e($phoneHref) ?>"><?= e(trim($v['phone_number'])) ?></a></li><?php endif; ?>
              <?php if ($email): ?><li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
              <?php if ($website): ?><li><i class="bi bi-globe2" aria-hidden="true"></i><a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= e(display_host($website)) ?></a></li><?php endif; ?>
              <?php if ($street !== '' || $line2 !== ''): ?><li><i class="bi bi-geo-alt" aria-hidden="true"></i><address class="mb-0"><?= e($street) ?><?= $street !== '' ? '<br>' : '' ?><?= e($line2) ?></address></li><?php endif; ?>
            </ul>
            <?php if ($social): ?>
            <div class="profile-social" aria-label="<?= e($name) ?> on social media">
              <?php foreach ($social as $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($name . ' on ' . $s['label']) ?>" title="<?= e($s['label']) ?>"><i class="bi <?= e($s['icon']) ?>" aria-hidden="true"></i></a><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="updated-note mb-0"><a href="<?= e(url('/support?topic=correction&about=' . rawurlencode($name) . '#contact')) ?>"><i class="bi bi-flag me-1" aria-hidden="true"></i>Report incorrect information</a></p>
          </section>
        </div>
      </aside>
    </div>

    <?php if ($nearby): ?>
    <section class="vp-section vp-related" aria-labelledby="h-nearby">
      <div class="section-head">
        <h2 id="h-nearby"><?= $isMosque ? 'More mosques in ' . e($city) : 'More in ' . e($city) ?></h2>
        <?php if ($isMosque): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('/mosques?city=' . (int)$v['city_id'])) ?>">All mosques in <?= e($city) ?></a><?php endif; ?>
      </div>
      <div class="card-grid"><?php foreach ($nearby as $nb) { venue_card($nb, $nearbyBadges); } ?></div>
    </section>
    <?php endif; ?>

    <div class="claim-band">
      <div><strong>Are you part of <?= e($name) ?>?</strong><p class="mb-0">Sign in to the portal to update prayer times, Jumu'ah, programs and announcements.</p></div>
      <a class="btn btn-primary" href="<?= e(url(PORTAL_PATH . '/login')) ?>">Manage this listing</a>
    </div>
  </div>
</div>
<?php page_end(); ?>
