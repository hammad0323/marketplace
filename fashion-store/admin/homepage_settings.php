<?php
$pageTitle = 'Homepage Selector';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $home = max(1, min(10, (int)($_POST['active_home'] ?? 1)));
    mysqli_query($mysqli, "INSERT INTO homepage_settings (id, active_home) VALUES (1, $home) ON DUPLICATE KEY UPDATE active_home = $home");
    flash_set('success', 'Live homepage updated.');
    redirect('homepage_settings.php');
}

$active = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT active_home FROM homepage_settings WHERE id = 1"))['active_home'] ?? 1;
$homes = mysqli_query($mysqli, "SELECT * FROM homepage_configs ORDER BY homepage");
$heroLabels = ['slider' => 'Slider Hero', 'split' => 'Split Hero', 'centered' => 'Centered Hero', 'collage' => 'Collage Hero'];
?>
<h1 class="page-title mb-2">Homepage Selector</h1>
<p class="text-muted mb-4">Choose which of the 10 homepage designs is live on the website. Each homepage has its own hero style, banners and sections you can manage independently.</p>

<form method="post">
<?= csrf_field() ?>
<div class="row g-4">
  <?php while ($h = mysqli_fetch_assoc($homes)): $num = $h['homepage']; ?>
  <div class="col-md-6 col-lg-4 col-xl-3">
    <label class="admin-card d-block h-100" style="cursor:pointer;<?= $active == $num ? 'border-color:var(--admin-primary);box-shadow:0 0 0 2px var(--admin-primary)' : '' ?>">
      <input type="radio" name="active_home" value="<?= $num ?>" class="form-check-input mb-2" <?= $active == $num ? 'checked' : '' ?>>
      <h2 class="h6">Home <?= (int)$num ?> &mdash; <?= e($h['name']) ?> <?= $active == $num ? '<span class="badge text-bg-success">Live</span>' : '' ?></h2>
      <p class="small text-muted mb-1"><span class="badge text-bg-light border"><?= e($heroLabels[$h['hero_style']] ?? $h['hero_style']) ?></span></p>
      <p class="small text-muted mb-2"><?= e($h['description']) ?></p>
      <div class="d-flex gap-2">
        <a href="banners.php?homepage=<?= $num ?>" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">Banners</a>
        <a href="homepage_sections.php?homepage=<?= $num ?>" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">Sections</a>
      </div>
    </label>
  </div>
  <?php endwhile; ?>
</div>
<button class="btn btn-primary text-white mt-4"><i class="bi bi-check-lg"></i> Set as Live Homepage</button>
</form>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
