<?php
require __DIR__ . '/config/config.php';
$seoEntityType = 'homepage'; $seoEntityId = null;
$sections = db_fetch_all("SELECT * FROM homepage_sections WHERE status='active' ORDER BY sort_order");
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';

echo render_schema([
    '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => site_name(),
    'url' => base_url(), 'potentialAction' => ['@type' => 'SearchAction',
        'target' => base_url('search.php') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
]);
echo render_schema([
    '@context' => 'https://schema.org', '@type' => 'Organization', 'name' => site_name(),
    'url' => base_url(), 'logo' => upload_url(get_setting('site_logo')) ?: asset_url('images/logo.png'),
]);

foreach ($sections as $section):
    $type = $section['section_type'];

    if ($type === 'hero_slider'):
        $banners = db_fetch_all("SELECT * FROM banners WHERE status='active' ORDER BY sort_order LIMIT ?", 'i', [(int)$section['item_count']]);
        if ($banners): ?>
        <section class="hero-slider" id="hero-slider">
          <?php foreach ($banners as $i => $b): ?>
            <div class="hero-slide<?= $i === 0 ? ' active' : '' ?>" style="background:<?= clean($b['bg_color']) ?>;<?php if ($b['image']): ?>background-image:url('<?= upload_url($b['image']) ?>');<?php endif; ?>">
              <div class="container hero-slide-inner text-<?= clean($b['text_align']) ?>">
                <?php if ($b['sub_heading']): ?><span class="hero-sub"><?= clean($b['sub_heading']) ?></span><?php endif; ?>
                <h1><?= clean($b['heading']) ?></h1>
                <?php if ($b['description']): ?><p><?= clean($b['description']) ?></p><?php endif; ?>
                <?php if ($b['button_text']): ?><a class="btn btn-accent" href="<?= base_url($b['button_url']) ?>"><?= clean($b['button_text']) ?></a><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="hero-dots">
            <?php foreach ($banners as $i => $b): ?><button class="hero-dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>"></button><?php endforeach; ?>
          </div>
        </section>
    <?php endif; endif;

    if ($type === 'featured_categories'):
        $cats = db_fetch_all("SELECT * FROM categories WHERE status='active' ORDER BY sort_order LIMIT ?", 'i', [(int)$section['item_count']]); ?>
        <section class="section container">
          <div class="section-head"><h2><?= clean($section['heading']) ?></h2><p><?= clean($section['description']) ?></p></div>
          <div class="category-grid">
            <?php foreach ($cats as $cat): ?>
              <a class="category-card" href="<?= base_url('category.php?slug=' . $cat['slug']) ?>">
                <img src="<?= category_image_or_default($cat['image']) ?>" alt="<?= clean($cat['name']) ?>">
                <span><?= clean($cat['name']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
    <?php endif;

    if ($type === 'featured_shops'):
        $shops = db_fetch_all("SELECT * FROM shops WHERE status='active' ORDER BY rating_avg DESC LIMIT ?", 'i', [(int)$section['item_count']]); ?>
        <section class="section container bg-alt">
          <div class="section-head"><h2><?= clean($section['heading']) ?></h2><p><?= clean($section['description']) ?></p></div>
          <div class="shop-grid">
            <?php foreach ($shops as $shop): ?>
              <a class="shop-card" href="<?= base_url('shop.php?slug=' . $shop['slug']) ?>">
                <div class="shop-card-cover" style="background-image:url('<?= shop_cover_or_default($shop['cover_image']) ?>')"></div>
                <img class="shop-card-logo" src="<?= shop_logo_or_default($shop['logo']) ?>" alt="<?= clean($shop['shop_name']) ?>">
                <h3><?= clean($shop['shop_name']) ?></h3>
                <span class="rating"><i class="fa-solid fa-star"></i> <?= number_format($shop['rating_avg'], 1) ?> (<?= $shop['rating_count'] ?>)</span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
    <?php endif;

    if (in_array($type, ['featured_products', 'latest_products', 'best_selling', 'discount_products'], true)):
        $order = ['latest_products' => 'p.created_at DESC', 'best_selling' => 'p.total_sold DESC', 'discount_products' => 'p.sale_price IS NOT NULL DESC'];
        $orderBy = $order[$type] ?? 'p.rating_avg DESC';
        $products = db_fetch_all("SELECT p.*, s.shop_name, s.slug as shop_slug FROM products p JOIN shops s ON s.id = p.shop_id
                                   WHERE p.status='active' AND s.status='active' ORDER BY $orderBy LIMIT ?", 'i', [(int)$section['item_count']]); ?>
        <section class="section container">
          <div class="section-head"><h2><?= clean($section['heading']) ?></h2><p><?= clean($section['description']) ?></p></div>
          <div class="product-grid">
            <?php foreach ($products as $p) render_product_card($p); ?>
          </div>
        </section>
    <?php endif;

    if ($type === 'promotional_banner'):
        $promo = db_fetch_one("SELECT * FROM banners WHERE status='active' ORDER BY sort_order DESC LIMIT 1");
        if ($promo): ?>
        <section class="container">
          <div class="promo-banner" style="background:<?= clean($promo['bg_color']) ?>">
            <div>
              <h2><?= clean($promo['heading']) ?></h2>
              <p><?= clean($promo['description']) ?></p>
              <?php if ($promo['button_text']): ?><a class="btn btn-light" href="<?= base_url($promo['button_url']) ?>"><?= clean($promo['button_text']) ?></a><?php endif; ?>
            </div>
          </div>
        </section>
    <?php endif; endif;
endforeach;

require __DIR__ . '/includes/footer.php';
