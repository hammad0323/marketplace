<?php
/** Homepage — sections are ordered, toggled and configured in Admin → Homepage. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$sections = array_filter(homepage_sections(), fn($s) => $s['is_visible'] || is_preview() && !empty($_GET['show_hidden']));
$slides = db_all('SELECT * FROM banner_slides WHERE is_active = 1 ORDER BY sort_order, id');
$heroVisible = (bool)array_filter($sections, fn($s) => $s['section_key'] === 'hero');
$GLOBALS['hero_has_slides'] = $heroVisible && $slides;

seo_set(['title' => null, 'description' => setting('seo_default_description')]);
seo_schema(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('site_name'), 'url' => abs_url(),
    'potentialAction' => ['@type' => 'SearchAction', 'target' => abs_url('search') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']]);

$bodyClass = 'page-home';
require ROOT_PATH . '/templates/header.php';

foreach ($sections as $sec) {
    $s = $sec['s'];
    $sid = (int)$sec['id'];
    switch ($sec['section_key']) {

    // ------------------------------------------------------------- Hero
    case 'hero':
        if (!$slides) break; ?>
        <section class="hero hero-<?= e($s['transition']) ?>" aria-label="Featured">
          <div class="swiper hero-swiper" data-autoplay="<?= $s['autoplay'] ? (int)$s['duration'] : 0 ?>" data-effect="<?= e($s['transition']) ?>">
            <div class="swiper-wrapper">
              <?php foreach ($slides as $i => $sl):
                $pos = ['top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end'][$sl['text_position']];
                $side = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'][$sl['content_side']];
                $op = max(0, min(90, (int)$sl['overlay_opacity'])) / 100; ?>
                <div class="swiper-slide hero-slide" style="--h-d:<?= (int)$sl['height_desktop'] ?>px;--h-m:<?= (int)$sl['height_mobile'] ?>px;color:<?= e($sl['text_color']) ?>">
                  <picture class="hero-media<?= $sl['parallax'] ? ' is-parallax' : '' ?>">
                    <?php if ($sl['mobile_image']): ?><source media="(max-width: 767px)" srcset="<?= e(img_url($sl['mobile_image'])) ?>"><?php endif; ?>
                    <img src="<?= e(img_url($sl['desktop_image'])) ?>" alt="<?= e($sl['image_alt'] ?: $sl['heading']) ?>" style="object-position:<?= e($sl['bg_position']) ?>;object-fit:<?= e($sl['bg_size'] === 'auto' ? 'none' : $sl['bg_size']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                  </picture>
                  <div class="hero-overlay" style="background:<?= e($sl['overlay_color']) ?>;opacity:<?= $op ?>"></div>
                  <div class="container-eb hero-content-wrap" style="align-items:<?= $pos ?>;justify-content:<?= $side ?>">
                    <div class="hero-content text-<?= e($sl['text_align']) ?>">
                      <?php if ($sl['subheading']): ?><span class="hero-eyebrow"><?= e($sl['subheading']) ?></span><?php endif; ?>
                      <?php if ($sl['heading']): ?><<?= $i === 0 ? 'h1' : 'h2' ?> class="hero-title"><?= e($sl['heading']) ?></<?= $i === 0 ? 'h1' : 'h2' ?>><?php endif; ?>
                      <?php if ($sl['description']): ?><p class="hero-text"><?= e($sl['description']) ?></p><?php endif; ?>
                      <div class="hero-btns">
                        <?php if ($sl['btn1_text']): ?><a href="<?= e(url($sl['btn1_url'] ?: '/shop')) ?>" class="btn btn-eb btn-light-eb"><?= e($sl['btn1_text']) ?></a><?php endif; ?>
                        <?php if ($sl['btn2_text']): ?><a href="<?= e(url($sl['btn2_url'] ?: '/shop')) ?>" class="btn btn-eb btn-ghost-light"><?= e($sl['btn2_text']) ?></a><?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($s['show_dots'] && count($slides) > 1): ?><div class="swiper-pagination hero-dots"></div><?php endif; ?>
            <?php if ($s['show_arrows'] && count($slides) > 1): ?>
              <button class="hero-nav hero-prev" type="button" aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
              <button class="hero-nav hero-next" type="button" aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
          </div>
          <?php if ($s['scroll_cue']): ?><a href="#hp-after-hero" class="scroll-cue" aria-label="Scroll to content"><span></span></a><?php endif; ?>
        </section>
        <div id="hp-after-hero"></div>
        <?php break;

    // ------------------------------------------------------------- Categories
    case 'categories':
        $limit = clamp_int($s['limit'], 1, 24);
        if ($s['source'] === 'manual') {
            $ids = homepage_section_item_ids($sid, 'category');
            $cats = array_values(array_filter(array_map(fn($id) => categories_all()[$id] ?? null, $ids)));
        } elseif ($s['source'] === 'top') {
            $cats = array_values(array_filter(categories_all(), fn($c) => $c['parent_id'] === null));
        } else {
            $cats = array_values(array_filter(categories_all(), fn($c) => $c['show_on_home']));
        }
        $cats = array_slice($cats, 0, $limit);
        if (!$cats) break;
        $children = [];
        foreach (categories_all() as $c) if ($c['parent_id'] !== null) $children[(int)$c['parent_id']][] = $c; ?>
        <section <?= hp_section_attrs($s, 'hp-categories') ?>>
          <div class="<?= hp_container_class($s) ?>">
            <?= hp_heading($s) ?>
            <?php if ($s['layout'] === 'carousel'): ?>
              <div class="swiper eb-carousel" data-per-view="3"><div class="swiper-wrapper">
            <?php else: ?>
              <div class="cat-grid cat-<?= e($s['layout']) ?> cat-count-<?= count($cats) ?>">
            <?php endif; ?>
              <?php foreach ($cats as $i => $c): ?>
                <<?= $s['layout'] === 'carousel' ? 'div class="swiper-slide"' : 'div class="cat-cell"' ?> data-reveal style="--d:<?= $i * 80 ?>ms">
                  <a class="cat-card" href="<?= e(category_url($c)) ?>">
                    <span class="cat-img"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"></span>
                    <span class="cat-info">
                      <span class="cat-name"><?= e($c['name']) ?></span>
                      <span class="cat-link">Discover <i class="bi bi-arrow-right"></i></span>
                    </span>
                  </a>
                  <?php if ($s['show_children'] && !empty($children[(int)$c['id']])): ?>
                    <div class="cat-children">
                      <?php foreach (array_slice($children[(int)$c['id']], 0, 4) as $ch): ?><a href="<?= e(category_url($ch)) ?>"><?= e($ch['name']) ?></a><?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?= $s['layout'] === 'carousel' ? '</div></div>' : '</div>' ?>
            <?php if ($s['cta_text']): ?><div class="text-center mt-5" data-reveal><a href="<?= e(url($s['cta_url'] ?: '/shop')) ?>" class="btn btn-eb btn-outline-eb"><?= e($s['cta_text']) ?></a></div><?php endif; ?>
          </div>
        </section>
        <?php break;

    // ------------------------------------------------------------- New arrivals & best sellers
    case 'new_arrivals':
    case 'best_sellers':
        $limit = clamp_int($s['limit'], 1, 24);
        if ($s['source'] === 'manual') {
            [$products] = products_query(['ids' => homepage_section_item_ids($sid, 'product'), 'sort' => 'manual', 'limit' => $limit]);
        } elseif ($sec['section_key'] === 'new_arrivals') {
            [$products] = $s['source'] === 'flag' ? products_query(['flag' => 'new_arrival', 'sort' => 'newest', 'limit' => $limit]) : products_query(['sort' => 'newest', 'limit' => $limit]);
        } elseif ($s['source'] === 'flag') {
            [$products] = products_query(['flag' => 'best_seller', 'sort' => 'featured', 'limit' => $limit]);
        } else {
            $ids = best_seller_ids($limit, (int)$s['days']);
            [$products] = $ids ? products_query(['ids' => $ids, 'sort' => 'manual', 'limit' => $limit]) : [[]];
            if (count($products) < $limit) {
                [$more] = products_query(['flag' => 'best_seller', 'exclude_ids' => $ids, 'limit' => $limit - count($products)]);
                $products = array_merge($products, $more);
            }
        }
        if (!$products) break; ?>
        <section <?= hp_section_attrs($s, 'hp-products hp-' . $sec['section_key']) ?>>
          <div class="<?= hp_container_class($s) ?>">
            <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 section-head-row">
              <?= hp_heading(array_merge($s, ['align' => 'left'])) ?>
              <div class="carousel-controls" data-reveal>
                <button class="car-btn car-prev" type="button" aria-label="Previous"><i class="bi bi-arrow-left"></i></button>
                <button class="car-btn car-next" type="button" aria-label="Next"><i class="bi bi-arrow-right"></i></button>
              </div>
            </div>
            <div class="swiper eb-carousel product-carousel" data-reveal>
              <div class="swiper-wrapper">
                <?php foreach ($products as $p): ?><div class="swiper-slide"><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?>
              </div>
              <div class="swiper-scrollbar"></div>
            </div>
            <?php if ($s['cta_text']): ?><div class="text-center mt-5"><a href="<?= e(url($s['cta_url'] ?: '/shop')) ?>" class="btn btn-eb btn-outline-eb"><?= e($s['cta_text']) ?></a></div><?php endif; ?>
          </div>
        </section>
        <?php break;

    // ------------------------------------------------------------- Handcrafted
    case 'handcrafted':
        $imgs = [];
        for ($i = 1; $i <= 6; $i++) if (!empty($s['image_' . $i])) $imgs[] = [$s['image_' . $i], $s['caption_' . $i]]; ?>
        <section <?= hp_section_attrs($s, 'hp-handcrafted') ?>>
          <div class="<?= hp_container_class($s) ?>">
            <div class="row g-5 align-items-center">
              <div class="col-lg-4" data-reveal>
                <span class="eyebrow"><?= e($s['eyebrow']) ?></span>
                <h2 class="section-title"><?= e($s['heading']) ?></h2>
                <div class="hc-body"><?= nl2p($s['body']) ?></div>
                <span class="ornament" aria-hidden="true"></span>
                <?php if ($s['cta_text']): ?><a href="<?= e(url($s['cta_url'] ?: '/shop')) ?>" class="btn btn-eb btn-primary-eb mt-3"><?= e($s['cta_text']) ?></a><?php endif; ?>
              </div>
              <div class="col-lg-8">
                <div class="hc-mosaic">
                  <?php foreach ($imgs as $i => [$img, $cap]): ?>
                    <figure class="hc-tile hc-tile-<?= $i + 1 ?>" data-reveal style="--d:<?= $i * 90 ?>ms">
                      <img src="<?= e(img_url($img)) ?>" alt="<?= e($cap ?: 'Handcrafted detail') ?>" loading="lazy">
                      <?php if ($cap): ?><figcaption><?= e($cap) ?></figcaption><?php endif; ?>
                    </figure>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php break;

    // ------------------------------------------------------------- Craft collections
    case 'craft_collections':
        $limit = clamp_int($s['limit'], 1, 24);
        if ($s['source'] === 'manual') {
            $ids = homepage_section_item_ids($sid, 'collection');
            $cols = $ids ? db_all("SELECT * FROM collections WHERE status = 'active' AND id IN (" . db_in($ids) . ') ORDER BY FIELD(id,' . implode(',', $ids) . ')', $ids) : [];
        } else {
            $cols = db_all("SELECT * FROM collections WHERE status = 'active' AND show_on_home = 1 ORDER BY sort_order, id LIMIT $limit");
        }
        $cols = array_slice($cols, 0, $limit);
        if (!$cols) break; ?>
        <section <?= hp_section_attrs($s, 'hp-collections') ?>>
          <div class="<?= hp_container_class($s) ?>">
            <?= hp_heading($s) ?>
            <?php if ($s['layout'] === 'carousel'): ?><div class="swiper eb-carousel" data-per-view="3"><div class="swiper-wrapper"><?php else: ?><div class="row g-4"><?php endif; ?>
              <?php foreach ($cols as $i => $c): ?>
                <div class="<?= $s['layout'] === 'carousel' ? 'swiper-slide' : 'col-6 col-lg-4' ?>" data-reveal style="--d:<?= $i * 80 ?>ms">
                  <a class="craft-card" href="<?= e(collection_url($c)) ?>">
                    <span class="craft-img"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"></span>
                    <span class="craft-body">
                      <span class="craft-title"><?= e($c['name']) ?></span>
                      <?php if ($c['description']): ?><span class="craft-desc"><?= e(str_limit($c['description'], 110)) ?></span><?php endif; ?>
                      <span class="craft-link">Explore <i class="bi bi-arrow-right"></i></span>
                    </span>
                  </a>
                </div>
              <?php endforeach; ?>
            <?= $s['layout'] === 'carousel' ? '</div></div>' : '</div>' ?>
          </div>
        </section>
        <?php break;

    // ------------------------------------------------------------- Story
    case 'story':
        $video = trim((string)$s['video_url']);
        $yt = preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})#', $video, $m) ? $m[1] : null; ?>
        <section <?= hp_section_attrs($s, 'hp-story') ?>>
          <div class="<?= hp_container_class($s) ?>">
            <div class="row g-5 align-items-center<?= $s['layout'] === 'right' ? ' flex-lg-row-reverse' : '' ?>">
              <div class="col-lg-6">
                <div class="story-media" data-reveal>
                  <?php if ($yt): ?>
                    <div class="ratio ratio-4x5 story-video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>?rel=0" title="Ebaya story" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>
                  <?php elseif ($video && preg_match('#\.mp4(\?|$)#i', $video)): ?>
                    <video class="story-main" src="<?= e(url($video)) ?>" <?= $s['image'] ? 'poster="' . e(img_url($s['image'])) . '"' : '' ?> muted loop playsinline autoplay></video>
                  <?php else: ?>
                    <img class="story-main parallax-img" src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['heading']) ?>" loading="lazy">
                  <?php endif; ?>
                  <?php if ($s['image_2']): ?><img class="story-inset" src="<?= e(img_url($s['image_2'])) ?>" alt="" loading="lazy"><?php endif; ?>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="story-copy" data-reveal>
                  <span class="eyebrow"><?= e($s['eyebrow']) ?></span>
                  <h2 class="section-title"><?= e($s['heading']) ?></h2>
                  <div class="story-intro"><?= nl2p($s['intro']) ?></div>
                  <div class="row g-4 mt-1">
                    <?php if ($s['philosophy']): ?><div class="col-md-6"><h3 class="story-sub"><?= e($s['philosophy_heading']) ?></h3><?= nl2p($s['philosophy']) ?></div><?php endif; ?>
                    <?php if ($s['inspiration']): ?><div class="col-md-6"><h3 class="story-sub"><?= e($s['inspiration_heading']) ?></h3><?= nl2p($s['inspiration']) ?></div><?php endif; ?>
                  </div>
                  <?php if ($s['image_3']): ?><img class="story-extra" src="<?= e(img_url($s['image_3'])) ?>" alt="" loading="lazy"><?php endif; ?>
                  <?php if ($s['cta_text']): ?><a href="<?= e(url($s['cta_url'] ?: '/about-ebaya')) ?>" class="btn btn-eb btn-outline-eb mt-4"><?= e($s['cta_text']) ?></a><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php break;

    // ------------------------------------------------------------- Testimonials & newsletter
    case 'testimonials_newsletter':
        $testimonials = $s['show_testimonials'] ? db_all("SELECT t.*, p.name AS product_name, p.slug AS product_slug FROM testimonials t LEFT JOIN products p ON p.id = t.product_id WHERE t.status = 'active' ORDER BY t.sort_order, t.id LIMIT 12") : []; ?>
        <?php if ($testimonials): ?>
        <section <?= hp_section_attrs($s, 'hp-testimonials') ?>>
          <div class="<?= hp_container_class($s) ?>">
            <?= hp_heading($s, 't_eyebrow', 't_heading', '_none') ?>
            <div class="swiper testimonial-swiper" data-reveal>
              <div class="swiper-wrapper">
                <?php foreach ($testimonials as $t): ?>
                  <div class="swiper-slide">
                    <figure class="testimonial">
                      <?php if ($s['show_ratings'] && $t['rating']): ?><div class="stars" aria-label="<?= (int)$t['rating'] ?> out of 5"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$t['rating']) . str_repeat('<i class="bi bi-star"></i>', 5 - (int)$t['rating']) ?></div><?php endif; ?>
                      <blockquote>“<?= e($t['quote']) ?>”</blockquote>
                      <figcaption><strong><?= e($t['name']) ?></strong><?= $t['location'] ? ' · ' . e($t['location']) : '' ?>
                        <?php if ($t['product_name']): ?><br><a href="<?= e(url('product/' . $t['product_slug'])) ?>"><?= e($t['product_name']) ?></a><?php endif; ?></figcaption>
                    </figure>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="swiper-pagination"></div>
            </div>
          </div>
        </section>
        <?php endif; ?>
        <?php if ($s['show_newsletter']): ?>
        <section class="hp-newsletter<?= $s['n_image'] ? ' has-bg' : '' ?>" style="background-color:<?= e($s['n_bg_color']) ?>;color:<?= e($s['n_text_color']) ?><?= $s['n_image'] ? ';background-image:url(' . e(img_url($s['n_image'])) . ')' : '' ?>" data-anim="<?= e(setting('anim_style')) ?>">
          <div class="container-eb">
            <div class="newsletter-box" data-reveal>
              <span class="ornament ornament-light" aria-hidden="true"></span>
              <h2 class="section-title"><?= e($s['n_heading']) ?></h2>
              <p><?= e($s['n_text']) ?></p>
              <form class="newsletter-form" data-newsletter novalidate>
                <div class="nl-row">
                  <label for="nlEmail" class="visually-hidden">Email address</label>
                  <input type="email" id="nlEmail" name="email" placeholder="Your email address" required maxlength="190">
                  <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                  <button type="submit" class="btn btn-eb btn-light-eb">Subscribe</button>
                </div>
                <label class="nl-consent"><input type="checkbox" name="consent" value="1" required> <?= e($s['n_consent']) ?></label>
              </form>
            </div>
          </div>
        </section>
        <?php endif; ?>
        <?php break;
    }
}

require ROOT_PATH . '/templates/footer.php';
