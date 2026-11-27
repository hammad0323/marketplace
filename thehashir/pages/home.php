<?php
/** Homepage  / */
global $SOCIAL_LINKS, $HOME_SLIDES;

$programs = get_categories(2);
$services = get_categories(3);
$events = get_categories(1);
$roles = get_org_roles();
$rolesById = array();
foreach ($roles as $r) {
    $rolesById[(int)$r['role_id']] = $r;
}
$mosqueRole = get_role(1);
$mosqueCount = (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1');
$cities = top_cities(14);
$blogs = get_blogs(3);
$fm = (array)setting('featured_mosques', array());
$featuredMosques = $fm ? venues_by_ids($fm) : list_venues(array('role' => 1, 'page' => 1, 'per' => 8, 'sort' => 'featured'))['rows'];
$featuredMosques = array_slice($featuredMosques, 0, 8);
$fmBadges = venue_badges($featuredMosques);
$fo = (array)setting('featured_orgs', array());
$featuredOrgs = $fo ? venues_by_ids($fo) : list_venues(array('role' => 'org', 'page' => 1, 'per' => 4, 'sort' => 'featured'))['rows'];
$featuredOrgs = array_slice($featuredOrgs, 0, 8);
$upcoming = db_all('SELECT ' . DETAIL_COLS . ', v.venue_title, v.url, v.role_id, v.profile_image, c.city_title FROM th_details d
    JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id
    WHERE d.grand_category_id = 1 AND v.verify_status = 1 AND ' . DETAIL_ACTIVE . ' ORDER BY (d.start_date < \'1000-01-01\'), d.start_date LIMIT 4');
$visitorCity = visitor_city_id() ? get_city(visitor_city_id()) : null;

$today = date('Y-m-d');
$slides = array();
foreach ($HOME_SLIDES as $s) {
    if (empty($s['until']) || $s['until'] >= $today) {
        $slides[] = $s;
    }
}

function home_cat_list($cats, $limit = 5)
{
    $withItems = array_values(array_filter($cats, function ($c) { return $c['count'] > 0; }));
    $list = array_slice($withItems ? $withItems : $cats, 0, $limit);
    echo '<ul class="cat-list">';
    foreach ($list as $c) {
        echo '<li><a class="cat-row" href="' . e(url(category_url($c))) . '">';
        echo '<span class="cat-thumb">';
        if (!empty($c['profile_image'])) {
            echo '<img src="' . e(media($c['profile_image'])) . '" alt="" loading="lazy" onerror="this.remove()">';
        }
        echo '</span><div><h3>' . e($c['name']) . '</h3><p>' . e(excerpt($c['long_description'], 95)) . '</p>';
        echo '<span class="count">' . e(plural($c['count'], 'listing')) . '</span></div></a></li>';
    }
    echo '</ul>';
}

page_start(array(
    'title' => 'The Hashir – Find Mosques, Prayer Times & Muslim Community Services',
    'description' => 'Discover ' . number_format($mosqueCount) . '+ mosques across Canada and the US. See daily prayer and Jumu\'ah times, Islamic programs, Nikah, counselling and funeral services, charities and events near you.',
    'canonical' => '/',
    'jsonld' => array(array(
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => SITE_URL . '/',
        'potentialAction' => array('@type' => 'SearchAction', 'target' => SITE_URL . '/search?q={search_term_string}', 'query-input' => 'required name=search_term_string'),
    )),
));
?>
<section class="home-hero">
  <div class="container-xl">
    <h1><?= e(setting('hero_title')) ?></h1>
    <p class="lead"><?= e(setting('hero_subtitle')) ?></p>

    <form class="search-card" action="<?= e(url('/search')) ?>" method="get" role="search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <label for="heroSearch" class="visually-hidden">Search by keyword</label>
      <input id="heroSearch" name="q" type="search" placeholder="Search mosques, cities, programs or services…" autocomplete="off">
      <button class="btn btn-primary px-4" type="submit">Search</button>
    </form>

    <div class="filter-card">
      <div>
        <label for="goPrograms">Programs</label>
        <select id="goPrograms" class="form-select" data-go-select>
          <option value="">Programs</option>
          <?php foreach ($programs as $c): ?><option value="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?> (<?= (int)$c['count'] ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="goServices">Services</label>
        <select id="goServices" class="form-select" data-go-select>
          <option value="">Services</option>
          <?php foreach ($services as $c): ?><option value="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?> (<?= (int)$c['count'] ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="goEvents">Events</label>
        <select id="goEvents" class="form-select" data-go-select>
          <option value="">Events</option>
          <?php foreach ($events as $c): ?><option value="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="goCity">Mosques by city</label>
        <?php city_select('city', null, 'Mosques by City', 'goCity'); ?>
      </div>
    </div>

    <div class="quick-links">
      <a href="<?= e(url('/prayer-times')) ?>"><i class="bi bi-clock" aria-hidden="true"></i>Prayer times</a>
      <?php if ($visitorCity): ?>
        <a href="<?= e(url('/mosques?city=' . (int)$visitorCity['city_id'])) ?>"><i class="bi bi-geo-alt" aria-hidden="true"></i>Mosques in <?= e($visitorCity['city_title']) ?></a>
      <?php else: ?>
        <a href="#" data-bs-toggle="modal" data-bs-target="#cityModal"><i class="bi bi-geo-alt" aria-hidden="true"></i>Mosques near me</a>
      <?php endif; ?>
      <a href="<?= e(url('/services/Nikah-Services/10')) ?>"><i class="bi bi-heart" aria-hidden="true"></i>Nikah services</a>
      <a href="<?= e(url('/services/Funeral-Services/13')) ?>"><i class="bi bi-flower1" aria-hidden="true"></i>Funeral services</a>
    </div>

    <div class="floating-social d-none d-lg-flex" aria-label="The Hashir on social media">
      <?php foreach ($SOCIAL_LINKS as $k => $u): ?>
      <a class="social-circle" href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="The Hashir on <?= e(ucfirst($k)) ?>"><i class="bi <?= e(social_icon_class($k)) ?>" aria-hidden="true"></i></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($slides && home_section_on('slider')): ?>
<section class="section-sm pt-0" aria-label="Featured">
  <div class="container-xl">
    <div id="homeSlider" class="carousel slide slider" data-bs-ride="carousel" data-bs-interval="7000">
      <div class="carousel-inner">
        <?php foreach ($slides as $i => $s): ?>
        <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
          <img src="<?= e(media($s['image'])) ?>" alt="" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
          <div class="slide-caption">
            <h2><?= e($s['title']) ?></h2>
            <a class="btn btn-primary btn-sm px-3" href="<?= e(url($s['link'])) ?>"><?= e($s['button']) ?></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($slides) > 1): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#homeSlider" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous slide</span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#homeSlider" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next slide</span></button>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (home_section_on('cities')): ?>
<section class="section-sm" aria-labelledby="h-cities">
  <div class="container-xl">
    <div class="section-head">
      <h2 class="section-title" id="h-cities">Browse mosques by city</h2>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/mosques')) ?>">All <?= number_format($mosqueCount) ?> mosques</a>
    </div>
    <div class="city-chips">
      <?php foreach ($cities as $c): ?>
      <a class="city-chip" href="<?= e(url('/mosques?city=' . (int)$c['city_id'])) ?>"><?= e($c['city_title']) ?><span class="n"><?= (int)$c['n'] ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php endif; ?>

<?php if ($featuredMosques && home_section_on('featured_mosques')): ?>
<section class="section-sm" aria-labelledby="h-featured">
  <div class="container-xl">
    <div class="section-head">
      <div><span class="eyebrow">Featured</span><h2 class="section-title mt-1" id="h-featured">Mosques to discover</h2></div>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/mosques')) ?>">Browse all</a>
    </div>
    <div class="card-grid"><?php foreach ($featuredMosques as $m) { venue_card($m, $fmBadges); } ?></div>
  </div>
</section>
<?php endif; ?>

<?php if (home_section_on('nearby')): ?>
<section class="section-sm" aria-labelledby="h-near">
  <div class="container-xl">
    <div class="near-band" data-home-nearby data-api="<?= e(url('/api/map')) ?>">
      <div class="near-band-text">
        <span class="eyebrow">Near you</span>
        <h2 class="section-title mt-1" id="h-near">Mosques around you</h2>
        <p class="mb-3">Share your location to see the closest mosques with a confirmed address, or pick your city.</p>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary" data-near-home><i class="bi bi-crosshair me-2" aria-hidden="true"></i>Use my location</button>
          <a class="btn btn-outline-primary" href="<?= e(url('/mosque-map')) ?>"><i class="bi bi-map me-2" aria-hidden="true"></i>Open the map</a>
          <?php if ($visitorCity): ?><a class="btn btn-soft" href="<?= e(url('/mosques?city=' . (int)$visitorCity['city_id'])) ?>">Mosques in <?= e($visitorCity['city_title']) ?></a><?php endif; ?>
        </div>
        <p class="small text-muted mt-2 mb-0" data-near-home-status role="status"></p>
      </div>
      <ol class="near-home-list list-unstyled" data-near-home-list></ol>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (home_section_on('platform')): ?>
<section class="section" aria-labelledby="h-platform">
  <div class="container-xl">
    <div class="row align-items-center g-5">
      <div class="col-md-5 text-center">
        <img class="feature-device" src="<?= e(media('images/thumbnails/hashir-gif.png')) ?>" alt="The Hashir app on a phone showing the mosque search" loading="lazy" onerror="this.remove()">
      </div>
      <div class="col-md-7">
        <h2 class="section-title" id="h-platform">A modern platform to stay <span class="accent">connected locally</span></h2>
        <ul class="tick-list">
          <li>Find mosques &amp; Muslim organizations near you</li>
          <li>See daily prayer and Jumu'ah times from each mosque</li>
          <li>Discover programs, services and events in your city</li>
          <li>Built for both the community &amp; mosques</li>
        </ul>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="<?= e(url('/about')) ?>">More about Hashir</a>
          <a class="btn btn-outline-primary" href="<?= e(url('/solution-mosque')) ?>">For mosques</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php endif; ?>

<?php if (home_section_on('programs')): ?>
<section class="section pt-0" aria-labelledby="h-learning">
  <div class="container-xl">
    <div class="row g-4 g-lg-5 align-items-start">
      <div class="col-lg-6">
        <div class="collage"><img src="<?= e(media('images/thumbnails/program-main-banner.webp')) ?>" alt="Quran classes, study circles and youth sports at local mosques" loading="lazy" onerror="this.remove()"></div>
        <h2 class="section-title h3 mt-4" id="h-learning">Community learning &amp; development</h2>
        <p>Hashir brings program information together so individuals and families can find Quran, Islamic studies, youth and sisters' programs near them.</p>
        <a class="btn btn-primary" href="<?= e(url('/program-catalog')) ?>">Explore programs</a>
      </div>
      <div class="col-lg-6"><?php home_cat_list($programs); ?></div>
    </div>
  </div>
</section>

<?php endif; ?>

<?php if (home_section_on('services')): ?>
<section class="section pt-0" aria-labelledby="h-serving">
  <div class="container-xl">
    <div class="row g-4 g-lg-5 align-items-start">
      <div class="col-lg-6">
        <div class="collage"><img src="<?= e(media('images/thumbnails/services-main-banner.webp')) ?>" alt="Food bank volunteers, Nikah and community prayer" loading="lazy" onerror="this.remove()"></div>
        <h2 class="section-title h3 mt-4" id="h-serving">Serving the community</h2>
        <p>Find Nikah, counselling, funeral, food bank and senior services offered by mosques and Muslim organizations.</p>
        <a class="btn btn-primary" href="<?= e(url('/services-catalog')) ?>">Explore services</a>
      </div>
      <div class="col-lg-6"><?php home_cat_list($services); ?></div>
    </div>
  </div>
</section>

<?php endif; ?>

<?php if ($upcoming && home_section_on('events')): ?>
<section class="section-sm pt-0" aria-labelledby="h-events">
  <div class="container-xl">
    <div class="section-head">
      <div><span class="eyebrow">What's on</span><h2 class="section-title mt-1" id="h-events">Upcoming events</h2></div>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/events-catalog')) ?>">All events</a>
    </div>
    <div class="card-grid"><?php foreach ($upcoming as $d) { detail_card($d); } ?></div>
  </div>
</section>
<?php endif; ?>

<?php if (home_section_on('organizations')): ?>
<section class="section pt-0" aria-labelledby="h-tiles">
  <div class="container-xl">
    <h2 class="visually-hidden" id="h-tiles">Explore mosques and organizations</h2>
    <div class="row g-3">
      <div class="col-lg-6">
        <a class="tile tile-tall h-100" href="<?= e(url('/mosques')) ?>">
          <?php if ($mosqueRole && $mosqueRole['profile_image']): ?><img src="<?= e(media($mosqueRole['profile_image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
          <div class="tile-body"><h3>Explore mosques</h3><p><?= number_format($mosqueCount) ?> mosques listed — find one to pray, learn, seek help or connect with others.</p></div>
        </a>
      </div>
      <div class="col-lg-6">
        <div class="tile-grid">
          <?php foreach (array(4 => 'Zakat and Sadaqah collection for those in need.', 7 => 'Halal food support for local families.', 3 => 'Quran, Arabic and full-time Islamic schools.', 5 => 'Dawah, open houses and interfaith outreach.') as $rid => $blurb):
              if (!isset($rolesById[$rid])) { continue; } $r = $rolesById[$rid]; ?>
          <a class="tile" href="<?= e(url(org_role_url($r))) ?>">
            <?php if ($r['profile_image']): ?><img src="<?= e(media($r['profile_image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
            <div class="tile-body"><h3><?= e($r['role_title']) ?></h3><p><?= e($blurb) ?> <?= $r['count'] ? '(' . (int)$r['count'] . ')' : '' ?></p></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php endif; ?>

<?php if ($featuredOrgs && home_section_on('featured_orgs')): ?>
<section class="section-sm pt-0" aria-labelledby="h-orgs">
  <div class="container-xl">
    <div class="section-head">
      <div><span class="eyebrow">Community</span><h2 class="section-title mt-1" id="h-orgs">Featured organizations</h2></div>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/organizations-catalog')) ?>">All organizations</a>
    </div>
    <div class="card-grid"><?php foreach ($featuredOrgs as $o) { venue_card($o); } ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($blogs && home_section_on('blogs')): ?>
<section class="section pt-0" aria-labelledby="h-guides">
  <div class="container-xl">
    <div class="section-head">
      <h2 class="section-title" id="h-guides">City guides</h2>
      <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/blogs')) ?>">All guides</a>
    </div>
    <div class="row g-4">
      <?php foreach ($blogs as $b): ?>
      <div class="col-md-4"><?php blog_card($b); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php if (home_section_on('newsletter')): ?>
<section class="section-sm pt-0" aria-labelledby="h-news">
  <div class="container-xl">
    <div class="newsletter">
      <div><h2 id="h-news">Stay connected with your community</h2><p class="mb-0">Get new events, programs and Ramadan timetables from mosques near you. No spam.</p></div>
      <form class="newsletter-form" method="post" action="<?= e(url('/api/newsletter')) ?>" data-newsletter novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="page" value="/">
        <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
        <label class="visually-hidden" for="nlEmail">Email address</label>
        <input id="nlEmail" type="email" name="email" class="form-control" placeholder="you@example.com" required autocomplete="email">
        <button class="btn btn-light" type="submit">Subscribe</button>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>
<?php page_end(); ?>
