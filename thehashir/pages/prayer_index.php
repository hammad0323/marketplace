<?php
/** /prayer-times — cities that have prayer or Jumu'ah times today */
$cities = prayer_cities();
$groups = array();
foreach ($cities as $c) {
    $country = $c['country_title'] ? $c['country_title'] : 'Other';
    $state = $c['state_title'] ? $c['state_title'] : '';
    $groups[$country][$state][] = $c;
}
$visitor = visitor_city_id() ? get_city(visitor_city_id()) : null;
page_start(array(
    'title' => 'Prayer Times & Jumu\'ah Times by City – Mosque Iqamah Times | The Hashir',
    'description' => 'Daily Azan and Iqamah times and Friday Jumu\'ah times published by mosques in ' . count($cities) . ' cities across Canada and the United States.',
    'canonical' => '/prayer-times',
    'active' => 'community',
));
page_hero('Prayer Times by City', 'Azan, Iqamah and Jumu\'ah times published by each mosque. Pick your city to see today\'s timetable.', array(array('Home', '/'), array('Prayer Times', null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="row g-3 align-items-end mb-4">
      <div class="col-md-6 col-lg-5">
        <label class="form-label fw-semibold" for="ptCity">Find your city</label>
        <select class="form-select" id="ptCity" data-prayer-city data-placeholder="Type a city name">
          <option value="">Type a city name</option>
          <?php foreach ($cities as $c): ?>
          <option value="<?= e(url(city_prayer_url($c))) ?>"><?= e($c['city_title'] . ', ' . $c['state_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($visitor): ?>
      <div class="col-md-auto"><a class="btn btn-primary" href="<?= e(url(city_prayer_url($visitor))) ?>"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Prayer times in <?= e($visitor['city_title']) ?></a></div>
      <?php endif; ?>
    </div>

    <?php if (!$cities): ?>
      <?php empty_state('No timetables published today', 'Mosques have not published prayer times for today yet. Please check back later.', array(array('Find a mosque', '/mosques'))); ?>
    <?php endif; ?>

    <?php foreach ($groups as $country => $states): ?>
    <div class="city-group">
      <h2 class="h4 mt-4 mb-2"><?= e($country) ?></h2>
      <?php foreach ($states as $state => $list): ?>
        <h3><?= e($state) ?></h3>
        <div class="city-chips">
          <?php foreach ($list as $c): ?>
          <a class="city-chip" href="<?= e(url(city_prayer_url($c))) ?>"><?= e($c['city_title']) ?>
            <span class="n" title="Mosques with daily times · with Jumu'ah times"><?= (int)$c['daily'] ? (int)$c['daily'] . ' daily · ' : '' ?><?= (int)$c['jummah'] ?> Jumu'ah</span></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <p class="small text-muted mt-4">Only cities where at least one mosque has published today's times are listed. Is your mosque missing? <a href="<?= e(url('/support?topic=listing#contact')) ?>">Send us its timetable</a>.</p>
  </div>
</section>
<?php page_end('<script>jQuery(function($){$("[data-prayer-city]").select2({width:"100%",placeholder:"Type a city name"}).on("select2:select",function(e){window.location.href=e.params.data.id;});});</script>'); ?>
