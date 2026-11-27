<?php
/** /mosque-map — map-based discovery of mosques with confirmed locations, plus "near me". */
$points = venues_with_coords(1);
$total = (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1');
$center = setting('map_center', array('lat' => 43.6532, 'lng' => -79.3832, 'zoom' => 9));
$cfg = array('tiles' => setting('map_tiles'), 'attribution' => setting('map_attribution'), 'center' => $center, 'api' => url('/api/map'));
page_start(array(
    'title' => 'Mosque Map – Find Mosques Near You | The Hashir',
    'description' => 'Find mosques near you on an interactive map. Use your location to see the closest mosques, then open each profile for prayer times and Jumu\'ah.',
    'canonical' => '/mosque-map',
    'active' => 'mosques',
));
page_hero('Mosques near you', 'Use your location to find the closest mosques, or explore the map.', array(array('Home', '/'), array('Mosques', '/mosques'), array('Map', null)));
?>
<section class="section-sm pt-0">
  <div class="container-xl">
    <div class="map-discovery">
      <div class="map-side">
        <button type="button" class="btn btn-primary w-100 mb-3" data-near-me><i class="bi bi-crosshair me-2" aria-hidden="true"></i>Use my location</button>
        <p class="small text-muted" data-near-status role="status"><?= number_format(count($points)) ?> mosques have a confirmed map location. <a href="<?= e(url('/mosques')) ?>">Browse all <?= number_format($total) ?> mosques</a> by city.</p>
        <ol class="near-list list-unstyled" data-near-list></ol>
      </div>
      <div class="map-main"><div id="discoveryMap" class="discovery-map" data-discovery-map="<?= e(json_encode($cfg, JSON_UNESCAPED_SLASHES)) ?>" role="region" aria-label="Map of mosques"></div></div>
    </div>
  </div>
</section>
<?php page_end('<link rel="stylesheet" href="' . e(asset('vendor/leaflet/leaflet.css')) . '"><script src="' . e(asset('vendor/leaflet/leaflet.js')) . '"></script>'); ?>
