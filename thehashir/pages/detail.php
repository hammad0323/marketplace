<?php
/** Program / service / event detail  /details/{slug}/{id} */
$d = get_detail($ROUTE['id']);
if (!$d) {
    route_page('404');
}
$canonPath = detail_url($d);
$parts = explode('/', $canonPath);
if (slug_key($ROUTE['slug']) !== slug_key($parts[2])) {
    redirect($canonPath);
}
$v = $d['venue_id'] ? get_venue((int)$d['venue_id']) : null;
$g = grand_info($d['grand_category_id']);
$cats = detail_categories($d);
$title = trim($d['details_title']);
$when = detail_when($d);
$gender = audience_gender($d['gender_id']);
$ages = audience_ages($d['age_group_id']);
$img = detail_image($d);
$desc = safe_html($d['description']);
$location = trim(clean_text($d['event_location']));
$more = $v ? details_for_venue((int)$v['venue_id'], 0, (int)$d['detail_id'], 6) : array();
$related = related_details($d, 6);

$hostName = $v ? trim($v['venue_title']) : '';
$city = $v ? trim((string)$v['city_title']) : '';
$street = $v ? trim((string)$v['street_address']) : '';
$directions = $v ? maps_link(array($hostName, $street, $city, $v['state_title'], $v['postal_code'], $v['country_title'])) : '';
$phoneHref = $v ? tel_href($v['phone_number']) : '';
$email = $v && filter_var(trim((string)$v['email_address']), FILTER_VALIDATE_EMAIL) ? trim($v['email_address']) : '';
$website = $v ? safe_url($v['website_link']) : '';

$metaTitle = $title . ($hostName !== '' ? ' at ' . $hostName : '') . ($city !== '' ? ', ' . $city : '') . ' | The Hashir';
$metaDesc = excerpt($desc !== '' ? $desc : ($title . ' at ' . $hostName . ' in ' . $city . '.'), 158);

$ld = array();
if ((int)$d['grand_category_id'] === 1 && valid_date($d['start_date'])) {
    $ld[] = array(
        '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $title, 'startDate' => $d['start_date'],
        'endDate' => valid_date($d['end_date']) ? $d['end_date'] : $d['start_date'],
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => array('@type' => 'Place', 'name' => $hostName, 'address' => trim($street . ', ' . $city)),
        'description' => excerpt($desc, 300), 'image' => $img !== '' ? $img : null,
        'organizer' => array('@type' => 'Organization', 'name' => $hostName, 'url' => $v ? abs_url(venue_url($v)) : SITE_URL),
    );
} elseif ($v) {
    $ld[] = array(
        '@context' => 'https://schema.org', '@type' => 'Service', 'name' => $title, 'serviceType' => $cats ? $cats[0]['name'] : $g['single'],
        'provider' => array('@type' => (int)$v['role_id'] === 1 ? 'Mosque' : 'Organization', 'name' => $hostName, 'url' => abs_url(venue_url($v))),
        'areaServed' => $city, 'description' => excerpt($desc, 300),
    );
}

page_start(array('title' => $metaTitle, 'description' => $metaDesc, 'canonical' => $canonPath, 'image' => $img !== '' ? $img : ($v ? venue_cover($v) : ''), 'jsonld' => $ld, 'active' => 'community'));

$crumbs = array(array('Home', '/'), array($g['plural'], $g['catalog']));
if ($cats) {
    $crumbs[] = array($cats[0]['name'], category_url($cats[0]));
}
$crumbs[] = array($title, null);
?>
<div class="container-xl pt-3">
  <?php breadcrumb($crumbs); ?>
  <div class="row g-4 g-lg-5 mt-1">
    <div class="col-lg-8">
      <div class="d-flex flex-wrap gap-2 mb-2">
        <?php foreach ($cats as $c): ?><a class="chip text-decoration-none" href="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>
      <h1 class="h2 fw-bold mb-2"><?= e($title) ?></h1>
      <?php if ($v): ?><p class="text-muted mb-4">by <a href="<?= e(url(venue_url($v))) ?>"><?= e($hostName) ?></a><?= $city !== '' ? ' · ' . e($city) : '' ?></p><?php endif; ?>

      <?php if ($img !== ''): ?>
      <div class="detail-hero-img mb-4"><img src="<?= e($img) ?>" alt="<?= e($title) ?>" fetchpriority="high" onerror="this.parentNode.remove()"></div>
      <?php endif; ?>

      <?php if ($desc !== ''): ?>
      <div class="rich mb-4"><?= $desc ?></div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-2 mb-5">
        <?php if ($directions): ?><a class="btn btn-soft" href="<?= e($directions) ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill me-2" aria-hidden="true"></i>Directions</a><?php endif; ?>
        <button type="button" class="btn btn-primary" data-share-btn data-title="<?= e($title) ?>"><i class="bi bi-share me-2" aria-hidden="true"></i>Share</button>
        <?php if ($phoneHref): ?><a class="btn btn-outline-primary" href="<?= e($phoneHref) ?>"><i class="bi bi-telephone me-2" aria-hidden="true"></i>Call</a><?php endif; ?>
      </div>
    </div>

    <aside class="col-lg-4">
      <div class="side-card">
        <h2>Details</h2>
        <ul class="facts">
          <?php if ($when !== ''): ?><li><i class="bi bi-calendar3" aria-hidden="true"></i><div><span class="label">When</span><?= e($when) ?></div></li><?php endif; ?>
          <?php if ($gender !== '' || $ages !== ''): ?><li><i class="bi bi-people" aria-hidden="true"></i><div><span class="label">Who it's for</span><?= e(trim($gender . ($gender !== '' && $ages !== '' ? ' · ' : '') . $ages)) ?></div></li><?php endif; ?>
          <?php if ($location !== '' || $street !== ''): ?><li><i class="bi bi-geo-alt" aria-hidden="true"></i><div><span class="label">Where</span><?= e($location !== '' ? $location : $street . ($city !== '' ? ', ' . $city : '')) ?></div></li><?php endif; ?>
          <li><i class="bi bi-tag" aria-hidden="true"></i><div><span class="label">Type</span><?= e($g['single']) ?><?= $cats ? ' · ' . e($cats[0]['name']) : '' ?></div></li>
          <?php if (valid_date($d['update_date'])): ?><li><i class="bi bi-arrow-repeat" aria-hidden="true"></i><div><span class="label">Last updated</span><?= e(fmt_date($d['update_date'])) ?></div></li><?php endif; ?>
        </ul>
      </div>
      <?php if ($v): ?>
      <div class="side-card">
        <h2>Offered by</h2>
        <a class="host-row mb-3" href="<?= e(url(venue_url($v))) ?>">
          <span class="logo"><?php img_or_initials(venue_logo($v), $hostName . ' logo', $hostName); ?></span>
          <span><strong><?= e($hostName) ?></strong><small class="text-muted"><?= e(venue_place_line($v)) ?></small></span>
        </a>
        <ul class="contact-list mb-0">
          <?php if ($phoneHref): ?><li><i class="bi bi-phone" aria-hidden="true"></i><a href="<?= e($phoneHref) ?>"><?= e(trim($v['phone_number'])) ?></a></li><?php endif; ?>
          <?php if ($email): ?><li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e($email) ?>?subject=<?= rawurlencode($title) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if ($website): ?><li><i class="bi bi-globe2" aria-hidden="true"></i><a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= e(display_host($website)) ?></a></li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <p class="small text-muted mt-3">Details are provided by the <?= $v && (int)$v['role_id'] === 1 ? 'mosque' : 'organization' ?>. Please confirm times and fees directly. <a href="<?= e(url('/support?topic=correction&about=' . rawurlencode($title . ' (' . $hostName . ')') . '#contact')) ?>">Report an error</a></p>
    </aside>
  </div>

  <?php if ($more): ?>
  <section class="profile-section" aria-labelledby="h-more">
    <div class="section-head">
      <h2 id="h-more">More from <?= e($hostName) ?></h2>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url(venue_url($v))) ?>">View profile</a>
    </div>
    <div class="card-grid"><?php foreach ($more as $m) { detail_card($m, false); } ?></div>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="profile-section" aria-labelledby="h-related">
    <div class="section-head">
      <h2 id="h-related">Similar <?= e(strtolower($g['plural'])) ?><?= $city !== '' ? ' near ' . e($city) : '' ?></h2>
      <?php if ($cats): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url(category_url($cats[0]))) ?>">All <?= e($cats[0]['name']) ?></a><?php endif; ?>
    </div>
    <div class="card-grid"><?php foreach ($related as $r) { detail_card($r); } ?></div>
  </section>
  <?php endif; ?>
</div>
<?php page_end(); ?>
