<?php
function render_hero($mysqli, $heroStyle, $banners) {
    $fallbackImg = BASE_URL . '/assets/img/banner-placeholder.svg';
    $tagline = get_setting('store_tagline', 'Premium Pakistani Fashion');
    $intro = 'Discover our latest collection of unstitched, ready-to-wear and festive fashion.';

    if ($heroStyle === 'collage') {
        $tiles = $banners;
        while (count($tiles) < 4) $tiles[] = null;
        $tiles = array_slice($tiles, 0, 4);
        ?>
        <section class="section-tight">
          <div class="container">
            <div class="row g-3">
              <?php foreach ($tiles as $i => $b): ?>
                <div class="col-6 col-md-3" data-aos="fade-up">
                  <a href="<?= $b ? e(resolve_link($b['button_url'])) : url('shop') ?>" class="promo-banner-img d-block" style="height:320px">
                    <img src="<?= $b ? e(BASE_URL . '/' . $b['image_desktop']) : e($fallbackImg) ?>" alt="<?= e($b['title'] ?? $tagline) ?>">
                    <?php if ($b && ($b['title'] || $b['subtitle'])): ?>
                    <div class="promo-caption">
                      <?php if ($b['title']): ?><h3 class="font-serif h6"><?= e($b['title']) ?></h3><?php endif; ?>
                      <?php if ($b['subtitle']): ?><p class="small mb-0"><?= e($b['subtitle']) ?></p><?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php
        return;
    }

    if ($heroStyle === 'centered') {
        $b = $banners[0] ?? null;
        $bg = $b ? BASE_URL . '/' . $b['image_desktop'] : null;
        ?>
        <section class="hero-centered" <?= $bg ? 'style="background-image:url(\'' . e($bg) . '\')"' : '' ?>>
          <div class="container text-center" data-aos="zoom-in">
            <h1><?= e($b['title'] ?? $tagline) ?></h1>
            <p><?= e($b['subtitle'] ?? $intro) ?></p>
            <a href="<?= $b ? e(resolve_link($b['button_url'])) : url('shop') ?>" class="btn-brand"><?= e($b['button_text'] ?? 'Shop Now') ?></a>
          </div>
        </section>
        <?php
        return;
    }

    if ($heroStyle === 'split') {
        $b = $banners[0] ?? null;
        $img = $b ? BASE_URL . '/' . $b['image_desktop'] : $fallbackImg;
        ?>
        <section class="hero-split">
          <div class="container">
            <div class="row align-items-center g-0">
              <div class="col-lg-6 order-2 order-lg-1" data-aos="fade-right">
                <div class="hero-split-text">
                  <h1><?= e($b['title'] ?? $tagline) ?></h1>
                  <p><?= e($b['subtitle'] ?? $intro) ?></p>
                  <a href="<?= $b ? e(resolve_link($b['button_url'])) : url('shop') ?>" class="btn-brand"><?= e($b['button_text'] ?? 'Shop Now') ?></a>
                </div>
              </div>
              <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-left">
                <div class="hero-split-img"><img src="<?= e($img) ?>" alt="<?= e($b['title'] ?? $tagline) ?>"></div>
              </div>
            </div>
          </div>
        </section>
        <?php
        return;
    }

    // default: slider
    if ($banners) {
        ?>
        <section class="hero-slider">
          <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" <?= count($banners) > 1 ? 'data-bs-interval="5500"' : '' ?>>
            <?php if (count($banners) > 1): ?>
            <div class="carousel-indicators">
              <?php foreach ($banners as $i => $b): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i===0?'active':'' ?>"></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="carousel-inner">
              <?php foreach ($banners as $i => $b): ?>
                <div class="carousel-item <?= $i===0?'active':'' ?>">
                  <div class="hero-slide" style="background-image:url('<?= e(BASE_URL . '/' . $b['image_desktop']) ?>')">
                    <div class="container">
                      <div class="hero-content" data-aos="fade-right">
                        <?php if ($b['title']): ?><h1><?= e($b['title']) ?></h1><?php endif; ?>
                        <?php if ($b['subtitle']): ?><p><?= e($b['subtitle']) ?></p><?php endif; ?>
                        <?php if ($b['button_text']): ?><a href="<?= e(resolve_link($b['button_url'])) ?>" class="btn-brand"><?= e($b['button_text']) ?></a><?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (count($banners) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
            <?php endif; ?>
          </div>
        </section>
        <?php
    } else {
        ?>
        <section class="hero-slide" style="background-image:url('<?= e($fallbackImg) ?>')">
          <div class="container">
            <div class="hero-content" data-aos="fade-right">
              <h1><?= e($tagline) ?></h1>
              <p><?= e($intro) ?></p>
              <a href="<?= url('shop') ?>" class="btn-brand">Shop Now</a>
            </div>
          </div>
        </section>
        <?php
    }
}

function get_section_items($mysqli, $sectionId) {
    $res = mysqli_query($mysqli, "SELECT i.*, p.slug AS product_slug FROM homepage_section_items i LEFT JOIN products p ON p.id = i.product_id WHERE i.section_id = $sectionId AND i.status = 'active' ORDER BY i.sort_order");
    $items = [];
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
    return $items;
}

function render_homepage_section($mysqli, $section) {
    $layout = $section['layout'] ?: 'grid-4';
    $gridClass = ['grid-3' => 'row-cols-2 row-cols-md-3', 'grid-4' => 'row-cols-2 row-cols-md-4'][$layout] ?? 'row-cols-2 row-cols-md-4';

    switch ($section['section_type']) {
        case 'categories':
            $items = get_section_items($mysqli, $section['id']);
            if (!$items) {
                $res = mysqli_query($mysqli, "SELECT * FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY sort_order LIMIT 6");
                $cats = [];
                while ($c = mysqli_fetch_assoc($res)) $cats[] = ['title' => $c['name'], 'image' => $c['image'], 'link' => category_url($c['slug'])];
                $items = $cats;
            }
            ?>
            <section class="section">
              <div class="container">
                <?php if ($section['title']): ?>
                <div class="section-head" data-aos="fade-up"><h2><?= e($section['title']) ?></h2><?php if ($section['subtitle']): ?><p><?= e($section['subtitle']) ?></p><?php endif; ?></div>
                <?php endif; ?>
                <div class="row <?= e($gridClass) ?> g-3 g-md-4">
                  <?php foreach ($items as $it): ?>
                    <div class="col" data-aos="fade-up">
                      <a href="<?= e(resolve_link($it['link'])) ?>" class="cat-tile d-block">
                        <img src="<?= e(BASE_URL . '/' . ($it['image'] ?: 'assets/img/placeholder.svg')) ?>" alt="<?= e($it['title']) ?>">
                        <span class="cat-tile-label"><?= e($it['title']) ?></span>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'products':
            $items = [];
            if ($section['product_filter'] === 'manual') {
                foreach (get_section_items($mysqli, $section['id']) as $it) {
                    if ($it['product_id']) {
                        $p = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM products WHERE id = " . (int)$it['product_id']));
                        if ($p) $items[] = $p;
                    }
                }
            } else {
                $items = fetch_products_by_filter($mysqli, $section['product_filter'], $section['category_id'], $layout === 'grid-3' ? 6 : 8);
            }
            if (!$items) break;
            ?>
            <section class="section">
              <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                  <div data-aos="fade-up">
                    <?php if ($section['title']): ?><h2 class="mb-1"><?= e($section['title']) ?></h2><?php endif; ?>
                    <?php if ($section['subtitle']): ?><p class="text-muted mb-0"><?= e($section['subtitle']) ?></p><?php endif; ?>
                  </div>
                  <?php if ($section['button_text']): ?><a href="<?= e(resolve_link($section['button_url'])) ?>" class="btn-outline-brand"><?= e($section['button_text']) ?></a><?php endif; ?>
                </div>
                <?php if ($layout === 'slider'): ?>
                <div class="d-flex gap-3 gap-md-4 overflow-auto pb-2" style="scroll-snap-type:x mandatory;">
                  <?php foreach ($items as $p): ?>
                    <div style="min-width:230px;max-width:230px;scroll-snap-align:start;" data-aos="fade-up"><?php render_product_card($mysqli, $p); ?></div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="row <?= e($gridClass) ?> g-3 g-md-4">
                  <?php foreach ($items as $p): ?>
                    <div class="col"><?php render_product_card($mysqli, $p); ?></div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
            </section>
            <?php
            break;

        case 'promo_banner':
            $items = get_section_items($mysqli, $section['id']);
            if (!$items) break;
            ?>
            <section class="section-tight">
              <div class="container">
                <div class="row g-3 g-md-4">
                <?php foreach ($items as $it): ?>
                  <div class="<?= $layout === 'full-width' ? 'col-12' : 'col-md-6' ?>" data-aos="fade-up">
                    <a href="<?= e(resolve_link($it['link'])) ?>" class="promo-banner-img d-block" style="height:<?= $layout==='full-width'?'420px':'320px' ?>">
                      <img src="<?= e(BASE_URL . '/' . ($it['image'] ?: 'assets/img/banner-placeholder.svg')) ?>" alt="<?= e($it['title']) ?>">
                      <div class="promo-caption">
                        <h3 class="font-serif h4"><?= e($it['title']) ?></h3>
                        <?php if ($it['subtitle']): ?><p class="small mb-0"><?= e($it['subtitle']) ?></p><?php endif; ?>
                      </div>
                    </a>
                  </div>
                <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'image_text':
        case 'brand_story':
            $items = get_section_items($mysqli, $section['id']);
            $image = $items[0]['image'] ?? 'assets/img/banner-placeholder.svg';
            $imgFirst = $layout !== 'split-right';
            ?>
            <section class="section">
              <div class="container">
                <div class="row align-items-center g-4 g-lg-5">
                  <div class="col-lg-6 <?= $imgFirst ? 'order-lg-1' : 'order-lg-2' ?>" data-aos="fade-<?= $imgFirst ? 'right' : 'left' ?>">
                    <img src="<?= e(BASE_URL . '/' . $image) ?>" class="w-100" style="border-radius:8px;object-fit:cover;max-height:520px;">
                  </div>
                  <div class="col-lg-6 <?= $imgFirst ? 'order-lg-2' : 'order-lg-1' ?>" data-aos="fade-<?= $imgFirst ? 'left' : 'right' ?>">
                    <?php if ($section['section_type'] === 'brand_story'): ?><p class="text-uppercase small text-muted mb-2" style="letter-spacing:.1em">Our Story</p><?php endif; ?>
                    <h2 class="mb-3"><?= e($section['title']) ?></h2>
                    <p class="text-muted mb-4"><?= e($section['subtitle']) ?></p>
                    <?php if ($section['button_text']): ?><a href="<?= e(resolve_link($section['button_url'])) ?>" class="btn-brand"><?= e($section['button_text']) ?></a><?php endif; ?>
                  </div>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'newsletter':
            ?>
            <section class="newsletter-section">
              <div class="container" data-aos="fade-up">
                <h2><?= e($section['title'] ?: 'Stay In Style') ?></h2>
                <p class="mb-0" style="color:#cabfa9"><?= e($section['subtitle']) ?></p>
                <form class="newsletter-inline js-inline-newsletter">
                  <input type="email" name="email" placeholder="Enter your email" required>
                  <button type="submit">Subscribe</button>
                </form>
              </div>
            </section>
            <?php
            break;

        case 'testimonials':
            $items = get_section_items($mysqli, $section['id']);
            if (!$items) {
                $items = [
                    ['title' => 'Ayesha K.', 'subtitle' => 'Beautiful fabric quality and the fit was perfect. Will definitely order again!'],
                    ['title' => 'Fatima R.', 'subtitle' => 'Fast delivery and the embroidery work is stunning in person.'],
                    ['title' => 'Sana M.', 'subtitle' => 'My go-to store for festive wear. Never disappoints.'],
                ];
            }
            ?>
            <section class="section">
              <div class="container">
                <?php if ($section['title']): ?><div class="section-head" data-aos="fade-up"><h2><?= e($section['title']) ?></h2></div><?php endif; ?>
                <div class="row g-4">
                  <?php foreach ($items as $it): ?>
                    <div class="col-md-4" data-aos="fade-up">
                      <div class="testimonial-card">
                        <div class="stars"><?= str_repeat('★', 5) ?></div>
                        <p class="text-muted">&ldquo;<?= e($it['subtitle']) ?>&rdquo;</p>
                        <p class="fw-medium mb-0">&mdash; <?= e($it['title']) ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'instagram':
            $items = get_section_items($mysqli, $section['id']);
            ?>
            <section class="section-tight">
              <div class="container">
                <div class="section-head" data-aos="fade-up"><h2><?= e($section['title'] ?: 'Follow Us') ?></h2><?php if ($section['subtitle']): ?><p><?= e($section['subtitle']) ?></p><?php endif; ?></div>
                <div class="row row-cols-3 row-cols-md-6 g-2">
                  <?php foreach ($items as $it): ?>
                    <div class="col" data-aos="fade-up"><a href="<?= e($it['link'] ?: '#') ?>" target="_blank" class="insta-tile d-block"><img src="<?= e(BASE_URL . '/' . ($it['image'] ?: 'assets/img/placeholder.svg')) ?>"></a></div>
                  <?php endforeach; ?>
                  <?php if (!$items): for ($i = 0; $i < 6; $i++): ?>
                    <div class="col" data-aos="fade-up"><div class="insta-tile"><img src="<?= BASE_URL ?>/assets/img/placeholder.svg"></div></div>
                  <?php endfor; endif; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'text_banner':
            $bg = get_setting('theme_dark_color', '#211d17');
            ?>
            <section class="text-banner" style="background:<?= e($bg) ?>">
              <div class="container text-center" data-aos="fade-up">
                <?php if ($section['title']): ?><h2><?= e($section['title']) ?></h2><?php endif; ?>
                <?php if ($section['subtitle']): ?><p><?= e($section['subtitle']) ?></p><?php endif; ?>
                <?php if ($section['button_text']): ?><a href="<?= e(resolve_link($section['button_url'])) ?>" class="btn-outline-light-brand"><?= e($section['button_text']) ?></a><?php endif; ?>
              </div>
            </section>
            <?php
            break;

        case 'two_column':
            $items = get_section_items($mysqli, $section['id']);
            $left = $items[0] ?? null;
            $right = $items[1] ?? null;
            ?>
            <section class="section">
              <div class="container">
                <?php if ($section['title']): ?><div class="section-head" data-aos="fade-up"><h2><?= e($section['title']) ?></h2><?php if ($section['subtitle']): ?><p><?= e($section['subtitle']) ?></p><?php endif; ?></div><?php endif; ?>
                <div class="row g-4">
                  <div class="col-md-6" data-aos="fade-right">
                    <div class="two-col-block">
                      <?php if ($left && $left['image']): ?><img src="<?= e(BASE_URL . '/' . $left['image']) ?>" class="w-100 mb-3" style="border-radius:8px;max-height:340px;object-fit:cover"><?php endif; ?>
                      <h3 class="h5"><?= e($left['title'] ?? '') ?></h3>
                      <p class="text-muted"><?= e($left['subtitle'] ?? '') ?></p>
                      <?php if ($left && $left['link']): ?><a href="<?= e(resolve_link($left['link'])) ?>" class="btn-outline-brand btn-sm">Learn More</a><?php endif; ?>
                    </div>
                  </div>
                  <div class="col-md-6" data-aos="fade-left">
                    <div class="two-col-block">
                      <?php if ($right && $right['image']): ?><img src="<?= e(BASE_URL . '/' . $right['image']) ?>" class="w-100 mb-3" style="border-radius:8px;max-height:340px;object-fit:cover"><?php endif; ?>
                      <h3 class="h5"><?= e($right['title'] ?? '') ?></h3>
                      <p class="text-muted"><?= e($right['subtitle'] ?? '') ?></p>
                      <?php if ($right && $right['link']): ?><a href="<?= e(resolve_link($right['link'])) ?>" class="btn-outline-brand btn-sm">Learn More</a><?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'brands':
            $res = mysqli_query($mysqli, "SELECT * FROM brands WHERE status = 'active' ORDER BY name");
            $brands = [];
            while ($b = mysqli_fetch_assoc($res)) $brands[] = $b;
            if (!$brands) break;
            ?>
            <section class="section-tight brands-strip">
              <div class="container">
                <?php if ($section['title']): ?><div class="section-head" data-aos="fade-up"><h2><?= e($section['title']) ?></h2></div><?php endif; ?>
                <div class="row row-cols-3 row-cols-md-6 g-4 align-items-center justify-content-center">
                  <?php foreach ($brands as $b): ?>
                    <div class="col text-center" data-aos="fade-up">
                      <?php if ($b['logo']): ?><img src="<?= e(BASE_URL . '/' . $b['logo']) ?>" class="brand-logo-tile"><?php else: ?><span class="fw-medium text-muted"><?= e($b['name']) ?></span><?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'features':
            $items = get_section_items($mysqli, $section['id']);
            if (!$items) {
                $items = [
                    ['icon_class' => 'bi-truck', 'title' => 'Free Shipping', 'subtitle' => 'On orders above Rs. 5,000'],
                    ['icon_class' => 'bi-arrow-repeat', 'title' => 'Easy Returns', 'subtitle' => '7-day return policy'],
                    ['icon_class' => 'bi-shield-check', 'title' => 'Secure Payment', 'subtitle' => 'COD & online payments'],
                    ['icon_class' => 'bi-headset', 'title' => '24/7 Support', 'subtitle' => 'We are here to help'],
                ];
            }
            ?>
            <section class="section-tight">
              <div class="container">
                <?php if ($section['title']): ?><div class="section-head" data-aos="fade-up"><h2><?= e($section['title']) ?></h2></div><?php endif; ?>
                <div class="row row-cols-2 row-cols-md-4 g-4 text-center">
                  <?php foreach ($items as $it): ?>
                    <div class="col" data-aos="fade-up">
                      <i class="bi <?= e($it['icon_class'] ?: 'bi-star') ?> feature-icon"></i>
                      <h3 class="h6 mt-2 mb-1"><?= e($it['title']) ?></h3>
                      <p class="small text-muted mb-0"><?= e($it['subtitle']) ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'counters':
            $items = get_section_items($mysqli, $section['id']);
            if (!$items) {
                $items = [
                    ['title' => '5000+', 'subtitle' => 'Happy Customers'],
                    ['title' => '300+', 'subtitle' => 'Unique Designs'],
                    ['title' => '50+', 'subtitle' => 'Cities Delivered'],
                    ['title' => '4.8/5', 'subtitle' => 'Average Rating'],
                ];
            }
            ?>
            <section class="section-tight counters-section">
              <div class="container">
                <div class="row row-cols-2 row-cols-md-4 g-4 text-center">
                  <?php foreach ($items as $it): ?>
                    <div class="col" data-aos="fade-up">
                      <div class="counter-value"><?= e($it['title']) ?></div>
                      <div class="counter-label"><?= e($it['subtitle']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </section>
            <?php
            break;

        case 'custom_html':
            echo '<section class="section-tight"><div class="container">' . $section['custom_html'] . '</div></section>';
            break;
    }
}
