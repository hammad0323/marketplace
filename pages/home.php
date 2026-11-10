<?php
/**
 * Homepage - renders the admin-managed sections in their configured order.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/product-card.php';

seo_apply(seo_get('static', 0, 'home'), [
    'title' => setting('site_name', 'Fahim Store') . ' - ' . setting('site_tagline'),
    'description' => setting('meta_description'),
    'canonical' => abs_url(''),
]);
page_add_schema(schema_organization());
page_add_schema(schema_website());
page_add_schema(schema_local_business());

$sections = db_all('SELECT * FROM homepage_sections WHERE is_active = 1 ORDER BY sort_order, id');

/** Products for a "products" section. */
function home_section_products(array $s, array $cfg): array
{
    $limit = max(2, min(24, (int) ($cfg['limit'] ?? 10)));
    $manual = array_map('intval', db_col("SELECT item_id FROM homepage_section_items WHERE section_id = ? AND item_type = 'product' ORDER BY sort_order, id", [$s['id']]));
    $source = $cfg['source'] ?? 'featured';
    if ($manual && ($source === 'manual' || ($cfg['manual_first'] ?? true))) {
        $items = products_query(['ids' => $manual, 'sort' => 'ids', 'limit' => $limit])['items'];
        if (count($items) >= $limit || $source === 'manual') return $items;
    }
    $o = ['limit' => $limit, 'in_stock' => !empty($cfg['in_stock'])];
    switch ($source) {
        case 'best_sellers': $o['sort'] = 'popular'; break;
        case 'new':          $o['sort'] = 'newest'; break;
        case 'on_sale':      $o['on_sale'] = 1; $o['sort'] = 'discount'; break;
        case 'trending':     $o['sort'] = 'trending'; break;
        case 'rashan':       $o['rashan'] = 1; $o['sort'] = 'popular'; break;
        case 'category':     $o['category_id'] = (int) ($cfg['category_id'] ?? 0); $o['sort'] = 'popular'; break;
        case 'recent_viewed':
            $ids = array_slice(array_filter(array_map('intval', explode(',', $_COOKIE['fs_recent'] ?? ''))), 0, $limit);
            if (!$ids) { $o['sort'] = 'trending'; break; }
            $o['ids'] = $ids; $o['sort'] = 'ids'; break;
        default:             $o['featured'] = 1; $o['sort'] = 'popular';
    }
    $items = products_query($o)['items'];
    if (!empty($manual)) {
        $seen = array_column($items, 'id');
        $pre = products_query(['ids' => $manual, 'sort' => 'ids', 'limit' => $limit])['items'];
        $items = array_slice(array_merge($pre, array_filter($items, function ($p) use ($manual) { return !in_array((int) $p['id'], $manual, true); })), 0, $limit);
    }
    return $items;
}

function section_head(array $s, string $link = ''): string
{
    if (!$s['title']) return '';
    return '<div class="section-head"><div><h2>' . e($s['title']) . '</h2>' . ($s['subtitle'] ? '<p>' . e($s['subtitle']) . '</p>' : '') . '</div>'
        . ($link ? '<a href="' . e(url($link)) . '" class="view-all">View all <i class="bi bi-arrow-right"></i></a>' : '') . '</div>';
}

require FS_ROOT . '/partials/header.php';

foreach ($sections as $s):
    $cfg = json_decode((string) $s['settings'], true) ?: [];
    switch ($s['section_type']):

    case 'hero':
        $banners = db_all("SELECT * FROM banners WHERE position = 'hero' AND " . sql_active_window() . ' ORDER BY sort_order, id');
        if (!$banners) break; ?>
<section class="hero-section">
  <div class="container">
    <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel" data-bs-interval="<?= (int) ($cfg['autoplay'] ?? 5000) ?>">
      <?php if (count($banners) > 1): ?>
      <div class="carousel-indicators">
        <?php foreach ($banners as $i => $b): ?><button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i ? '' : 'active' ?>" aria-label="Slide <?= $i + 1 ?>"<?= $i ? '' : ' aria-current="true"' ?>></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="carousel-inner">
        <?php foreach ($banners as $i => $b):
          $bgImg = $b['background_image'] ? 'background-image:url(' . e(upload_url($b['background_image'])) . ');' : ''; ?>
        <div class="carousel-item<?= $i ? '' : ' active' ?>">
          <?php if ($b['desktop_image']): ?>
            <a href="<?= e($b['button_url'] ? url($b['button_url']) : '#') ?>" class="hero-art">
              <picture>
                <?php if ($b['mobile_image']): ?><source media="(max-width: 767px)" srcset="<?= e(upload_url($b['mobile_image'])) ?>"><?php endif; ?>
                <img src="<?= e(upload_url($b['desktop_image'])) ?>" alt="<?= e($b['heading'] ?: 'Promotion') ?>" class="w-100" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
              </picture>
            </a>
          <?php else: ?>
          <div class="hero-slide" style="background-color:<?= e($b['bg_color']) ?>;color:<?= e($b['text_color']) ?>;<?= $bgImg ?>">
            <div class="hero-text">
              <?php if ($b['subheading']): ?><span class="hero-kicker"><?= e($b['subheading']) ?></span><?php endif; ?>
              <?php if ($b['heading']): ?><<?= $i === 0 ? 'h1' : 'h2' ?> class="hero-title"><?= e($b['heading']) ?></<?= $i === 0 ? 'h1' : 'h2' ?>><?php endif; ?>
              <?php if ($b['description']): ?><p><?= e($b['description']) ?></p><?php endif; ?>
              <?php if ($b['button_text']): ?><a href="<?= e(url($b['button_url'] ?: '')) ?>" class="btn btn-light btn-lg fw-semibold"><?= e($b['button_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </div>
            <?php if ($b['side_image']): ?>
            <div class="hero-side"><img src="<?= e(upload_url($b['side_image'])) ?>" alt="" width="360" height="360" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>></div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($banners) > 1): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php break;

    case 'categories':
        $limit = max(2, (int) ($cfg['limit'] ?? 8));
        $items = db_all("SELECT item_id, custom_image FROM homepage_section_items WHERE section_id = ? AND item_type = 'category' ORDER BY sort_order, id", [$s['id']]);
        $cats = [];
        foreach ($items as $it) {
            if (($c = category_get((int) $it['item_id'])) && category_is_visible((int) $c['id'])) { $c['_img'] = $it['custom_image']; $cats[] = $c; }
        }
        if (!$cats) {
            foreach (categories_all() as $c) if ($c['show_on_home'] && category_is_visible((int) $c['id'])) { $c['_img'] = null; $cats[] = $c; }
        }
        if (!$cats) foreach (category_children(null) as $c) { $c['_img'] = null; $cats[] = $c; }
        $cats = array_slice($cats, 0, $limit); ?>
<section class="home-section">
  <div class="container">
    <?= section_head($s) ?>
    <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-2 g-md-3">
      <?php foreach ($cats as $c): ?><div class="col"><?= render_category_card($c, !empty($cfg['show_count']), $c['_img']) ?></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'products':
        $items = home_section_products($s, $cfg);
        if (!$items) break; ?>
<section class="home-section">
  <div class="container">
    <?= section_head($s, (string) ($cfg['link'] ?? '')) ?>
    <?= render_product_scroller($items) ?>
  </div>
</section>
<?php break;

    case 'rashan_cta': ?>
<section class="home-section">
  <div class="container">
    <div class="rashan-cta">
      <div>
        <span class="hero-kicker"><i class="bi bi-stars me-1"></i>New</span>
        <h2><?= e($s['title'] ?: 'Build Your Monthly Rashan') ?></h2>
        <p><?= e($s['subtitle'] ?: setting('rashan_intro')) ?></p>
        <ul class="list-unstyled d-flex flex-wrap gap-3 small mb-4">
          <li><i class="bi bi-check-circle-fill me-1"></i>Set a monthly budget</li>
          <li><i class="bi bi-check-circle-fill me-1"></i>Save &amp; reuse lists</li>
          <li><i class="bi bi-check-circle-fill me-1"></i>Delivery or pickup</li>
        </ul>
        <a href="<?= url('monthly-rashan/') ?>" class="btn btn-warning btn-lg fw-semibold">Start My Rashan List <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="rashan-cta-card d-none d-md-block">
        <div class="d-flex justify-content-between"><span>Monthly Budget</span><strong><?= money(setting('rashan_default_budget', 30000)) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Selected Rashan</span><strong><?= money((float) setting('rashan_default_budget', 30000) * 0.82) ?></strong></div>
        <div class="progress my-2" role="presentation"><div class="progress-bar bg-success" style="width:82%"></div></div>
        <div class="d-flex justify-content-between text-success"><span>Remaining</span><strong><?= money((float) setting('rashan_default_budget', 30000) * 0.18) ?></strong></div>
      </div>
    </div>
  </div>
</section>
<?php break;

    case 'promotions':
        $promos = db_all('SELECT * FROM promotions WHERE ' . sql_active_window() . ' ORDER BY sort_order, id LIMIT ' . max(1, (int) ($cfg['limit'] ?? 4)));
        if (!$promos) break; ?>
<section class="home-section">
  <div class="container">
    <?= section_head($s, 'offers/') ?>
    <div class="row g-3">
      <?php foreach ($promos as $pr): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <a href="<?= e(promotion_url($pr)) ?>" class="promo-card" style="background:<?= e($pr['bg_color'] ?: '#0f766e') ?>;color:<?= e($pr['text_color'] ?: '#fff') ?>">
          <?php if ($pr['badge_text']): ?><span class="promo-badge"><?= e($pr['badge_text']) ?></span><?php endif; ?>
          <span class="promo-type"><?= e(promo_types()[$pr['promo_type']] ?? '') ?></span>
          <h3><?= e($pr['title']) ?></h3>
          <p><?= e($pr['subtitle']) ?></p>
          <?php if ($pr['image']): ?><img src="<?= e(upload_url($pr['image'])) ?>" alt="" loading="lazy" class="promo-img"><?php endif; ?>
          <span class="promo-link"><?= e($pr['button_text'] ?: 'Shop now') ?> <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'promo_banners':
        $pb = db_all("SELECT * FROM banners WHERE position = 'promo' AND " . sql_active_window() . ' ORDER BY sort_order, id LIMIT 3');
        if (!$pb) break; ?>
<section class="home-section">
  <div class="container">
    <div class="row g-3">
      <?php foreach ($pb as $b): ?>
      <div class="col-md-<?= count($pb) === 1 ? '12' : (count($pb) === 2 ? '6' : '4') ?>">
        <?php if ($b['desktop_image']): ?>
          <a href="<?= e(url($b['button_url'] ?: '')) ?>"><img src="<?= e(upload_url($b['desktop_image'])) ?>" alt="<?= e($b['heading']) ?>" class="img-fluid rounded-4 w-100" loading="lazy"></a>
        <?php else: ?>
        <a href="<?= e(url($b['button_url'] ?: '')) ?>" class="promo-banner" style="background:<?= e($b['bg_color']) ?>;color:<?= e($b['text_color']) ?>">
          <div><h3><?= e($b['heading']) ?></h3><p><?= e($b['subheading']) ?></p><span class="btn btn-sm btn-dark"><?= e($b['button_text'] ?: 'Shop now') ?></span></div>
          <?php if ($b['side_image']): ?><img src="<?= e(upload_url($b['side_image'])) ?>" alt="" loading="lazy" width="140" height="140"><?php endif; ?>
        </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'brands':
        $limit = max(2, (int) ($cfg['limit'] ?? 12));
        $manual = db_col("SELECT item_id FROM homepage_section_items WHERE section_id = ? AND item_type = 'brand' ORDER BY sort_order", [$s['id']]);
        $brands = $manual
            ? db_all('SELECT * FROM brands WHERE id IN (' . in_placeholders($manual) . ') AND is_active = 1 AND deleted_at IS NULL ORDER BY FIELD(id, ' . implode(',', array_map('intval', $manual)) . ')', $manual)
            : db_all('SELECT * FROM brands WHERE is_active = 1 AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order LIMIT ' . $limit);
        if (!$brands) break; ?>
<section class="home-section">
  <div class="container">
    <?= section_head($s, 'brands/') ?>
    <div class="brand-strip">
      <?php foreach ($brands as $b): ?>
      <a href="<?= e(brand_url($b)) ?>" class="brand-chip">
        <?php if ($b['logo']): ?><img src="<?= e(upload_url($b['logo'])) ?>" alt="<?= e($b['name']) ?>" loading="lazy" height="40"><?php else: ?><span><?= e($b['name']) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'testimonials':
        $ts = db_all('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, id LIMIT 8');
        if (!$ts) break; ?>
<section class="home-section">
  <div class="container">
    <?= section_head($s) ?>
    <div class="row g-3">
      <?php foreach ($ts as $t): ?>
      <div class="col-md-6 col-xl-3">
        <figure class="testimonial">
          <div class="stars" aria-label="<?= (int) $t['rating'] ?> stars"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int) $t['rating']) ?></div>
          <blockquote>“<?= e($t['message']) ?>”</blockquote>
          <figcaption><strong><?= e($t['customer_name']) ?></strong><?php if ($t['location']): ?><span><?= e($t['location']) ?></span><?php endif; ?></figcaption>
        </figure>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'newsletter': ?>
<section class="home-section">
  <div class="container">
    <div class="newsletter-box">
      <div><h2><?= e($s['title'] ?: 'Subscribe') ?></h2><p class="mb-0"><?= e($s['subtitle']) ?></p></div>
      <form class="js-newsletter d-flex gap-2 flex-grow-1" novalidate>
        <input type="email" name="email" class="form-control form-control-lg" placeholder="Enter your email" required aria-label="Email address">
        <button class="btn btn-dark btn-lg" type="submit">Subscribe</button>
      </form>
      <div class="small w-100 js-newsletter-msg"></div>
    </div>
  </div>
</section>
<?php break;

    endswitch;
endforeach;

require FS_ROOT . '/partials/footer.php';
