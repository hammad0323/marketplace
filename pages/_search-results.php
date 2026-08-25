<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
?>
<div class="section-sub" style="margin-bottom:20px;"><?php echo (int) $pg['total']; ?> result<?php echo $pg['total'] === 1 ? '' : 's'; ?> found.</div>

<?php if ($results): ?>
  <div class="provider-grid stagger reveal in-view">
    <?php foreach ($results as $svc): ?>
      <div class="service-card">
        <div class="thumb">
          <?php if ($svc['is_featured']): ?><span class="badge-pill"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
          <?php echo render_fav_button($conn, 'service', $svc['id']); ?>
          <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($svc['slug']); ?>"><?php if ($svc['cover']): ?><img src="<?php echo e($svc['cover']); ?>" alt="<?php echo e($svc['title']); ?>"><?php endif; ?></a>
        </div>
        <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($svc['slug']); ?>" class="card-body" style="display:block;">
          <div class="card-meta"><i class="bi <?php echo e($svc['category_icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($svc['category_name']); ?><?php if ($svc['is_verified']): ?> · <i class="bi bi-patch-check-fill" style="color:var(--purple);"></i> Verified<?php endif; ?></div>
          <div class="card-title"><?php echo e($svc['title']); ?></div>
          <div class="card-meta">
            <i class="bi bi-geo-alt"></i> <?php echo e($svc['city_name'] ?? ''); ?>
            <?php if (isset($svc['distance_km'])): ?> · <?php echo number_format((float) $svc['distance_km'], 1); ?> km away<?php endif; ?>
          </div>
          <div class="card-footer-row">
            <div class="price-tag"><?php echo format_price($svc['price']); ?> <?php if ($svc['price_unit'] !== 'fixed'): ?><span>/ <?php echo e($svc['price_unit']); ?></span><?php endif; ?></div>
            <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $svc['avg_rating'], 1); ?></div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pg['total_pages'] > 1): ?>
    <div class="search-pagination" style="display:flex;gap:6px;justify-content:center;margin-top:32px;">
      <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
        <a href="#" data-page="<?php echo $i; ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?> page-link"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="empty-state">
    <div class="icon-wrap"><i class="bi bi-search"></i></div>
    <h4>No results match these filters</h4>
    <p>Try widening your price range or clearing a filter.</p>
  </div>
<?php endif; ?>
