<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $response = clean_input($_POST['provider_response'] ?? '');
    $review = db_select_one($conn, 'SELECT r.* FROM reviews r JOIN services s ON s.id = r.service_id WHERE r.id = ? AND s.provider_id = ?', [$reviewId, (int) $provider['id']]);
    if ($review) {
        db_execute($conn, 'UPDATE reviews SET provider_response = ? WHERE id = ?', [$response, $reviewId]);
        flash_set('success', 'Response saved.');
    }
    redirect('/provider/reviews.php');
}

$reviews = db_select(
    $conn,
    'SELECT r.*, s.title AS service_title, u.name AS customer_name
     FROM reviews r JOIN services s ON s.id = r.service_id JOIN users u ON u.id = r.customer_id
     WHERE s.provider_id = ? AND r.status = "approved" ORDER BY r.created_at DESC',
    [(int) $provider['id']]
);

$pageTitle = 'Reviews';
$providerActiveTab = 'reviews';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-star"></i> Provider</span>
      <h1 class="section-heading">Reviews</h1>
      <p class="section-sub">Average rating: <i class="bi bi-star-fill" style="color:#F59E0B;"></i> <?php echo number_format((float) $provider['avg_rating'], 1); ?> (<?php echo (int) $provider['review_count']; ?> reviews)</p>
    </div>

    <?php if ($reviews): ?>
      <?php foreach ($reviews as $r): ?>
        <div class="panel">
          <div style="display:flex;justify-content:space-between;">
            <strong><?php echo e($r['customer_name']); ?></strong>
            <span class="card-rating"><i class="bi bi-star-fill"></i> <?php echo (int) $r['rating']; ?>/5</span>
          </div>
          <div class="card-meta" style="margin-top:2px;"><?php echo e($r['service_title']); ?></div>
          <?php if ($r['title']): ?><div style="font-weight:600;margin-top:8px;"><?php echo e($r['title']); ?></div><?php endif; ?>
          <p style="color:var(--ink-soft);font-size:14px;margin-top:4px;"><?php echo e($r['review_text']); ?></p>

          <?php if ($r['provider_response']): ?>
            <div style="background:var(--purple-50);border-radius:10px;padding:12px 14px;margin-top:10px;">
              <strong style="font-size:12.5px;color:var(--purple-600);">Your response</strong>
              <p style="font-size:13.5px;margin-top:4px;"><?php echo e($r['provider_response']); ?></p>
            </div>
          <?php else: ?>
            <form method="post" style="margin-top:12px;display:flex;gap:8px;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>">
              <input type="text" name="provider_response" placeholder="Write a public response…" style="flex:1;padding:9px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:13.5px;">
              <button type="submit" class="btn-w btn-outline btn-sm">Respond</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-star"></i></div><h4>No reviews yet</h4></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
