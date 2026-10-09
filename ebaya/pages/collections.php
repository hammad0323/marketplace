<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
$cols = db_all("SELECT * FROM collections WHERE status = 'active' ORDER BY sort_order, id");
seo_set(['title' => 'Collections', 'description' => 'Explore Ebaya collections organised by handcrafted detail — crochet flowers, floral embroidery, statement sleeves and more.', 'canonical' => abs_url('collections')]);
seo_breadcrumbs([['Collections', null]]);
require ROOT_PATH . '/templates/header.php';
?>
<section class="listing-hero"><div class="container-eb">
  <?php include ROOT_PATH . '/templates/breadcrumbs.php'; ?>
  <h1 class="page-title" data-reveal>Collections</h1>
  <p class="page-intro" data-reveal>Curated by craft and detail.</p>
</div></section>
<section class="page-section pt-0"><div class="container-eb"><div class="row g-4">
  <?php foreach ($cols as $i => $c): ?>
    <div class="col-6 col-lg-4" data-reveal style="--d:<?= ($i % 3) * 80 ?>ms">
      <a class="craft-card" href="<?= e(collection_url($c)) ?>">
        <span class="craft-img"><img src="<?= e(img_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"></span>
        <span class="craft-body"><span class="craft-title"><?= e($c['name']) ?></span><?php if ($c['description']): ?><span class="craft-desc"><?= e(str_limit($c['description'], 110)) ?></span><?php endif; ?><span class="craft-link">Explore <i class="bi bi-arrow-right"></i></span></span>
      </a>
    </div>
  <?php endforeach; ?>
</div></div></section>
<?php require ROOT_PATH . '/templates/footer.php';
