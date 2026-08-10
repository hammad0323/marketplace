<?php
require_once __DIR__ . '/../config/config.php';
require ROOT_PATH . '/includes/search-query.php';

$pageTitle = $destination !== '' ? $destination : ($activeCategory['name'] ?? 'Search results');
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-search"></i> Search</span>
      <h1 class="section-heading"><?php echo $destination !== '' ? e($destination) : ($activeCategory['name'] ?? 'All services'); ?></h1>
      <?php if ($hasGeo): ?><p class="section-sub"><i class="bi bi-crosshair"></i> Sorted near your current location.</p><?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:32px;align-items:start;">
      <form id="filter-form" class="panel" style="position:sticky;top:96px;">
        <input type="hidden" name="destination" value="<?php echo e($destination); ?>">
        <?php if ($hasGeo): ?>
          <input type="hidden" name="lat" value="<?php echo e($lat); ?>">
          <input type="hidden" name="lng" value="<?php echo e($lng); ?>">
        <?php endif; ?>

        <h3 style="font-size:14px;margin-bottom:14px;">Filters</h3>

        <label style="font-size:13px;font-weight:600;">Category</label>
        <select name="category" class="filter-input" style="width:100%;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
          <option value="">All categories</option>
          <?php foreach ($allCategories as $c): ?>
            <option value="<?php echo e($c['slug']); ?>" <?php echo $categorySlug === $c['slug'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <label style="font-size:13px;font-weight:600;">City</label>
        <select name="city" class="filter-input" style="width:100%;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
          <option value="">All cities</option>
          <?php foreach ($allCities as $c): ?>
            <option value="<?php echo e($c['slug']); ?>" <?php echo $citySlug === $c['slug'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>

        <label style="font-size:13px;font-weight:600;">Price range</label>
        <div style="display:flex;gap:8px;margin-bottom:14px;">
          <input type="number" name="min_price" class="filter-input" placeholder="Min" value="<?php echo e($minPrice ?? ''); ?>" style="width:50%;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border);">
          <input type="number" name="max_price" class="filter-input" placeholder="Max" value="<?php echo e($maxPrice ?? ''); ?>" style="width:50%;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border);">
        </div>

        <label style="font-size:13px;font-weight:600;">Minimum rating</label>
        <select name="min_rating" class="filter-input" style="width:100%;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
          <option value="0" <?php echo $minRating == 0 ? 'selected' : ''; ?>>Any rating</option>
          <?php foreach ([4.5, 4, 3.5, 3] as $r): ?>
            <option value="<?php echo $r; ?>" <?php echo (float) $minRating === $r ? 'selected' : ''; ?>><?php echo $r; ?>+ stars</option>
          <?php endforeach; ?>
        </select>

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <input type="checkbox" name="verified" value="1" class="filter-input" <?php echo $verifiedOnly ? 'checked' : ''; ?> style="width:auto;"> Verified providers only
        </label>

        <button type="button" id="clear-filters" class="btn-w btn-ghost btn-sm btn-block" style="margin-top:14px;">Clear filters</button>
      </form>

      <div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
          <select name="sort" id="sort-select" style="padding:10px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
            <option value="relevance" <?php echo $sort === 'relevance' ? 'selected' : ''; ?>>Sort: Recommended</option>
            <?php if ($hasGeo): ?><option value="distance" <?php echo $sort === 'distance' ? 'selected' : ''; ?>>Sort: Nearest</option><?php endif; ?>
            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Sort: Price (low to high)</option>
            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Sort: Price (high to low)</option>
            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Sort: Top rated</option>
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Sort: Newest</option>
          </select>
        </div>
        <div id="search-results">
          <?php require ROOT_PATH . '/pages/_search-results.php'; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php $extraJs = '<script src="' . ASSETS_URL . '/js/search.js"></script>'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
