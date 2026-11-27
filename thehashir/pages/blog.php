<?php
/**
 * Blog article  /blog/{url}/{id}
 * Preview (Super Admin only)  /blog-preview/{key}
 *
 * - Existing blogs without a builder layout show their original city-guide content.
 * - Blogs edited in the builder show their blocks; the "City guide (original content)" block keeps the old content.
 */
$preview = !empty($ROUTE['preview']);
if ($preview) {
    if (!current_admin()) {
        route_page('404');
    }
    $doc = blog_doc_key_ok($ROUTE['key']) ? storage_read('blog_previews/' . $ROUTE['key'] . '.json', null) : null;
    if (!is_array($doc)) {
        $doc = blog_doc($ROUTE['key']);
    }
    if (!$doc) {
        route_page('404');
    }
    $b = !empty($doc['blog_id']) ? get_blog((int)$doc['blog_id']) : null;
    if (!$b) {
        // Unsaved draft: build a row-shaped array from the draft
        $b = array('id' => 0, 'heading' => $doc['heading'], 'url' => $doc['slug'], 'author' => $doc['author'], 'quick_answer_text' => '',
            'city_id' => (int)$doc['city_id'], 'city_title' => '', 'state_title' => '', 'country_title' => '', 'population' => '', 'map_url' => '',
            'summary_text' => '', 'profile_image' => $doc['featured_image'], 'updated_date' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'));
    }
} else {
    $b = get_blog($ROUTE['id']);
    if (!$b) {
        route_page('404');
    }
    $doc = blog_doc((string)(int)$b['id']);
    if (!blog_doc_live($doc)) {
        route_page('404');
    }
    if (slug_key($ROUTE['slug']) !== slug_key($b['url'])) {
        redirect(blog_url($b));
    }
}

$cityId = (int)$b['city_id'];
$cityName = trim((string)$b['city_title']);
$date = !empty($b['updated_date']) ? $b['updated_date'] : $b['created_at'];
if ($doc && !empty($doc['updated_at']) && strtotime($doc['updated_at']) > strtotime($date)) {
    $date = $doc['updated_at'];
}
$heading = $doc ? $doc['heading'] : $b['heading'];
$author = $doc && $doc['author'] !== '' ? $doc['author'] : $b['author'];
$blocks = $doc ? (array)$doc['blocks'] : array(array('id' => 'legacy', 'type' => 'legacy_guide', 'data' => array()));
$hasLegacy = false;
foreach ($blocks as $bl) {
    if (isset($bl['type']) && $bl['type'] === 'legacy_guide') {
        $hasLegacy = true;
    }
}
$g = $hasLegacy ? guide_data($b) : null;
$seo = $doc && isset($doc['seo']) ? $doc['seo'] : array();
$featuredImg = blog_image_url($doc ? $doc['featured_image'] : $b['profile_image']);
$ogImage = !empty($seo['og_image']) ? blog_image_url($seo['og_image']) : $featuredImg;
if ($ogImage === '' && $g && $g['featured']) {
    $ogImage = venue_cover($g['featured'][0]);
}
$desc = !empty($seo['description']) ? $seo['description'] : ($doc && $doc['excerpt'] !== '' ? $doc['excerpt'] : clean_text($b['quick_answer_text']));
$canonical = $preview ? '/blogs' : blog_url($b);
if (!empty($seo['canonical'])) {
    $canonical = $seo['canonical'];
}

$faqEntries = blocks_faq_entries($blocks);
if ($g) {
    $faqEntries = array_merge($g['faq'], $faqEntries);
}
$jsonld = array(array(
    '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $heading,
    'datePublished' => date('c', strtotime($b['created_at'])), 'dateModified' => date('c', strtotime($date)),
    'author' => array('@type' => 'Organization', 'name' => $author),
    'publisher' => array('@type' => 'Organization', 'name' => SITE_NAME, 'logo' => array('@type' => 'ImageObject', 'url' => abs_url('/images/logos/hashirLogo.png'))),
    'mainEntityOfPage' => preg_match('#^https?://#', $canonical) ? $canonical : abs_url($canonical),
));
if ($ogImage) {
    $jsonld[0]['image'] = $ogImage;
}
if ($faqEntries) {
    $ldFaq = array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array());
    foreach ($faqEntries as $q) {
        $ldFaq['mainEntity'][] = array('@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $q[1]));
    }
    $jsonld[] = $ldFaq;
}

// Table of contents
$toc = array();
foreach ($blocks as $bl) {
    if (!isset($bl['type'])) {
        continue;
    }
    if ($bl['type'] === 'legacy_guide' && $g) {
        $toc[] = array('id' => 'quick-answer', 'text' => 'Quick answer', 'level' => 2);
        if ($g['mapSrc']) { $toc[] = array('id' => 'map', 'text' => 'Map of mosques in ' . $cityName, 'level' => 2); }
        if ($g['featured']) { $toc[] = array('id' => 'mosques', 'text' => 'Featured mosques', 'level' => 2); }
        if ($g['compare']) { $toc[] = array('id' => 'compare', 'text' => 'What each mosque offers', 'level' => 2); }
        if ($g['hasSummary']) { $toc[] = array('id' => 'summary', 'text' => 'Summary', 'level' => 2); }
        if ($g['faq']) { $toc[] = array('id' => 'faq', 'text' => 'Frequently asked questions', 'level' => 2); }
    } else {
        foreach (blocks_toc(array($bl)) as $t) {
            $toc[] = $t;
        }
    }
}

page_start(array(
    'title' => (!empty($seo['title']) ? $seo['title'] : $heading . ' | The Hashir'),
    'description' => excerpt($desc, 158),
    'canonical' => $canonical,
    'type' => 'article',
    'image' => $ogImage,
    'noindex' => $preview || !empty($seo['noindex']),
    'jsonld' => $jsonld,
    'active' => 'community',
));
$crumbs = array(array('Home', '/'), array('Blogs', '/blogs'), array($cityName !== '' && $hasLegacy ? $cityName : $heading, null));
?>
<?php if ($preview): ?>
<div class="preview-bar"><i class="bi bi-eye" aria-hidden="true"></i> Preview — <?= e(ucfirst($doc['status'])) ?><?= $doc['status'] === 'scheduled' ? ' for ' . e($doc['publish_at']) : '' ?>. Visitors can't see this page<?= $doc['status'] === 'published' ? ' version until you save' : '' ?>.
  <a href="<?= e(url(ADMIN_PATH . '/blog-edit?key=' . rawurlencode($ROUTE['key']))) ?>">Back to the editor</a></div>
<?php endif; ?>
<div class="container-xl pt-3">
  <?php breadcrumb($crumbs); ?>
  <div class="row g-5 mt-0">
    <article class="col-lg-8 article">
      <h1 class="fw-bold mt-2 mb-3"><?= e($heading) ?></h1>
      <p class="byline">
        <span><i class="bi bi-calendar3" aria-hidden="true"></i>Updated <?= e(fmt_date($date, 'j F Y')) ?></span>
        <span><i class="bi bi-person" aria-hidden="true"></i><?= e($author) ?></span>
        <?php if ($g && $g['cityName'] !== ''): ?><span><i class="bi bi-building" aria-hidden="true"></i><?= e(plural($g['count'], 'mosque')) ?> listed</span><?php endif; ?>
      </p>
      <?php if ($featuredImg !== ''): ?>
      <figure class="article-hero"><img src="<?= e($featuredImg) ?>" alt="<?= e($heading) ?>" fetchpriority="high" onerror="this.parentNode.remove()"></figure>
      <?php endif; ?>
      <?php if ($doc && $doc['excerpt'] !== '' && !$hasLegacy): ?><p class="lead article-lead"><?= e($doc['excerpt']) ?></p><?php endif; ?>

      <?php if (count($toc) > 2): ?>
      <nav class="toc mt-4" aria-label="On this page">
        <strong>On this page</strong>
        <ol><?php foreach ($toc as $t): ?><li class="<?= $t['level'] === 3 ? 'sub' : '' ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['text']) ?></a></li><?php endforeach; ?></ol>
      </nav>
      <?php endif; ?>

      <?php render_blocks($blocks, array('blog_id' => (int)$b['id'], 'blog_row' => $b)); ?>
    </article>

    <aside class="col-lg-4">
      <div class="side-card" style="position:sticky;top:96px">
        <?php if ($cityId && $cityName !== ''): $count = $g ? $g['count'] : (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1 AND role_id = 1 AND city_id = ?', 'i', array($cityId)); $cityObj = get_city($cityId); ?>
        <h2>In <?= e($cityName) ?></h2>
        <div class="d-grid gap-2">
          <a class="btn btn-primary" href="<?= e(url('/mosques?city=' . $cityId)) ?>"><i class="bi bi-building me-2" aria-hidden="true"></i>All mosques (<?= number_format($count) ?>)</a>
          <?php if ($cityObj): ?><a class="btn btn-outline-primary" href="<?= e(url(city_prayer_url($cityObj))) ?>"><i class="bi bi-clock me-2" aria-hidden="true"></i>Prayer &amp; Jumu'ah times</a><?php endif; ?>
          <a class="btn btn-soft" href="<?= e(url('/services/Nikah-Services/10?city=' . $cityId)) ?>">Nikah services</a>
          <a class="btn btn-soft" href="<?= e(url('/services/Funeral-Services/13?city=' . $cityId)) ?>">Funeral services</a>
        </div>
        <?php else: ?>
        <h2>Explore The Hashir</h2>
        <div class="d-grid gap-2">
          <a class="btn btn-primary" href="<?= e(url('/mosques')) ?>"><i class="bi bi-building me-2" aria-hidden="true"></i>Find a mosque</a>
          <a class="btn btn-outline-primary" href="<?= e(url('/prayer-times')) ?>"><i class="bi bi-clock me-2" aria-hidden="true"></i>Prayer times</a>
          <a class="btn btn-soft" href="<?= e(url('/program-catalog')) ?>">Programs</a>
          <a class="btn btn-soft" href="<?= e(url('/services-catalog')) ?>">Services</a>
        </div>
        <?php endif; ?>
        <button type="button" class="btn btn-link px-0 mt-3" data-share-btn data-title="<?= e($heading) ?>"><i class="bi bi-share me-1" aria-hidden="true"></i>Share this article</button>
      </div>
    </aside>
  </div>

  <?php
  $hasRelatedBlock = false;
  foreach ($blocks as $bl) {
      if (isset($bl['type']) && $bl['type'] === 'related_blogs') {
          $hasRelatedBlock = true;
      }
  }
  if (!$hasRelatedBlock):
      $hidden = blog_hidden_ids();
      $related = array();
      foreach (get_blogs() as $r) {
          if ((int)$r['id'] !== (int)$b['id'] && !isset($hidden[(int)$r['id']]) && count($related) < 6 && $r['city_title'] !== $b['city_title']) {
              $related[] = $r;
          }
      }
      if ($related): ?>
  <section class="profile-section" aria-labelledby="h-rel">
    <h2 id="h-rel">More from the blog</h2>
    <div class="row g-4 mt-1"><?php foreach ($related as $r): ?><div class="col-sm-6 col-lg-4"><?php blog_card($r); ?></div><?php endforeach; ?></div>
  </section>
  <?php endif; endif; ?>
</div>
<?php
$extra = '';
if (!empty($GLOBALS['NEEDS_LEAFLET'])) {
    $extra = '<link rel="stylesheet" href="' . e(asset('vendor/leaflet/leaflet.css')) . '"><script src="' . e(asset('vendor/leaflet/leaflet.js')) . '"></script>';
}
page_end($extra);
