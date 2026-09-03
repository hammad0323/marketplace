<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();

$reviews = db_fetch_all("SELECT r.*, p.name as product_name, p.slug, p.main_image FROM reviews r JOIN products p ON p.id=r.product_id
    WHERE r.customer_id=? ORDER BY r.created_at DESC", 'i', [$customer['id']]);

$dashRole = 'customer'; $pageTitle = 'My Reviews'; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>My Reviews</h1></div>
<div class="dash-table-card">
  <?php if ($reviews): foreach ($reviews as $r): ?>
    <div class="review-item">
      <div class="review-head">
        <a href="<?= base_url('product.php?slug=' . $r['slug']) ?>"><?= clean($r['product_name']) ?></a>
        <span><?php for ($i=1;$i<=5;$i++): ?><i class="fa-<?= $i<=$r['rating']?'solid':'regular' ?> fa-star"></i><?php endfor; ?></span>
      </div>
      <p><?= clean($r['title']) ?></p>
      <p><?= clean($r['comment']) ?></p>
      <small class="text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?> — <span class="badge badge-<?= $r['status']==='approved'?'success':'warn' ?>"><?= clean($r['status']) ?></span></small>
    </div>
  <?php endforeach; else: ?><div class="empty-state"><h3>You haven't written any reviews yet</h3></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
