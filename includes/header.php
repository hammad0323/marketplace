<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

$siteName = get_setting($conn, 'site_name', APP_NAME);
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' — ' . $siteName : $siteName . ' — Plan, book, and explore';
$metaDescription = $metaDescription ?? get_setting($conn, 'site_tagline', 'Trip planning and multi-service travel marketplace.');
$canonicalUrl = $canonicalUrl ?? (APP_URL . ($_SERVER['REQUEST_URI'] ?? '/'));
$ogImage = !empty($ogImage) ? (strpos($ogImage, 'http') === 0 ? $ogImage : APP_URL . $ogImage) : (get_setting($conn, 'site_logo', '') ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?></title>
<meta name="description" content="<?php echo e($metaDescription); ?>">
<link rel="canonical" href="<?php echo e($canonicalUrl); ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?php echo e($siteName); ?>">
<meta property="og:title" content="<?php echo e($pageTitle); ?>">
<meta property="og:description" content="<?php echo e($metaDescription); ?>">
<meta property="og:url" content="<?php echo e($canonicalUrl); ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?php echo e($ogImage); ?>"><?php endif; ?>
<meta name="twitter:card" content="<?php echo $ogImage ? 'summary_large_image' : 'summary'; ?>">
<meta name="twitter:title" content="<?php echo e($pageTitle); ?>">
<meta name="twitter:description" content="<?php echo e($metaDescription); ?>">
<?php if (is_logged_in()): ?><meta name="csrf-token" content="<?php echo e(csrf_token()); ?>"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2224%22 fill=%22%238B5CF6%22/><text x=%2250%25%22 y=%2262%25%22 font-size=%2255%22 fill=%22white%22 text-anchor=%22middle%22 font-family=%22Arial%22>W</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
<?php if (!empty($extraCss)) echo $extraCss; ?>
</head>
<body>
<?php require ROOT_PATH . '/includes/navbar.php'; ?>
<main>
<?php
$flashSuccess = flash_get('success');
$flashDanger = flash_get('danger');
$flashInfo = flash_get('info');
if ($flashSuccess || $flashDanger || $flashInfo):
?>
<div class="container-xl" style="padding-top:20px;">
  <?php if ($flashSuccess): ?><div class="alert-w alert-success"><i class="bi bi-check-circle-fill"></i><?php echo e($flashSuccess); ?></div><?php endif; ?>
  <?php if ($flashDanger): ?><div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><?php echo e($flashDanger); ?></div><?php endif; ?>
  <?php if ($flashInfo): ?><div class="alert-w alert-info"><i class="bi bi-info-circle-fill"></i><?php echo e($flashInfo); ?></div><?php endif; ?>
</div>
<?php endif; ?>
