<?php
/**
 * Mosque directory  /mosques?city=&state=&q=&sort=&page=
 * Organizations by type  /organizations/{slug}/{role_id}
 */
$roleId = (int)$ROUTE['role'];
$isMosques = ($roleId === 1);
$role = null;
if (!$isMosques) {
    $role = get_role($roleId);
    if (!$role || $roleId === 1) {
        route_page('404');
    }
    $canon = org_role_url($role);
    $parts = explode('/', $canon);
    if (slug_key($ROUTE['slug']) !== slug_key($parts[2])) {
        redirect($canon . query_with(array()));
    }
}

$f = array(
    'role' => $roleId,
    'q' => str_param('q', 80),
    'city' => int_param('city'),
    'state' => int_param('state'),
    'sort' => in_array(str_param('sort', 10), array('updated', 'name'), true) ? str_param('sort', 10) : ($isMosques ? 'featured' : 'name'),
    'page' => max(1, int_param('page', 1)),
);
$city = $f['city'] ? get_city($f['city']) : null;
$state = $f['state'] ? get_state($f['state']) : null;
$res = list_venues($f);
$badges = venue_badges($res['rows']);

$noun = $isMosques ? 'mosque' : 'organization';
$typeName = $isMosques ? 'Mosques' : $role['role_title'];
$where = $city ? $city['city_title'] . (!empty($city['state_title']) ? ', ' . $city['state_title'] : '') : ($state ? $state['state_title'] : '');
$h1 = $isMosques ? ($where !== '' ? 'Mosques in ' . $where : 'Find a Mosque') : ($where !== '' ? $typeName . ' in ' . $where : $typeName);
$sub = $isMosques
    ? 'Search ' . number_format((int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1')) . ' mosques and Islamic centres across Canada and the United States by name, city or postal code.'
    : clean_text($role['description']);

// States that have listings of this type (for the province / state filter)
$states = db_all('SELECT s.state_id, s.state_title, co.country_title, COUNT(*) n FROM th_venue v JOIN th_city c ON c.city_id = v.city_id
    JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = s.country_id
    WHERE v.verify_status = 1 AND v.role_id = ? GROUP BY s.state_id ORDER BY co.country_id, s.state_title', 'i', array($roleId));

$basePath = $isMosques ? '/mosques' : org_role_url($role);
$canonQuery = array();
if ($f['city']) { $canonQuery['city'] = $f['city']; }
if ($f['state'] && !$f['city']) { $canonQuery['state'] = $f['state']; }
if ($f['page'] > 1) { $canonQuery['page'] = $f['page']; }

$ldItems = array();
foreach ($res['rows'] as $i => $r) {
    $ldItems[] = array('@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(venue_url($r)), 'name' => trim($r['venue_title']));
}
page_start(array(
    'title' => $h1 . ($f['page'] > 1 ? ' – Page ' . $f['page'] : '') . ' | The Hashir',
    'description' => $isMosques
        ? ($where !== '' ? 'All ' . plural($res['total'], 'mosque') . ' in ' . $where . ' with addresses, prayer and Jumu\'ah times, programs and services.' : $sub)
        : excerpt($sub . ' Browse ' . plural($res['total'], 'listing') . ' on The Hashir.', 158),
    'canonical' => $basePath . ($canonQuery ? '?' . http_build_query($canonQuery) : ''),
    'noindex' => $f['q'] !== '' || $res['total'] === 0,
    'jsonld' => $ldItems ? array(array('@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $ldItems)) : array(),
    'active' => $isMosques ? 'mosques' : 'orgs',
));

$crumbs = $isMosques
    ? array(array('Home', '/'), array('Mosques', $city || $state ? '/mosques' : null))
    : array(array('Home', '/'), array('Organizations', '/organizations-catalog'), array($typeName, $city || $state ? $basePath : null));
if ($city || $state) {
    $crumbs[] = array($where, null);
}
page_hero($h1, $sub, $crumbs);
?>
<section class="section-sm">
  <div class="container-xl">
    <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url($basePath)) ?>" role="search" aria-label="Filter <?= e(strtolower($typeName)) ?>">
      <div class="col-lg-4">
        <label class="form-label small fw-semibold" for="fq">Name, address or postal code</label>
        <input class="form-control" id="fq" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="e.g. Jame Masjid, L4Z 2G4">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label small fw-semibold" for="fcity">City</label>
        <?php city_select('city', $city, 'Any city', 'fcity'); ?>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small fw-semibold" for="fstate">Province / state</label>
        <select class="form-select" id="fstate" name="state">
          <option value="">Any</option>
          <?php $lastCountry = ''; foreach ($states as $s):
              if ($s['country_title'] !== $lastCountry) { if ($lastCountry !== '') { echo '</optgroup>'; } echo '<optgroup label="' . e($s['country_title']) . '">'; $lastCountry = $s['country_title']; } ?>
            <option value="<?= (int)$s['state_id'] ?>"<?= $f['state'] === (int)$s['state_id'] ? ' selected' : '' ?>><?= e($s['state_title']) ?> (<?= (int)$s['n'] ?>)</option>
          <?php endforeach; if ($lastCountry !== '') { echo '</optgroup>'; } ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label small fw-semibold" for="fsort">Sort by</label>
        <select class="form-select" id="fsort" name="sort">
          <?php if ($isMosques): ?><option value="featured"<?= $f['sort'] === 'featured' ? ' selected' : '' ?>>Recommended</option><?php endif; ?>
          <option value="name"<?= $f['sort'] === 'name' ? ' selected' : '' ?>>Name A–Z</option>
          <option value="updated"<?= $f['sort'] === 'updated' ? ' selected' : '' ?>>Recently updated</option>
        </select>
      </div>
      <div class="col-6 col-lg-1 d-grid">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </form>

    <?php if ($f['q'] !== '' || $city || $state): ?>
    <div class="active-filters" aria-label="Active filters">
      <?php if ($f['q'] !== ''): ?><a href="<?= e(url($basePath) . query_with(array('q' => null, 'page' => null))) ?>">“<?= e($f['q']) ?>” <i class="bi bi-x" aria-hidden="true"></i><span class="visually-hidden">Remove</span></a><?php endif; ?>
      <?php if ($city): ?><a href="<?= e(url($basePath) . query_with(array('city' => null, 'page' => null))) ?>"><?= e($city['city_title']) ?> <i class="bi bi-x" aria-hidden="true"></i><span class="visually-hidden">Remove</span></a><?php endif; ?>
      <?php if ($state): ?><a href="<?= e(url($basePath) . query_with(array('state' => null, 'page' => null))) ?>"><?= e($state['state_title']) ?> <i class="bi bi-x" aria-hidden="true"></i><span class="visually-hidden">Remove</span></a><?php endif; ?>
      <a href="<?= e(url($basePath)) ?>" class="bg-transparent text-decoration-underline">Clear all</a>
    </div>
    <?php endif; ?>

    <div class="listing-bar">
      <p class="count" role="status"><?= e($res['total'] ? result_range($res['total'], $res['per'], $res['page'], $noun) : '') ?></p>
      <?php if ($isMosques && $city): ?><a class="btn btn-soft btn-sm" href="<?= e(url(city_prayer_url($city))) ?>"><i class="bi bi-clock me-1" aria-hidden="true"></i>Prayer times in <?= e($city['city_title']) ?></a><?php endif; ?>
    </div>

    <?php if ($res['rows']): ?>
      <div class="card-grid">
        <?php foreach ($res['rows'] as $r) { venue_card($r, $badges); } ?>
      </div>
      <?php pagination($res['total'], $res['per'], $res['page']); ?>
    <?php else: ?>
      <?php empty_state('No ' . strtolower($typeName) . ' match your search', 'Try a shorter name, a nearby city, or clear the filters. If a ' . $noun . ' is missing, tell us and we\'ll add it.',
          array(array('Clear filters', $basePath), array('Suggest a listing', '/support?topic=listing#contact'))); ?>
    <?php endif; ?>
  </div>
</section>
<?php page_end(); ?>
