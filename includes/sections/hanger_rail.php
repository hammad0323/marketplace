<?php
$limit = max(1, min(30, (int) ($s['limit'] ?: 12)));
if ($s['source'] === 'manual' && $s['product_ids']) {
    $products = products_by_ids($s['product_ids'], $limit);
} elseif ($s['source'] === 'category' && $s['category_ids']) {
    $catIds = [];
    foreach ($s['category_ids'] as $cid) {
        $catIds = array_merge($catIds, category_descendant_ids((int) $cid));
    }
    $products = products_search(['category_ids' => array_values(array_unique($catIds))], 'featured', $limit)['items'];
} else {
    $products = new_arrivals($limit);
}
if (!$products) {
    return;
}
// Transparent cut-outs hang as garments; ordinary photos hang as cards. Keep cut-outs first so the rail looks even.
$isCutout = fn(?string $path) => (bool) preg_match('/\.(png|webp|svg)$/i', (string) parse_url((string) $path, PHP_URL_PATH));
$items = [];
foreach ($products as $p) {
    [$front, $back] = $s['image'] === 'second' && $p['hover_image'] ? [$p['hover_image'], $p['image']] : [$p['image'], $p['hover_image']];
    $items[] = ['p' => $p, 'front' => $front, 'back' => $back, 'cutout' => $isCutout($front)];
}
usort($items, fn($a, $b) => $b['cutout'] <=> $a['cutout']);
$classes = 'uc-rail is-pipe-' . $s['pipe'] . ' is-hover-' . $s['hover'] . ' is-details-' . $s['details'] . ($s['sway'] === '1' ? ' has-sway' : '');
?>
<section <?= section_wrapper_attrs($s, 'hanger_rail', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-head-row">
            <?= section_heading($s, 'left') ?>
            <?php if ($s['button_text']): ?><a class="uc-link-arrow d-none d-md-inline-flex" href="<?= e(url($s['button_url'] ?: 'category/t-shirts')) ?>"><?= e($s['button_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
        <div class="<?= e($classes) ?>" data-rail>
            <span class="uc-rail-pipe" aria-hidden="true"><i></i><i></i></span>
            <button type="button" class="uc-rail-nav is-prev" data-rail-nav="-1" aria-label="Previous products"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="uc-rail-nav is-next" data-rail-nav="1" aria-label="More products"><i class="bi bi-chevron-right"></i></button>
            <ul class="uc-rail-track" data-rail-track>
                <?php foreach ($items as $i => $it): $p = $it['p']; $pi = $p['price_info']; ?>
                    <li class="uc-hang<?= $it['cutout'] ? ' is-cutout' : ' is-card' ?><?= $p['in_stock'] ? '' : ' is-soldout' ?>" style="--i:<?= $i ?>">
                        <a class="uc-hang-link" href="<?= e($p['url']) ?>" aria-label="<?= e($p['name'] . ' — ' . money($pi['final'])) ?>">
                            <span class="uc-hang-swing">
                                <svg class="uc-hang-hook" viewBox="0 0 40 46" aria-hidden="true"><path d="M20 46V30c0-4 6-6 9-10 4-5 1-14-9-14S9 14 12 18"/></svg>
                                <span class="uc-hang-spin">
                                    <span class="uc-hang-face is-front"><img src="<?= e(img_sized($it['front'], 500)) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" width="300" height="350" loading="lazy" decoding="async"></span>
                                    <span class="uc-hang-face is-back<?= $it['back'] ? '' : ' is-mirror' ?>"><img src="<?= e(img_sized($it['back'] ?: $it['front'], 500)) ?>" alt="" width="300" height="350" loading="lazy" decoding="async"></span>
                                </span>
                                <?php if (!$p['in_stock']): ?><span class="uc-hang-badge">Sold out</span><?php elseif ($p['badge_label']): ?><span class="uc-hang-badge"><?= e($p['badge_label']) ?></span><?php endif; ?>
                            </span>
                            <span class="uc-hang-tag">
                                <span class="uc-hang-name"><?= e($p['name']) ?></span>
                                <span class="uc-hang-price"><?php if ($pi['on_sale']): ?><strong class="is-sale"><?= e(money($pi['sale'])) ?></strong> <s><?= e(money($pi['regular'])) ?></s><?php else: ?><strong><?= e(money($pi['final'])) ?></strong><?php endif; ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($s['button_text']): ?><div class="text-center mt-3 d-md-none"><a class="uc-btn uc-btn--outline" href="<?= e(url($s['button_url'] ?: 'category/t-shirts')) ?>"><?= e($s['button_text']) ?></a></div><?php endif; ?>
    </div>
</section>
