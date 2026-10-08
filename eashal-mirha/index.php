<?php
require __DIR__ . '/includes/bootstrap.php';

$sections = rows('SELECT * FROM home_sections WHERE enabled = 1 ORDER BY sort_order, id');
$wish = wishlist_ids();
$seo = [
    'title'       => setting('meta_title'),
    'description' => setting('meta_description'),
    'canonical'   => abs_url(''),
    'schema'      => [[
        '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => setting('site_name'), 'url' => abs_url(''),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => abs_url('shop') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
    ]],
];
$bodyClass = 'home';
require ROOT . '/includes/header.php';

/** Renders a banner block (parallax / promo). */
function render_banner_content(array $b, string $extra = ''): void
{ ?>
  <div class="banner-content <?= e($extra) ?>" style="color:<?= e($b['text_color']) ?>">
    <?php if ($b['small_text']): ?><span class="eyebrow" data-reveal><?= e($b['small_text']) ?></span><?php endif; ?>
    <?php if ($b['heading']): ?><h2 class="display" data-reveal data-delay="1"><?= e($b['heading']) ?></h2><?php endif; ?>
    <?php if ($b['text']): ?><p data-reveal data-delay="2"><?= e($b['text']) ?></p><?php endif; ?>
    <?php if ($b['btn_text']): ?><a class="btn btn-outline-light" href="<?= e(url((string)$b['btn_link'])) ?>" data-reveal data-delay="3"><?= e($b['btn_text']) ?> <?= icon('arrow', 16) ?></a><?php endif; ?>
  </div>
<?php }

function section_head(array $s, string $link = '', string $linkText = 'View All'): void
{
    if (!$s['title'] && !$s['subtitle']) {
        return;
    } ?>
  <div class="section-head" data-reveal>
    <span class="ornament">✦</span>
    <?php if ($s['title']): ?><h2 class="section-title"><?= e($s['title']) ?></h2><?php endif; ?>
    <?php if ($s['subtitle']): ?><p class="section-sub"><?= e($s['subtitle']) ?></p><?php endif; ?>
    <?php if ($link): ?><a class="link-underline" href="<?= e($link) ?>"><?= e($linkText) ?></a><?php endif; ?>
  </div>
<?php }

foreach ($sections as $s):
    switch ($s['skey']):

    /* -------------------- 1. HERO CAROUSEL -------------------- */
    case 'hero':
        $slides = rows('SELECT * FROM slides WHERE status = 1 ORDER BY sort_order, id');
        if (!$slides) break;
        $unit = setting('hero_height_unit', 'vh') === 'px' ? 'px' : 'vh';
        $boxed = setting('hero_width', 'full') === 'boxed';
        ?>
        <section class="hero<?= $boxed ? ' hero--boxed' : '' ?>" style="--hero-h:<?= (int)setting('hero_height', 92) . $unit ?>;--hero-h-m:<?= (int)setting('hero_height_mobile', 78) . $unit ?>;--hero-max:<?= (int)setting('hero_max_width', 1400) ?>px;--hero-fs:<?= (int)setting('hero_heading_size', 64) ?>px;--hero-fs-m:<?= (int)setting('hero_heading_size_mobile', 36) ?>px">
          <div class="swiper hero-swiper<?= setting('hero_kenburns', '1') === '1' ? ' kenburns' : '' ?>" data-autoplay="<?= (int)setting('hero_autoplay', 6000) ?>" data-effect="<?= e(setting('hero_effect', 'fade')) ?>">
            <div class="swiper-wrapper">
              <?php foreach ($slides as $i => $sl): ?>
              <div class="swiper-slide hero-slide align-<?= e($sl['align']) ?>">
                <picture class="hero-bg" data-parallax="0.25">
                  <?php if ($sl['mobile_image']): ?><source media="(max-width: 767px)" srcset="<?= e(img($sl['mobile_image'])) ?>"><?php endif; ?>
                  <img src="<?= e(img($sl['image'])) ?>" alt="<?= e($sl['heading']) ?>" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
                </picture>
                <div class="hero-overlay" style="opacity:<?= (float)$sl['overlay'] ?>"></div>
                <div class="container hero-content" style="color:<?= e($sl['text_color']) ?>">
                  <div class="hero-text">
                    <?php if ($sl['small_text']): ?><span class="eyebrow anim" style="--d:.1s"><?= e($sl['small_text']) ?></span><?php endif; ?>
                    <?php if ($sl['heading']): ?><h<?= $i ? '2' : '1' ?> class="hero-title anim" style="--d:.3s"><?= e($sl['heading']) ?></h<?= $i ? '2' : '1' ?>><?php endif; ?>
                    <?php if ($sl['subheading']): ?><p class="hero-sub anim" style="--d:.5s"><?= e($sl['subheading']) ?></p><?php endif; ?>
                    <div class="hero-btns anim" style="--d:.7s">
                      <?php if ($sl['btn_text']): ?><a class="btn btn-gold" href="<?= e(url((string)$sl['btn_link'])) ?>"><?= e($sl['btn_text']) ?></a><?php endif; ?>
                      <?php if ($sl['btn2_text']): ?><a class="btn btn-outline-light" href="<?= e(url((string)$sl['btn2_link'])) ?>"><?= e($sl['btn2_text']) ?></a><?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="hero-pagination"></div>
            <button class="hero-nav hero-prev" aria-label="Previous"><?= icon('left', 24) ?></button>
            <button class="hero-nav hero-next" aria-label="Next"><?= icon('right', 24) ?></button>
          </div>
          <a href="#afterHero" class="scroll-cue" aria-label="Scroll"><span></span></a>
        </section>
        <div id="afterHero"></div>
        <?php break;

    /* -------------------- 2. CATEGORIES -------------------- */
    case 'categories':
        $cats = rows('SELECT * FROM categories WHERE status = 1 AND show_home = 1 AND parent_id IS NULL ORDER BY sort_order, name LIMIT ' . max(1, (int)$s['item_limit']));
        if (!$cats) break; ?>
        <section class="section">
          <div class="container">
            <?php section_head($s); ?>
            <div class="cat-grid cat-grid--<?= count($cats) ?>">
              <?php foreach ($cats as $i => $cat):
                $subs = rows('SELECT * FROM categories WHERE status = 1 AND parent_id = ? ORDER BY sort_order LIMIT 4', [$cat['id']]); ?>
                <a class="cat-tile" href="<?= category_url($cat) ?>" data-reveal data-delay="<?= $i % 3 ?>">
                  <div class="cat-tile__img"><img src="<?= e(img($cat['image'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy"></div>
                  <div class="cat-tile__info">
                    <h3><?= e($cat['name']) ?></h3>
                    <?php if ($subs): ?><p><?= e(implode(' · ', array_column($subs, 'name'))) ?></p><?php endif; ?>
                    <span class="cat-tile__cta">Shop Now <?= icon('arrow', 14) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php break;

    /* -------------------- 3 & 5. PRODUCT CAROUSELS -------------------- */
    case 'new_arrivals':
    case 'best_sellers':
        $isNew = $s['skey'] === 'new_arrivals';
        $products = $isNew
            ? product_list('p.is_new = 1', [], 'p.created_at DESC', (int)$s['item_limit'])
            : product_list('p.is_bestseller = 1', [], 'p.sales_count DESC', (int)$s['item_limit']);
        if (!$products) break; ?>
        <section class="section <?= $isNew ? '' : 'section--cream' ?>">
          <div class="container">
            <?php section_head($s); ?>
            <div class="carousel" data-reveal>
              <div class="swiper product-swiper">
                <div class="swiper-wrapper">
                  <?php foreach ($products as $p): ?><div class="swiper-slide"><?php require ROOT . '/includes/product-card.php'; ?></div><?php endforeach; ?>
                </div>
              </div>
              <button class="car-nav car-prev" aria-label="Previous"><?= icon('left', 20) ?></button>
              <button class="car-nav car-next" aria-label="Next"><?= icon('right', 20) ?></button>
              <div class="car-progress"></div>
            </div>
            <div class="center mt-40"><a class="btn btn-dark" href="<?= url('shop?filter=' . ($isNew ? 'new' : 'bestseller')) ?>">View All <?= e($s['title']) ?></a></div>
          </div>
        </section>
        <?php break;

    /* -------------------- 4. PARALLAX BANNER -------------------- */
    case 'parallax':
        $b = banner('parallax');
        if (!$b || !$b['status']) break; ?>
        <section class="parallax-banner" style="--bh:<?= (int)$b['height'] ?>px">
          <div class="parallax-banner__bg" data-parallax="0.35" style="background-image:url('<?= e(img($b['image'])) ?>')"></div>
          <div class="parallax-banner__overlay" style="opacity:<?= (float)$b['overlay'] ?>"></div>
          <div class="container"><?php render_banner_content($b, 'center'); ?></div>
        </section>
        <?php break;

    /* -------------------- 6. PROMO DUO -------------------- */
    case 'promo_duo':
        $l = banner('promo_left');
        $r = banner('promo_right'); ?>
        <section class="section">
          <div class="container promo-duo">
            <?php foreach ([$l, $r] as $i => $b): if (!$b || !$b['status']) continue; ?>
              <a class="promo-tile" href="<?= e(url((string)$b['btn_link'])) ?>" style="--bh:<?= (int)$b['height'] ?>px" data-reveal data-delay="<?= $i ?>">
                <div class="promo-tile__bg" style="background-image:url('<?= e(img($b['image'])) ?>')"></div>
                <div class="promo-tile__overlay" style="opacity:<?= (float)$b['overlay'] ?>"></div>
                <div class="promo-tile__content" style="color:<?= e($b['text_color']) ?>">
                  <?php if ($b['small_text']): ?><span class="eyebrow"><?= e($b['small_text']) ?></span><?php endif; ?>
                  <h3 class="display-sm"><?= e($b['heading']) ?></h3>
                  <?php if ($b['text']): ?><p><?= e($b['text']) ?></p><?php endif; ?>
                  <?php if ($b['btn_text']): ?><span class="link-underline light"><?= e($b['btn_text']) ?></span><?php endif; ?>
                </div>
                <span class="promo-tile__frame"></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php break;

    /* -------------------- 7. CATEGORY TABS -------------------- */
    case 'category_tabs':
        $tabCats = rows('SELECT * FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 6');
        if (!$tabCats) break; ?>
        <section class="section">
          <div class="container">
            <?php section_head($s); ?>
            <div class="tabs" data-tabs data-reveal>
              <div class="tabs__nav">
                <?php foreach ($tabCats as $i => $tc): ?><button class="<?= $i ? '' : 'active' ?>" data-tab="t<?= $tc['id'] ?>"><?= e($tc['name']) ?></button><?php endforeach; ?>
              </div>
              <?php foreach ($tabCats as $i => $tc):
                $tp = product_list('p.category_id = ?', [$tc['id']], 'p.is_featured DESC, p.created_at DESC', (int)$s['item_limit']); ?>
                <div class="tabs__panel<?= $i ? '' : ' active' ?>" id="t<?= $tc['id'] ?>">
                  <?php if ($tp): ?>
                    <div class="grid grid-4"><?php foreach ($tp as $p) { require ROOT . '/includes/product-card.php'; } ?></div>
                    <div class="center mt-40"><a class="link-underline" href="<?= category_url($tc) ?>">Explore all <?= e($tc['name']) ?></a></div>
                  <?php else: ?><p class="muted center">New pieces are coming soon.</p><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php break;

    /* -------------------- 8. FEATURES -------------------- */
    case 'features': ?>
        <section class="features">
          <div class="container features__grid">
            <?php for ($i = 1; $i <= 4; $i++): $f = banner('feature_' . $i); if (!$f || !$f['status']) continue; ?>
              <div class="feature" data-reveal data-delay="<?= $i - 1 ?>">
                <span class="feature__icon"><?= icon($f['image'] ?: 'star', 30) ?></span>
                <h4><?= e($f['heading']) ?></h4>
                <p><?= e($f['text']) ?></p>
              </div>
            <?php endfor; ?>
          </div>
        </section>
        <?php break;

    /* -------------------- 9. TESTIMONIALS -------------------- */
    case 'testimonials':
        $reviews = rows('SELECT r.*, p.name product_name, p.slug FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.status = 1 AND r.rating >= 4 ORDER BY r.created_at DESC LIMIT ' . max(1, (int)$s['item_limit']));
        if (!$reviews) break; ?>
        <section class="section section--dark testimonials">
          <div class="container">
            <?php section_head($s); ?>
            <div class="swiper testi-swiper" data-reveal>
              <div class="swiper-wrapper">
                <?php foreach ($reviews as $r): ?>
                  <div class="swiper-slide">
                    <blockquote class="testi">
                      <span class="testi__quote">“</span>
                      <?= stars((float)$r['rating']) ?>
                      <p><?= e($r['comment']) ?></p>
                      <footer><strong><?= e($r['name']) ?></strong> — on <a href="<?= url('product/' . $r['slug']) ?>"><?= e($r['product_name']) ?></a></footer>
                    </blockquote>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="testi-pagination"></div>
            </div>
          </div>
        </section>
        <?php break;

    /* -------------------- 10. NEWSLETTER -------------------- */
    case 'newsletter': ?>
        <section class="newsletter">
          <div class="container newsletter__inner" data-reveal>
            <span class="ornament">✦</span>
            <h2 class="section-title"><?= e($s['title'] ?: 'Join the Inner Circle') ?></h2>
            <?php if ($s['subtitle']): ?><p class="section-sub"><?= e($s['subtitle']) ?></p><?php endif; ?>
            <form class="newsletter__form" data-ajax-form action="<?= url('newsletter') ?>" method="post">
              <?= csrf_field() ?>
              <input type="email" name="email" placeholder="Your email address" required>
              <button class="btn btn-gold" type="submit">Subscribe</button>
            </form>
          </div>
        </section>
        <?php break;

    endswitch;
endforeach;

require ROOT . '/includes/footer.php';
