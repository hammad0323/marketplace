<?php
/** /program-catalog, /services-catalog, /events-catalog */
$gc = (int)$ROUTE['gc'];
$g = grand_info($gc);
$cats = get_categories($gc);
$total = 0;
foreach ($cats as $c) {
    $total += $c['count'];
}
$intro = array(
    2 => 'Quran memorisation, Tajweed, Islamic studies, youth, sisters and seniors programs run by mosques and organizations.',
    3 => 'Nikah, counselling, funeral, food bank, matrimonial and outreach services offered by mosques and Muslim organizations.',
    1 => 'Ramadan programs, Eid bazaars, Eid prayers and community events hosted by mosques and organizations.',
);
page_start(array(
    'title' => 'Islamic ' . $g['plural'] . ' Near You – Browse by Category | The Hashir',
    'description' => $intro[$gc],
    'canonical' => $g['catalog'],
    'active' => 'community',
));
page_hero('Explore ' . $g['plural'], $intro[$gc], array(array('Home', '/'), array($g['plural'], null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <form class="toolbar-search mb-4" method="get" action="<?= e(url('/search')) ?>" role="search">
      <input type="hidden" name="type" value="<?= e($g['key']) ?>">
      <label for="catq" class="visually-hidden">Search <?= e(strtolower($g['plural'])) ?></label>
      <input id="catq" class="form-control flex-grow-1" type="search" name="q" placeholder="Search <?= e(strtolower($g['plural'])) ?> by name or mosque…">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <?php if ($gc === 1 && $total === 0): ?>
      <div class="alert alert-light border mb-4" role="status"><i class="bi bi-info-circle me-2 text-primary" aria-hidden="true"></i>No upcoming events are listed right now. Mosques add Ramadan and Eid events closer to the date — check back soon or browse <a href="<?= e(url('/program-catalog')) ?>">weekly programs</a>.</div>
    <?php endif; ?>
    <div class="card-grid">
      <?php foreach ($cats as $c): ?>
      <article class="cat-card<?= $c['count'] ? '' : ' is-empty' ?>">
        <div class="cat-card-media"><i class="bi <?= e($g['icon']) ?>" aria-hidden="true"></i><?php if ($c['profile_image']): ?><img src="<?= e(media($c['profile_image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></div>
        <div class="cat-card-body">
          <h2><a class="stretched-link" href="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></a></h2>
          <p><?= e(excerpt($c['long_description'], 120)) ?></p>
          <span class="cat-card-count"><?= $c['count'] ? e(plural($c['count'], $gc === 1 ? 'upcoming event' : 'listing')) : ($gc === 1 ? 'No upcoming events' : 'No listings yet') ?></span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php page_end(); ?>
