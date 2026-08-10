<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $review = db_select_one($conn, 'SELECT * FROM reviews WHERE id = ?', [$reviewId]);
    if (!$review) {
        flash_set('danger', 'Review not found.');
        redirect('/admin/reviews.php');
    }

    if ($action === 'approve') {
        db_execute($conn, 'UPDATE reviews SET status = "approved" WHERE id = ?', [$reviewId]);
        recalculate_service_rating($conn, (int) $review['service_id']);
        db_execute($conn, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "review_approved", "Review published", "Thanks for sharing your experience!", "/customer/bookings.php")', [(int) $review['customer_id']]);
        $msg = 'Review approved.';
    } elseif ($action === 'reject') {
        db_execute($conn, 'UPDATE reviews SET status = "rejected" WHERE id = ?', [$reviewId]);
        recalculate_service_rating($conn, (int) $review['service_id']);
        $msg = 'Review rejected.';
    } elseif ($action === 'delete') {
        db_execute($conn, 'DELETE FROM reviews WHERE id = ?', [$reviewId]);
        recalculate_service_rating($conn, (int) $review['service_id']);
        $msg = 'Review deleted.';
    } else {
        flash_set('danger', 'Invalid action.');
        redirect('/admin/reviews.php');
    }
    log_audit($conn, (int) $admin['id'], 'review', $reviewId, $action);
    flash_set('success', $msg);
    redirect('/admin/reviews.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$statusFilter = clean_input($_GET['status'] ?? 'pending');
$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}

$reviews = db_select(
    $conn,
    'SELECT r.*, s.title AS service_title, u.name AS customer_name
     FROM reviews r JOIN services s ON s.id = r.service_id JOIN users u ON u.id = r.customer_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC LIMIT 50',
    $params
);

$adminPageTitle = 'Reviews';
$adminActive = 'reviews';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head">
    <h3>Reviews</h3>
    <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
      <?php foreach (['pending' => 'Pending', '' => 'All', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
        <option value="<?php echo $val; ?>" <?php echo $statusFilter === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($reviews): ?>
    <?php foreach ($reviews as $r): ?>
      <div style="border-bottom:1px solid var(--border);padding:16px 0;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <strong><?php echo e($r['customer_name']); ?></strong> on <em><?php echo e($r['service_title']); ?></em>
            <div class="card-rating" style="margin-top:4px;"><i class="bi bi-star-fill"></i> <?php echo (int) $r['rating']; ?>/5</div>
          </div>
          <?php echo status_badge($r['status']); ?>
        </div>
        <?php if ($r['title']): ?><div style="font-weight:600;margin-top:8px;"><?php echo e($r['title']); ?></div><?php endif; ?>
        <p style="color:var(--ink-soft);font-size:14px;margin-top:4px;"><?php echo e($r['review_text']); ?></p>
        <?php if ($r['status'] === 'pending'): ?>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn-w btn-primary btn-sm">Approve</button></form>
            <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn-w btn-outline btn-sm">Reject</button></form>
          </div>
        <?php else: ?>
          <form method="post" style="margin-top:10px;" onsubmit="return confirm('Delete this review?');"><?php echo csrf_field(); ?><input type="hidden" name="review_id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="delete"><button class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i> Delete</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-star"></i></div><h4>No reviews here</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
