<?php
/**
 * Reusable UI pieces.
 */

function breadcrumb($items)
{
    // $items = array(array('Home', '/'), array('Mosques', '/mosques'), array('Current page', null))
    $ld = array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array());
    echo '<nav aria-label="Breadcrumb" class="crumbs"><ol>';
    $n = count($items);
    foreach ($items as $i => $it) {
        $last = ($i === $n - 1);
        $ld['itemListElement'][] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => abs_url($it[1] ? $it[1] : current_path()));
        echo '<li>';
        if ($i === 0) {
            echo '<i class="bi bi-house" aria-hidden="true"></i> ';
        }
        if ($last || !$it[1]) {
            echo '<span' . ($last ? ' aria-current="page"' : '') . '>' . e($it[0]) . '</span>';
        } else {
            echo '<a href="' . e(url($it[1])) . '">' . e($it[0]) . '</a>';
        }
        echo '</li>';
    }
    echo '</ol></nav>';
    echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
}

function img_or_initials($src, $alt, $name, $class = '')
{
    // Initials are always rendered underneath; the image covers them and is removed if it fails to load.
    echo '<span class="img-stack ' . e($class) . '"><span class="initials" aria-hidden="true">' . e(initials($name)) . '</span>';
    if ($src !== '') {
        echo '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy" decoding="async" onerror="this.remove()">';
    }
    echo '</span>';
}

function venue_card($v, $badges = array())
{
    $href = url(venue_url($v));
    $cover = venue_cover($v);
    $logo = venue_logo($v);
    $id = (int)$v['venue_id'];
    $addr = trim((string)$v['street_address']);
    $hasPrayer = !empty($badges['prayer'][$id]);
    $jummah = isset($badges['jummah'][$id]) ? $badges['jummah'][$id] : '';
    ?>
<article class="venue-card">
  <div class="venue-card-media">
    <?php if ($cover !== ''): ?>
      <img src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async" onerror="this.remove()">
    <?php endif; ?>
    <div class="venue-card-logo"><?php img_or_initials($logo, $v['venue_title'] . ' logo', $v['venue_title']); ?></div>
  </div>
  <div class="venue-card-body">
    <h3 class="venue-card-title"><a class="stretched-link" href="<?= e($href) ?>"><?= e(trim($v['venue_title'])) ?></a></h3>
    <?php if (!empty($v['venue_aka'])): ?><p class="venue-card-aka">Also known as <?= e(excerpt($v['venue_aka'], 70)) ?></p><?php endif; ?>
    <p class="venue-card-addr"><i class="bi bi-geo-alt" aria-hidden="true"></i><span><?= e($addr !== '' ? excerpt($addr, 60) . ', ' : '') ?><?= e(venue_place_line($v)) ?></span></p>
    <?php if ($hasPrayer || $jummah): ?>
    <ul class="venue-card-tags">
      <?php if ($hasPrayer): ?><li><i class="bi bi-clock" aria-hidden="true"></i> Daily prayer times</li><?php endif; ?>
      <?php if ($jummah): ?><li><i class="bi bi-people" aria-hidden="true"></i> Jumu'ah <?= e(fmt_time($jummah)) ?></li><?php endif; ?>
    </ul>
    <?php endif; ?>
  </div>
</article>
<?php
}

function detail_card($d, $showVenue = true)
{
    $g = grand_info($d['grand_category_id']);
    $img = detail_image($d);
    $cats = detail_categories($d);
    $when = detail_when($d);
    $aud = trim(audience_gender($d['gender_id']) . (audience_ages($d['age_group_id']) !== '' ? ' · ' . audience_ages($d['age_group_id']) : ''), ' ·');
    ?>
<article class="detail-card">
  <div class="detail-card-media">
    <?php if ($img !== ''): ?>
      <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async" onerror="this.remove()">
    <?php endif; ?>
    <span class="detail-card-icon" aria-hidden="true"><i class="bi <?= e($g['icon']) ?>"></i></span>
    <?php if ($cats): ?><span class="chip chip-on-media"><?= e($cats[0]['name']) ?></span><?php endif; ?>
  </div>
  <div class="detail-card-body">
    <h3 class="detail-card-title"><a class="stretched-link" href="<?= e(url(detail_url($d))) ?>"><?= e(trim($d['details_title'])) ?></a></h3>
    <?php if ($showVenue && !empty($d['venue_title'])): ?>
      <p class="detail-card-venue"><i class="bi bi-building" aria-hidden="true"></i><?= e(trim($d['venue_title'])) ?><?= !empty($d['city_title']) ? ', ' . e($d['city_title']) : '' ?></p>
    <?php endif; ?>
    <?php if ($when !== ''): ?><p class="detail-card-meta"><i class="bi bi-calendar3" aria-hidden="true"></i><?= e($when) ?></p><?php endif; ?>
    <?php if ($aud !== ''): ?><p class="detail-card-meta"><i class="bi bi-person" aria-hidden="true"></i><?= e($aud) ?></p><?php endif; ?>
    <?php if ($when === '' && $aud === ''): ?><p class="detail-card-text"><?= e(excerpt($d['description'], 110)) ?></p><?php endif; ?>
  </div>
</article>
<?php
}

function pagination($total, $per, $page)
{
    $pages = (int)ceil($total / max(1, $per));
    if ($pages <= 1) {
        return;
    }
    $win = array();
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= 1) {
            $win[] = $i;
        } elseif (end($win) !== '…') {
            $win[] = '…';
        }
    }
    echo '<nav class="pager" aria-label="Pagination"><ul class="pagination justify-content-center flex-wrap">';
    $path = url(current_path());
    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" rel="prev" href="' . e($path . query_with(array('page' => $page - 1 > 1 ? $page - 1 : null))) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Previous page</span></a></li>';
    }
    foreach ($win as $w) {
        if ($w === '…') {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        } elseif ($w === $page) {
            echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $w . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . e($path . query_with(array('page' => $w > 1 ? $w : null))) . '">' . $w . '</a></li>';
        }
    }
    if ($page < $pages) {
        echo '<li class="page-item"><a class="page-link" rel="next" href="' . e($path . query_with(array('page' => $page + 1))) . '"><span class="visually-hidden">Next page</span><i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>';
    }
    echo '</ul></nav>';
}

function result_range($total, $per, $page, $noun)
{
    if ($total === 0) {
        return '';
    }
    $from = ($page - 1) * $per + 1;
    $to = min($total, $page * $per);
    return 'Showing ' . number_format($from) . '–' . number_format($to) . ' of ' . plural($total, $noun);
}

function empty_state($title, $text, $actions = array(), $icon = 'bi-search')
{
    echo '<div class="empty-state"><i class="bi ' . e($icon) . '" aria-hidden="true"></i><h2 class="h5">' . e($title) . '</h2><p>' . e($text) . '</p>';
    if ($actions) {
        echo '<div class="d-flex flex-wrap gap-2 justify-content-center">';
        foreach ($actions as $i => $a) {
            echo '<a class="btn ' . ($i === 0 ? 'btn-primary' : 'btn-outline-primary') . '" href="' . e(url($a[1])) . '">' . e($a[0]) . '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function page_hero($title, $sub = '', $crumbs = array())
{
    echo '<section class="page-hero"><div class="container-xl">';
    if ($crumbs) {
        breadcrumb($crumbs);
    }
    echo '<h1>' . e($title) . '</h1>';
    if ($sub !== '') {
        echo '<p class="lead">' . e($sub) . '</p>';
    }
    echo '</div></section>';
}

/** City select (Select2 with AJAX search). */
function city_select($name, $selected = null, $placeholder = 'Any city', $id = '')
{
    $id = $id !== '' ? $id : $name . '-select';
    echo '<select class="form-select" name="' . e($name) . '" id="' . e($id) . '" data-city-select data-placeholder="' . e($placeholder) . '">';
    echo '<option value="">' . e($placeholder) . '</option>';
    if ($selected) {
        echo '<option value="' . (int)$selected['city_id'] . '" selected>' . e($selected['city_title'] . (!empty($selected['state_title']) ? ', ' . $selected['state_title'] : '')) . '</option>';
    }
    echo '</select>';
}

function blog_card($b)
{
    $date = !empty($b['updated_date']) ? $b['updated_date'] : $b['created_at'];
    $img = blog_image_url(isset($b['profile_image']) ? $b['profile_image'] : '');
    ?>
<article class="blog-card h-100">
  <div class="blog-card-cover<?= $img !== '' ? ' has-img' : '' ?>" aria-hidden="true">
    <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="" loading="lazy" onerror="this.parentNode.classList.remove('has-img');this.remove()"><?php endif; ?>
    <span class="prov"><?= e(trim((string)$b['state_title'])) ?></span>
    <span class="city"><?= e(trim((string)($b['city_title'] !== null && $b['city_title'] !== '' ? $b['city_title'] : 'The Hashir'))) ?></span>
  </div>
  <div class="blog-card-body">
    <h3><a class="stretched-link" href="<?= e(url(blog_url($b))) ?>"><?= e($b['heading']) ?></a></h3>
    <p class="small text-muted mb-0"><?= e(excerpt($b['quick_answer_text'], 120)) ?></p>
    <p class="meta"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Updated <?= e(fmt_date($date, 'j M Y')) ?> · <?= e($b['author']) ?></p>
  </div>
</article>
<?php
}

function role_icon($roleId)
{
    $map = array(1 => 'bi-building', 2 => 'bi-bank', 3 => 'bi-mortarboard', 4 => 'bi-heart', 5 => 'bi-megaphone',
        6 => 'bi-patch-check', 7 => 'bi-basket', 8 => 'bi-people', 9 => 'bi-book');
    return isset($map[(int)$roleId]) ? $map[(int)$roleId] : 'bi-building';
}
