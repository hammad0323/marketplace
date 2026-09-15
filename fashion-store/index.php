<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/section_renderer.php';

$activeHome = (int)(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT active_home FROM homepage_settings WHERE id = 1"))['active_home'] ?? 1);

$bannerRes = mysqli_query($mysqli, "SELECT * FROM banners WHERE homepage = $activeHome AND status = 'active' ORDER BY sort_order");
$banners = [];
while ($b = mysqli_fetch_assoc($bannerRes)) $banners[] = $b;

$sectionRes = mysqli_query($mysqli, "SELECT * FROM homepage_sections WHERE homepage = $activeHome AND status = 'active' ORDER BY sort_order");
?>

<?php if ($banners): ?>
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
                <?php if ($b['button_text']): ?><a href="<?= BASE_URL ?>/<?= e($b['button_url'] ?: 'shop.php') ?>" class="btn-brand"><?= e($b['button_text']) ?></a><?php endif; ?>
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
<?php else: ?>
<section class="hero-slide" style="background-image:url('<?= BASE_URL ?>/assets/img/banner-placeholder.svg')">
  <div class="container">
    <div class="hero-content" data-aos="fade-right">
      <h1><?= e(get_setting('store_tagline', 'Premium Pakistani Fashion')) ?></h1>
      <p>Discover our latest collection of unstitched, ready-to-wear and festive fashion.</p>
      <a href="<?= BASE_URL ?>/shop.php" class="btn-brand">Shop Now</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php while ($section = mysqli_fetch_assoc($sectionRes)): ?>
  <?php render_homepage_section($mysqli, $section); ?>
<?php endwhile; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
