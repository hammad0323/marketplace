<?php
/** /search?q=&type= */
$q = str_param('q', 80);
$type = str_param('type', 10);
$gcFilter = array('programs' => 2, 'services' => 3, 'events' => 1);
$gc = isset($gcFilter[$type]) ? $gcFilter[$type] : 0;

$mosques = $orgs = $details = $cities = array();
$mosqueTotal = $orgTotal = 0;
if (mb_strlen($q, 'UTF-8') >= 2) {
    if (!$gc) {
        $m = list_venues(array('role' => 1, 'q' => $q, 'page' => 1, 'per' => 8, 'sort' => 'name'));
        $mosques = $m['rows'];
        $mosqueTotal = $m['total'];
        $o = list_venues(array('role' => 'org', 'q' => $q, 'page' => 1, 'per' => 8, 'sort' => 'name'));
        $orgs = $o['rows'];
        $orgTotal = $o['total'];
        $cities = search_cities($q, 8);
    }
    $like = '%' . $q . '%';
    $sql = 'SELECT ' . DETAIL_COLS . ', v.venue_title, v.url, v.role_id, v.profile_image, c.city_title FROM th_details d
        JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id
        WHERE ' . DETAIL_ACTIVE . ' AND v.verify_status = 1 AND (d.details_title LIKE ? OR v.venue_title LIKE ? OR c.city_title LIKE ?)';
    $types = 'sss';
    $params = array($like, $like, $like);
    if ($gc) {
        $sql .= ' AND d.grand_category_id = ?';
        $types .= 'i';
        $params[] = $gc;
    }
    $sql .= ' ORDER BY (d.details_title LIKE ?) DESC, d.details_title LIMIT 24';
    $types .= 's';
    $params[] = $like;
    $details = db_all($sql, $types, $params);
}
$total = count($mosques) + count($orgs) + count($details) + count($cities);

page_start(array(
    'title' => ($q !== '' ? 'Search results for “' . $q . '”' : 'Search') . ' | The Hashir',
    'description' => 'Search mosques, organizations, programs, services and cities on The Hashir.',
    'canonical' => '/search',
    'noindex' => true,
));
page_hero($q !== '' ? 'Results for “' . $q . '”' : 'Search The Hashir', $q !== '' && $total === 0 ? '' : 'Mosques, organizations, programs, services and cities.', array(array('Home', '/'), array('Search', null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <form class="search-card mb-4" action="<?= e(url('/search')) ?>" method="get" role="search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <label for="sq" class="visually-hidden">Search</label>
      <input id="sq" name="q" type="search" value="<?= e($q) ?>" placeholder="Mosque name, city, program or service…" autofocus>
      <?php if ($gc): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
      <button class="btn btn-primary px-4" type="submit">Search</button>
    </form>

    <?php if ($q !== '' && mb_strlen($q, 'UTF-8') < 2): ?>
      <p class="text-muted">Please type at least 2 characters.</p>
    <?php elseif ($q !== '' && $total === 0): ?>
      <?php empty_state('No results for “' . $q . '”', 'Check the spelling, try a shorter word, or search by city name.', array(array('Browse mosques', '/mosques'), array('Browse programs', '/program-catalog'))); ?>
    <?php endif; ?>

    <?php if ($cities): ?>
    <h2 class="h5 mt-2 mb-3">Cities</h2>
    <div class="city-chips mb-4">
      <?php foreach ($cities as $c): ?><a class="city-chip" href="<?= e(url('/mosques?city=' . (int)$c['city_id'])) ?>"><?= e($c['city_title'] . ', ' . $c['state_title']) ?><span class="n"><?= (int)$c['n'] ?></span></a><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($mosques): ?>
    <div class="section-head mt-4"><h2 class="h5 m-0">Mosques <span class="text-muted fw-normal">(<?= number_format($mosqueTotal) ?>)</span></h2>
      <?php if ($mosqueTotal > count($mosques)): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('/mosques?q=' . rawurlencode($q))) ?>">See all <?= number_format($mosqueTotal) ?></a><?php endif; ?></div>
    <div class="card-grid mb-4"><?php $bd = venue_badges($mosques); foreach ($mosques as $r) { venue_card($r, $bd); } ?></div>
    <?php endif; ?>

    <?php if ($orgs): ?>
    <div class="section-head mt-4"><h2 class="h5 m-0">Organizations <span class="text-muted fw-normal">(<?= number_format($orgTotal) ?>)</span></h2></div>
    <div class="card-grid mb-4"><?php foreach ($orgs as $r) { venue_card($r); } ?></div>
    <?php endif; ?>

    <?php if ($details): ?>
    <div class="section-head mt-4"><h2 class="h5 m-0"><?= $gc ? e(grand_info($gc)['plural']) : 'Programs, services &amp; events' ?></h2></div>
    <div class="card-grid"><?php foreach ($details as $d) { detail_card($d); } ?></div>
    <?php endif; ?>
  </div>
</section>
<?php page_end(); ?>
