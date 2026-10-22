<?php
/** Listing filters. Expects $facets, $filters, $category (nullable). */
$open = fn(array $vals) => $vals ? ' open' : '';
?>
<?php if ($category):
    $children = category_children((int) $category['id']);
    if ($children): ?>
    <details class="uc-filter" open>
        <summary>Category</summary>
        <div class="uc-filter-body">
            <?php foreach ($children as $c): ?>
                <label class="uc-check"><input type="radio" name="subcategory" value="<?= (int) $c['id'] ?>"<?= (int) ($filters['subcategory'] ?? 0) === (int) $c['id'] ? ' checked' : '' ?>> <span><?= e($c['name']) ?></span></label>
            <?php endforeach; ?>
            <?php if (!empty($filters['subcategory'])): ?><label class="uc-check"><input type="radio" name="subcategory" value=""> <span>All</span></label><?php endif; ?>
        </div>
    </details>
<?php endif; endif; ?>

<details class="uc-filter"<?= ($filters['min_price'] !== null || $filters['max_price'] !== null) ? ' open' : '' ?> open>
    <summary>Price</summary>
    <div class="uc-filter-body uc-price-range">
        <label><span><?= e(setting('currency_symbol', 'Rs.')) ?></span><input type="number" name="min_price" min="0" step="100" inputmode="numeric" placeholder="<?= (int) $facets['price']['min'] ?>" value="<?= $filters['min_price'] !== null ? (int) $filters['min_price'] : '' ?>" aria-label="Minimum price"></label>
        <span class="uc-range-sep">–</span>
        <label><span><?= e(setting('currency_symbol', 'Rs.')) ?></span><input type="number" name="max_price" min="0" step="100" inputmode="numeric" placeholder="<?= (int) $facets['price']['max'] ?>" value="<?= $filters['max_price'] !== null ? (int) $filters['max_price'] : '' ?>" aria-label="Maximum price"></label>
    </div>
</details>

<?php if ($facets['sizes']): ?>
<details class="uc-filter"<?= $open($filters['sizes']) ?> open>
    <summary>Size</summary>
    <div class="uc-filter-body uc-size-grid">
        <?php foreach ($facets['sizes'] as $sz): ?>
            <label class="uc-size-pill"><input type="checkbox" name="size[]" value="<?= e($sz) ?>"<?= in_array($sz, $filters['sizes'], true) ? ' checked' : '' ?>><span><?= e($sz) ?></span></label>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php if ($facets['colors']): ?>
<details class="uc-filter"<?= $open($filters['colors']) ?> open>
    <summary>Colour</summary>
    <div class="uc-filter-body uc-color-grid">
        <?php foreach ($facets['colors'] as $c): ?>
            <label class="uc-color-opt" title="<?= e($c['name']) ?>"><input type="checkbox" name="color[]" value="<?= e($c['name']) ?>"<?= in_array($c['name'], $filters['colors'], true) ? ' checked' : '' ?>><span class="uc-swatch-dot" style="--sw:<?= e(safe_color($c['hex'], '#cccccc')) ?>"></span><small><?= e($c['name']) ?></small></label>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php foreach (filter_attribute_columns() as $col => $label): if (empty($facets[$col])) continue; ?>
<details class="uc-filter"<?= $open($filters[$col]) ?>>
    <summary><?= e($label) ?></summary>
    <div class="uc-filter-body">
        <?php foreach ($facets[$col]['values'] as $v): ?>
            <label class="uc-check"><input type="checkbox" name="<?= e($col) ?>[]" value="<?= e($v) ?>"<?= in_array($v, $filters[$col], true) ? ' checked' : '' ?>> <span><?= e($v) ?></span></label>
        <?php endforeach; ?>
    </div>
</details>
<?php endforeach; ?>

<details class="uc-filter" open>
    <summary>Availability</summary>
    <div class="uc-filter-body">
        <label class="uc-check"><input type="checkbox" name="in_stock" value="1"<?= $filters['in_stock'] ? ' checked' : '' ?>> <span>In stock only</span></label>
        <label class="uc-check"><input type="checkbox" name="on_sale" value="1"<?= !empty($_GET['on_sale']) ? ' checked' : '' ?>> <span>On sale</span></label>
    </div>
</details>
