<h1><?= $term !== '' ? 'Search results for "' . e($term) . '"' : 'Search' ?></h1>

<?php if ($term === ''): ?>
    <p>Enter a search term above to find products across every marketplace.</p>
<?php elseif (!$results): ?>
    <p>No products found for "<?= e($term) ?>".</p>
<?php else: ?>
    <p><?= count($results) ?> result(s) — badges show which marketplace each item comes from.</p>
    <div class="card-grid">
        <?php foreach ($results as $product): ?>
            <?php View::partial('partials/product_card', ['product' => $product]); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
