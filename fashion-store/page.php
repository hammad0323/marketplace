<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = mysqli_prepare($mysqli, "SELECT * FROM pages WHERE slug = ? AND status = 'active'");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$page = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$page) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$pageTitle = ($page['seo_title'] ?: $page['title']) . ' | ' . get_setting('store_name');
$pageDescription = $page['seo_description'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight" style="max-width:820px">
  <h1 class="font-serif mb-4"><?= e($page['title']) ?></h1>
  <div class="page-content"><?= $page['content'] ?></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
