<?php
/**
 * Global search: queries products across every marketplace type in a
 * single pass, so results carry their own marketplace badge
 * (Handmade / Business Shop / Official Store) regardless of source.
 */

$term = trim($_GET['q'] ?? '');
$results = $term !== '' ? search_products($term) : [];

$pageTitle = $term !== '' ? "Search results for \"{$term}\"" : 'Search';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<h1><?= $term !== '' ? 'Search results for "' . e($term) . '"' : 'Search' ?></h1>

<?php if ($term === ''): ?>
    <p>Enter a search term above to find products across every marketplace.</p>
<?php elseif (!$results): ?>
    <p>No products found for "<?= e($term) ?>".</p>
<?php else: ?>
    <p><?= count($results) ?> result(s) — badges show which marketplace each item comes from.</p>
    <div class="card-grid">
        <?php foreach ($results as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
