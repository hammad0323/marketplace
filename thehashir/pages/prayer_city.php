<?php
/** City prayer times  /prayer-times/{Country}/{City}/{city_id}?date=YYYY-MM-DD */
$city = get_city($ROUTE['id']);
if (!$city) {
    route_page('404');
}
$canonPath = city_prayer_url($city);
$parts = explode('/', $canonPath);
if (slug_key($ROUTE['slug']) !== slug_key($parts[3])) {
    redirect($canonPath);
}
$cityId = (int)$city['city_id'];
$tz = state_timezone($city['state_title']);
$today = today_in($tz);
$date = $today;
$reqDate = str_param('date', 10);
if ($reqDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate) && strtotime($reqDate)) {
    $diff = (strtotime($reqDate) - strtotime($today)) / 86400;
    if ($diff >= -60 && $diff <= 120) {
        $date = $reqDate;
    }
}
$isToday = ($date === $today);
$prev = date('Y-m-d', strtotime($date . ' -1 day'));
$next = date('Y-m-d', strtotime($date . ' +1 day'));

$daily = db_all('SELECT pt.*, v.venue_id, v.venue_title, v.url, v.role_id, v.street_address FROM prayer_timings pt
    JOIN th_venue v ON v.venue_id = pt.venue_id
    WHERE v.city_id = ? AND v.verify_status = 1 AND pt.georgian_date = ? ORDER BY v.venue_title', 'is', array($cityId, $date));

$dow = (int)date('N', strtotime($date));
$friday = $dow === 5 ? $date : date('Y-m-d', strtotime($date . ' next friday'));
// Jumu'ah: current schedules first; for mosques whose schedule has lapsed, show their last
// schedule for the same season, clearly marked as possibly out of date.
$jVenues = db_all('SELECT DISTINCT v.venue_id, v.venue_title, v.url, v.role_id, v.street_address FROM th_jummah_prayers j
    JOIN th_venue v ON v.venue_id = j.venue_id WHERE v.city_id = ? AND v.verify_status = 1 ORDER BY v.venue_title', 'i', array($cityId));
$jummah = array();
$jummahStale = array();
foreach ($jVenues as $jv) {
    $info = jummah_for_profile((int)$jv['venue_id'], $friday, $tz);
    if (!$info['sessions']) {
        continue;
    }
    $entry = array('venue' => $jv, 'sessions' => $info['sessions'], 'to' => $info['to']);
    if ($info['stale']) {
        $jummahStale[] = $entry;
    } else {
        $jummah[] = $entry;
    }
}
$cityList = prayer_cities();
$mosqueCount = (int)db_val('SELECT COUNT(*) FROM th_venue WHERE city_id = ? AND role_id = 1 AND verify_status = 1', 'i', array($cityId));

$cityName = $city['city_title'];
$h1 = 'Prayer Times in ' . $cityName . ($isToday ? ' Today' : '');
page_start(array(
    'title' => 'Prayer Times in ' . $cityName . ', ' . $city['state_title'] . ' – Iqamah & Jumu\'ah Times | The Hashir',
    'description' => 'Today\'s Azan and Iqamah times and Friday Jumu\'ah times at ' . plural(count($daily) + count($jummah) + count($jummahStale), 'mosque') . ' in ' . $cityName . ', ' . $city['state_title'] . ', published by each mosque.',
    'canonical' => $canonPath,
    'noindex' => !$isToday || (!$daily && !$jummah && !$jummahStale),
    'active' => 'community',
));
page_hero($h1, fmt_date($date, 'l, j F Y') . ' · ' . $cityName . ', ' . $city['state_title'], array(array('Home', '/'), array('Prayer Times', '/prayer-times'), array($cityName, null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-end mb-4">
      <nav class="date-nav" aria-label="Choose date">
        <a class="btn btn-soft btn-sm" href="<?= e(url($canonPath) . '?date=' . $prev) ?>" rel="nofollow"><i class="bi bi-chevron-left" aria-hidden="true"></i> <?= e(fmt_date($prev, 'D j M')) ?></a>
        <span class="current"><?= $isToday ? 'Today' : e(fmt_date($date, 'D j M')) ?></span>
        <a class="btn btn-soft btn-sm" href="<?= e(url($canonPath) . '?date=' . $next) ?>" rel="nofollow"><?= e(fmt_date($next, 'D j M')) ?> <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        <?php if (!$isToday): ?><a class="btn btn-link btn-sm" href="<?= e(url($canonPath)) ?>">Back to today</a><?php endif; ?>
      </nav>
      <div style="min-width:260px">
        <label class="form-label small fw-semibold" for="ptCity">Change city</label>
        <select class="form-select" id="ptCity" data-prayer-city>
          <?php foreach ($cityList as $c): ?>
          <option value="<?= e(url(city_prayer_url($c))) ?>"<?= (int)$c['city_id'] === $cityId ? ' selected' : '' ?>><?= e($c['city_title'] . ', ' . $c['state_title']) ?></option>
          <?php endforeach; ?>
          <?php if (!$daily && !$jummah && !$jummahStale): ?><option value="" selected><?= e($cityName) ?></option><?php endif; ?>
        </select>
      </div>
    </div>

    <h2 class="h4 mb-3">Daily prayers</h2>
    <?php if ($daily): ?>
      <div class="table-wrap d-none d-md-block mb-2">
        <table class="pt-table">
          <caption class="visually-hidden">Azan and Iqamah times for <?= e(fmt_date($date, 'j F Y')) ?></caption>
          <thead><tr><th scope="col">Mosque</th><th scope="col">Fajr</th><th scope="col">Sunrise</th><th scope="col">Dhuhr</th><th scope="col">Asr</th><th scope="col">Maghrib</th><th scope="col">Isha</th></tr></thead>
          <tbody>
          <?php foreach ($daily as $p): $rows = prayer_rows($p); $byName = array(); foreach ($rows as $r) { $byName[$r['name']] = $r; } ?>
            <tr>
              <th scope="row" class="mosque"><a href="<?= e(url(venue_url($p))) ?>"><?= e(trim($p['venue_title'])) ?></a><div class="small text-muted fw-normal"><?= e(excerpt($p['street_address'], 40)) ?></div></th>
              <?php foreach (array('Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha') as $nm): ?>
                <?php if ($nm === 'Sunrise'): ?>
                  <td><span class="iq"><?= e(fmt_time($p['sunrise'])) ?></span></td>
                <?php elseif (isset($byName[$nm])): $r = $byName[$nm]; ?>
                  <td><span class="az"><?= e($r['azan'] !== '' ? $r['azan'] : $r['iqama']) ?></span><?php if ($r['iqama'] !== '' && $r['azan'] !== ''): ?><span class="iq">Iqamah <?= e($r['iqama']) ?></span><?php endif; ?></td>
                <?php else: ?><td><span class="iq">—</span></td><?php endif; ?>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-md-none">
        <?php foreach ($daily as $p): $rows = prayer_rows($p); ?>
        <div class="mobile-pt-card">
          <a class="fw-semibold text-decoration-none" href="<?= e(url(venue_url($p))) ?>"><?= e(trim($p['venue_title'])) ?></a>
          <dl>
            <?php foreach ($rows as $r): ?><div><dt><?= e($r['name']) ?></dt><dd><?= e($r['azan'] !== '' ? $r['azan'] : $r['iqama']) ?><?php if ($r['iqama'] !== '' && $r['azan'] !== ''): ?><small>Iq <?= e($r['iqama']) ?></small><?php endif; ?></dd></div><?php endforeach; ?>
          </dl>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="small text-muted">Times are published by each mosque. Azan time shown in bold, Iqamah (congregation) below.</p>
    <?php else: ?>
      <div class="alert alert-light border"><i class="bi bi-info-circle text-primary me-2" aria-hidden="true"></i>No mosque in <?= e($cityName) ?> has published daily prayer times for this date yet<?= $jummah || $jummahStale ? ' — Jumu\'ah times are below' : '' ?>.</div>
    <?php endif; ?>

    <h2 class="h4 mt-5 mb-1">Friday Jumu'ah times</h2>
    <p class="text-muted mb-3"><?= e(fmt_date($friday, 'l, j F Y')) ?></p>
    <?php
    $jGroups = array(array($jummah, false), array($jummahStale, true));
    foreach ($jGroups as $grp):
        list($list, $isStale) = $grp;
        if (!$list) { continue; }
        if ($isStale): ?>
      <h3 class="h6 mt-4 mb-1">Last published schedules</h3>
      <p class="small text-muted mb-3"><i class="bi bi-exclamation-triangle text-warning me-1" aria-hidden="true"></i>These mosques have not published this season's Jumu'ah times yet. Times shown are from their last schedule and may have changed — please confirm with the mosque.</p>
    <?php endif; ?>
      <div class="row g-3">
        <?php foreach ($list as $j): $sess = $j['sessions']; ?>
        <div class="col-md-6 col-lg-4">
          <div class="side-card h-100">
            <a class="fw-semibold text-decoration-none d-block mb-1" href="<?= e(url(venue_url($j['venue']))) ?>"><?= e(trim($j['venue']['venue_title'])) ?></a>
            <div class="small text-muted mb-2"><?= e(excerpt($j['venue']['street_address'], 50)) ?><?= $isStale ? ' · until ' . e(fmt_date($j['to'], 'j M Y')) : '' ?></div>
            <ul class="list-unstyled mb-0">
              <?php foreach ($sess as $i => $s): ?>
              <li class="d-flex justify-content-between border-top py-2">
                <span><?= e(count($sess) > 1 ? ordinal($i + 1) . ' Jumu\'ah' : 'Jumu\'ah') ?><?= $s['place'] !== '' ? '<span class="d-block small text-muted">' . e($s['place']) . '</span>' : '' ?></span>
                <span class="text-end"><?php if ($s['iqama'] === ''): ?><strong class="text-primary"><?= e($s['khutbah']) ?></strong> <span class="small text-muted">Khutbah</span><?php else: ?><?php if ($s['khutbah'] !== ''): ?><span class="small text-muted">Khutbah <?= e($s['khutbah']) ?> · </span><?php endif; ?><strong class="text-primary"><?= e($s['iqama']) ?></strong><?php endif; ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$jummah && !$jummahStale): ?>
      <div class="alert alert-light border">No Jumu'ah times published for <?= e($cityName) ?> yet.</div>
    <?php endif; ?>

    <div class="claim-band">
      <p><strong><?= number_format($mosqueCount) ?> mosques are listed in <?= e($cityName) ?>.</strong> Don't see yours here? Ask the mosque to share its timetable, or send it to us.</p>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="<?= e(url('/mosques?city=' . $cityId)) ?>">Mosques in <?= e($cityName) ?></a>
        <a class="btn btn-primary" href="<?= e(url('/support?topic=listing&about=' . rawurlencode('Prayer times – ' . $cityName) . '#contact')) ?>">Send a timetable</a>
      </div>
    </div>
  </div>
</section>
<?php page_end('<script>jQuery(function($){$("[data-prayer-city]").select2({width:"100%"}).on("select2:select",function(e){if(e.params.data.id){window.location.href=e.params.data.id;}});});</script>'); ?>
