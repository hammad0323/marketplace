<?php
/** Category listing  /programs/{slug}/{id}, /services/{slug}/{id}, /events/{slug}/{id} */
$cat = get_category($ROUTE['id']);
if (!$cat) {
    route_page('404');
}
$g = grand_info($cat['grand_category_id']);
$canonPath = category_url($cat);
$parts = explode('/', $canonPath);
if ($ROUTE['type'] !== $g['key'] || slug_key($ROUTE['slug']) !== slug_key($parts[2])) {
    redirect($canonPath . query_with(array()));
}
$gc = (int)$cat['grand_category_id'];
$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
$f = array(
    'gc' => $gc,
    'category' => (int)$cat['category_id'],
    'q' => str_param('q', 80),
    'city' => int_param('city'),
    'gender' => in_array(int_param('gender'), array(1, 2), true) ? int_param('gender') : 0,
    'age' => int_param('age') >= 1 && int_param('age') <= 5 ? int_param('age') : 0,
    'day' => in_array(str_param('day', 10), $days, true) ? str_param('day', 10) : '',
    'sort' => in_array(str_param('sort', 10), array('recent', 'date'), true) ? str_param('sort', 10) : 'name',
    'org_type' => in_array(str_param('by', 10), array('mosque', 'org'), true) ? str_param('by', 10) : '',
    'venue' => int_param('venue'),
    'page' => max(1, int_param('page', 1)),
);
$city = $f['city'] ? get_city($f['city']) : null;
$res = list_details($f);
$siblings = get_categories($gc);
$ages = age_groups_by_id();
$name = $cat['name'];
$h1 = $name . ($city ? ' in ' . $city['city_title'] : '');
$noun = strtolower($g['single']);
$filtered = $f['q'] !== '' || $f['city'] || $f['gender'] || $f['age'] || $f['day'];

page_start(array(
    'title' => $h1 . ($gc === 3 ? ' – Find a Provider Near You' : ' Near You') . ($f['page'] > 1 ? ' – Page ' . $f['page'] : '') . ' | The Hashir',
    'description' => excerpt($cat['long_description'], 150),
    'canonical' => $canonPath . ($f['city'] ? '?city=' . $f['city'] : '') . ($f['page'] > 1 ? ($f['city'] ? '&' : '?') . 'page=' . $f['page'] : ''),
    'noindex' => $res['total'] === 0 || $f['q'] !== '' || $f['gender'] || $f['age'] || $f['day'],
    'active' => 'community',
));
page_hero($h1, clean_text($cat['long_description']), array(array('Home', '/'), array($g['plural'], $g['catalog']), array($name, null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="row g-4">
      <aside class="col-lg-3" aria-label="Filters">
        <button class="btn btn-outline-primary w-100 d-lg-none mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterBox" aria-expanded="false" aria-controls="filterBox"><i class="bi bi-sliders me-2" aria-hidden="true"></i>Filters<?= $filtered ? ' (on)' : '' ?></button>
        <div class="collapse d-lg-block" id="filterBox">
          <form class="filter-panel" method="get" action="<?= e(url($canonPath)) ?>" data-autosubmit>
            <h2>Filter</h2>
            <div class="filter-group">
              <label class="form-label" for="fcat">Category</label>
              <select class="form-select" id="fcat" data-go-select>
                <?php foreach ($siblings as $s): ?>
                <option value="<?= e(url(category_url($s))) ?>"<?= (int)$s['category_id'] === (int)$cat['category_id'] ? ' selected' : '' ?>><?= e($s['name']) ?> (<?= (int)$s['count'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group">
              <label class="form-label" for="fq">Keyword</label>
              <input class="form-control" id="fq" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name or mosque">
            </div>
            <div class="filter-group">
              <label class="form-label" for="fcity">City</label>
              <?php city_select('city', $city, 'Any city', 'fcity'); ?>
            </div>
            <?php if ($gc !== 3): ?>
            <div class="filter-group">
              <label class="form-label" for="fgender">For</label>
              <select class="form-select" id="fgender" name="gender">
                <option value="">Everyone</option>
                <option value="1"<?= $f['gender'] === 1 ? ' selected' : '' ?>>Brothers</option>
                <option value="2"<?= $f['gender'] === 2 ? ' selected' : '' ?>>Sisters</option>
              </select>
            </div>
            <div class="filter-group">
              <label class="form-label" for="fage">Age group</label>
              <select class="form-select" id="fage" name="age">
                <option value="">All ages</option>
                <?php foreach ($ages as $aid => $a): ?><option value="<?= (int)$aid ?>"<?= $f['age'] === $aid ? ' selected' : '' ?>><?= e($a['desc']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group">
              <label class="form-label" for="fday">Day</label>
              <select class="form-select" id="fday" name="day">
                <option value="">Any day</option>
                <?php foreach ($days as $d): ?><option<?= $f['day'] === $d ? ' selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="filter-group">
              <label class="form-label" for="fby">Offered by</label>
              <select class="form-select" id="fby" name="by">
                <option value="">Mosques &amp; organizations</option>
                <option value="mosque"<?= $f['org_type'] === 'mosque' ? ' selected' : '' ?>>Mosques only</option>
                <option value="org"<?= $f['org_type'] === 'org' ? ' selected' : '' ?>>Organizations only</option>
              </select>
            </div>
            <?php if ($f['venue']): ?><input type="hidden" name="venue" value="<?= (int)$f['venue'] ?>"><?php endif; ?>
            <div class="filter-group">
              <label class="form-label" for="fsort">Sort by</label>
              <select class="form-select" id="fsort" name="sort">
                <option value="name">Name A–Z</option>
                <option value="recent"<?= $f['sort'] === 'recent' ? ' selected' : '' ?>>Recently updated</option>
                <?php if ($gc === 1): ?><option value="date"<?= $f['sort'] === 'date' ? ' selected' : '' ?>>Event date</option><?php endif; ?>
              </select>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary flex-grow-1" type="submit">Apply</button>
              <?php if ($filtered): ?><a class="btn btn-soft" href="<?= e(url($canonPath)) ?>">Reset</a><?php endif; ?>
            </div>
          </form>
        </div>
      </aside>

      <div class="col-lg-9">
        <div class="listing-bar">
          <p class="count" role="status"><?= e($res['total'] ? result_range($res['total'], $res['per'], $res['page'], $noun) : '') ?></p>
        </div>
        <?php if ($res['rows']): ?>
          <div class="card-grid">
            <?php foreach ($res['rows'] as $d) { detail_card($d); } ?>
          </div>
          <?php pagination($res['total'], $res['per'], $res['page']); ?>
        <?php elseif ($gc === 1 && !$filtered): ?>
          <?php empty_state('No upcoming ' . $name . ' listed yet', 'Mosques usually post these closer to the date. Browse other events or weekly programs in the meantime.', array(array('All events', '/events-catalog'), array('Weekly programs', '/program-catalog')), 'bi-calendar-x'); ?>
        <?php else: ?>
          <?php empty_state('Nothing matches these filters', 'Try another city, remove a filter, or browse all ' . strtolower($name) . '.', array(array('Clear filters', $canonPath), array('Suggest a listing', '/support?topic=listing#contact'))); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php page_end(); ?>
