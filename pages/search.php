<?php
/**
 * Global search: queries products across every marketplace type in a
 * single pass, so results carry their own marketplace badge
 * (Handmade / Business Shop / Official Store) regardless of source.
 */

$term = trim($_GET['q'] ?? '');
$results = $term !== '' ? mp_search_products($term) : [];

$pageTitle = $term !== '' ? "Search results for \"{$term}\"" : 'Search';
$theme = 'main';
require __DIR__ . '/../includes/header.php';
?>

<h1><?= $term !== '' ? 'Search results for "' . mp_e($term) . '"' : 'Search' ?></h1>

<?php if ($term === ''): ?>
    <p>Enter a search term above to find products across every marketplace.</p>
<?php elseif (!$results): ?>
    <p>No products found for "<?= mp_e($term) ?>".</p>
<?php else: ?>
    <p><?= count($results) ?> result(s) — badges show which marketplace each item comes from.</p>
    <div class="card-grid">
        <?php foreach ($results as $product): ?>
            <?php mp_render_product_card($product); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
