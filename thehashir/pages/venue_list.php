<?php
/** All programs / services / events of one venue  /details-list/{venue-slug}/{Programs|Services|Events}/{venue_id} */
$v = get_venue($ROUTE['id']);
$types = array('programs' => 2, 'services' => 3, 'events' => 1);
if (!$v || !isset($types[$ROUTE['type']])) {
    route_page('404');
}
$gc = $types[$ROUTE['type']];
$g = grand_info($gc);
$list = details_for_venue((int)$v['venue_id'], $gc);
$name = trim($v['venue_title']);
$canon = '/details-list/' . legacy_slug($v['url'] ? $v['url'] : $name) . '/' . $g['plural'] . '/' . (int)$v['venue_id'];
page_start(array(
    'title' => $g['plural'] . ' at ' . $name . (!empty($v['city_title']) ? ', ' . $v['city_title'] : '') . ' | The Hashir',
    'description' => 'All ' . strtolower($g['plural']) . ' offered by ' . $name . ' in ' . venue_place_line($v) . '.',
    'canonical' => $canon,
    'noindex' => !$list,
));
page_hero($g['plural'] . ' at ' . $name, plural(count($list), strtolower($g['single'])) . ' listed · ' . venue_place_line($v),
    array(array('Home', '/'), array((int)$v['role_id'] === 1 ? 'Mosques' : 'Organizations', (int)$v['role_id'] === 1 ? '/mosques' : '/organizations-catalog'), array($name, venue_url($v)), array($g['plural'], null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <?php if ($list): ?>
      <div class="card-grid"><?php foreach ($list as $d) { detail_card($d, false); } ?></div>
    <?php else: ?>
      <?php empty_state('No ' . strtolower($g['plural']) . ' listed', $name . ' has not listed any ' . strtolower($g['plural']) . ' yet.', array(array('Back to ' . $name, venue_url($v)))); ?>
    <?php endif; ?>
  </div>
</section>
<?php page_end(); ?>
