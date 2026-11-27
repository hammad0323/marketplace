<?php
/**
 * /manage/locations — add or verify map locations for mosques.
 * Look-ups use OpenStreetMap Nominatim from the browser, one at a time; nothing is saved until an admin confirms each pin.
 */
require_admin('locations');
if (is_post()) {
    require_post_csrf();
    $id = post_int('venue_id');
    $lat = post('lat');
    $lng = post('lng');
    $v = $id ? db_one('SELECT venue_id, address_iframe FROM th_venue WHERE venue_id = ?', 'i', array($id)) : null;
    if (!$v || !valid_lat_lng($lat, $lng)) {
        json_out(array('ok' => false, 'message' => 'Invalid location.'), 422);
    }
    $iframe = coords_iframe($lat, $lng);
    db_exec('UPDATE th_venue SET address_iframe = ?, update_date = CURDATE() WHERE venue_id = ?', 'si', array($iframe, $id));
    coords_cache_clear();
    log_me('venue.location_confirmed', 'venue', $id, array('address_iframe' => $v['address_iframe']), array('lat' => (float)$lat, 'lng' => (float)$lng));
    json_out(array('ok' => true));
}
$show = str_param('show', 10) === 'set' ? 'set' : 'missing';
$cityId = int_param('city');
$page = max(1, int_param('page', 1));
$per = 25;
$params = array();
$types = '';
$where = "v.role_id = 1 AND v.verify_status = 1";
if ($cityId) {
    $where .= ' AND v.city_id = ?';
    $types .= 'i';
    $params[] = $cityId;
}
$all = db_all("SELECT v.venue_id, v.venue_title, v.street_address, v.postal_code, v.address_iframe, c.city_title, s.state_title, co.country_title
    FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
    WHERE $where ORDER BY c.city_title, v.venue_title", $types, $params);
$list = array();
foreach ($all as $r) {
    $c = coords_from_iframe($r['address_iframe']);
    if (($show === 'missing') === !$c) {
        $r['coords'] = $c;
        $list[] = $r;
    }
}
$total = count($list);
$rows = array_slice($list, ($page - 1) * $per, $per);
bo_start('admin', 'Map locations', array('leaflet' => true));
?>
<p class="text-muted">Mosque maps, “near me” and the blog map need a confirmed location. Use <strong>Look up</strong> to find a suggestion from the address, check the pin (drag it if needed) and <strong>Confirm</strong>. Nothing is saved without your confirmation.</p>
<div class="d-flex flex-wrap gap-2 align-items-end mb-3">
  <div class="btn-group">
    <a class="btn btn-<?= $show === 'missing' ? 'primary' : 'outline-primary' ?>" href="<?= e(query_with(array('show' => null, 'page' => null))) ?>">Missing</a>
    <a class="btn btn-<?= $show === 'set' ? 'primary' : 'outline-primary' ?>" href="<?= e(query_with(array('show' => 'set', 'page' => null))) ?>">Confirmed</a>
  </div>
  <form method="get" class="d-flex gap-2" style="min-width:260px"><input type="hidden" name="show" value="<?= e($show) ?>"><?php bo_city_select('city', $cityId, false, 'lcity'); ?><button class="btn btn-outline-secondary">Filter</button></form>
  <?php if ($show === 'missing' && $rows): ?><button type="button" class="btn btn-outline-primary ms-auto" data-geo-lookup-all><i class="bi bi-search me-1"></i>Look up all on this page</button><?php endif; ?>
</div>
<div class="card bo-card">
  <div class="card-header small text-muted"><?= number_format($total) ?> mosques</div>
  <ul class="list-group list-group-flush">
  <?php if (!$rows): ?><li class="list-group-item text-muted">Nothing here.</li><?php endif; ?>
  <?php foreach ($rows as $r): $addr = trim(implode(', ', array_filter(array(trim((string)$r['street_address']), $r['city_title'], $r['state_title'], $r['postal_code'], $r['country_title'])))); ?>
    <li class="list-group-item loc-row" data-loc-row data-id="<?= (int)$r['venue_id'] ?>" data-address="<?= e($addr) ?>" data-name="<?= e(trim($r['venue_title'])) ?>"<?= $r['coords'] ? ' data-lat="' . e($r['coords']['lat']) . '" data-lng="' . e($r['coords']['lng']) . '"' : '' ?>>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto"><a class="fw-semibold" href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'])) ?>"><?= e(trim($r['venue_title'])) ?></a><div class="small text-muted"><?= e($addr) ?></div></div>
        <span class="small" data-loc-status><?= $r['coords'] ? '<span class="text-success">Confirmed</span>' : '' ?></span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-loc-lookup><?= $r['coords'] ? 'Check' : 'Look up' ?></button>
        <button type="button" class="btn btn-sm btn-success" data-loc-save hidden>Confirm</button>
      </div>
      <div class="loc-map mt-2" data-loc-map hidden></div>
    </li>
  <?php endforeach; ?>
  </ul>
  <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div>
</div>
<p class="small text-muted mt-3">Address look-ups use OpenStreetMap Nominatim (max. one request per second). Results can be wrong — always check the pin.</p>
<?php bo_end();
