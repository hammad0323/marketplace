<?php
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
                while ($c = mysqli_fetch_assoc($res)) $cats[] = ['title' => $c['name'], 'image' => $c['image'], 'link' => 'shop.php?category=' . $c['slug']];
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
                      <a href="<?= BASE_URL ?>/<?= e($it['link'] ?: 'shop.php') ?>" class="cat-tile d-block">
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
                  <?php if ($section['button_text']): ?><a href="<?= BASE_URL ?>/<?= e($section['button_url'] ?: 'shop.php') ?>" class="btn-outline-brand"><?= e($section['button_text']) ?></a><?php endif; ?>
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
                    <a href="<?= BASE_URL ?>/<?= e($it['link'] ?: 'shop.php') ?>" class="promo-banner-img d-block" style="height:<?= $layout==='full-width'?'420px':'320px' ?>">
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
                    <?php if ($section['button_text']): ?><a href="<?= BASE_URL ?>/<?= e($section['button_url'] ?: 'shop.php') ?>" class="btn-brand"><?= e($section['button_text']) ?></a><?php endif; ?>
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

        case 'custom_html':
            echo '<section class="section-tight"><div class="container">' . $section['custom_html'] . '</div></section>';
            break;
    }
}
