<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/section_renderer.php';

$activeHome = (int)(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT active_home FROM homepage_settings WHERE id = 1"))['active_home'] ?? 1);

$config = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM homepage_configs WHERE homepage = $activeHome"));
$heroStyle = $config['hero_style'] ?? 'slider';

$bannerRes = mysqli_query($mysqli, "SELECT * FROM banners WHERE homepage = $activeHome AND status = 'active' ORDER BY sort_order");
$banners = [];
while ($b = mysqli_fetch_assoc($bannerRes)) $banners[] = $b;

$sectionRes = mysqli_query($mysqli, "SELECT * FROM homepage_sections WHERE homepage = $activeHome AND status = 'active' ORDER BY sort_order");
?>

<?php render_hero($mysqli, $heroStyle, $banners); ?>

<?php while ($section = mysqli_fetch_assoc($sectionRes)): ?>
  <?php render_homepage_section($mysqli, $section); ?>
<?php endwhile; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
