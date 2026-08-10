<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$booking = db_select_one(
    $conn,
    'SELECT b.*, s.title AS service_title, s.slug AS service_slug FROM bookings b JOIN services s ON s.id = b.service_id WHERE b.id = ? AND b.customer_id = ?',
    [$bookingId, (int) $user['id']]
);

if (!$booking || $booking['status'] !== 'completed') {
    flash_set('danger', 'You can only review completed bookings.');
    redirect('/customer/bookings.php');
}

$existingReview = db_select_one($conn, 'SELECT id FROM reviews WHERE booking_id = ?', [$bookingId]);
if ($existingReview) {
    flash_set('info', 'You already reviewed this booking.');
    redirect('/customer/bookings.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rating = (int) ($_POST['rating'] ?? 0);
    $title = clean_input($_POST['title'] ?? '');
    $text = clean_input($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please choose a rating from 1 to 5.';
    }
    if (mb_strlen($text) < 10) {
        $errors[] = 'Please write at least a short sentence about your experience.';
    }

    if (!$errors) {
        $reviewId = db_insert_get_id(
            $conn,
            'INSERT INTO reviews (service_id, booking_id, customer_id, rating, title, review_text, status) VALUES (?,?,?,?,?,?, "pending")',
            [(int) $booking['service_id'], $bookingId, (int) $user['id'], $rating, $title, $text]
        );
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $i => $name) {
                if ($name === '') {
                    continue;
                }
                $_FILES['__single_image'] = [
                    'name' => $_FILES['images']['name'][$i], 'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i], 'size' => $_FILES['images']['size'][$i],
                ];
                $upload = upload_file('__single_image', 'reviews');
                if ($upload['ok']) {
                    db_execute($conn, 'INSERT INTO review_images (review_id, image_path) VALUES (?, ?)', [$reviewId, $upload['path']]);
                }
            }
        }
        flash_set('success', 'Thanks! Your review is pending moderation and will appear once approved.');
        redirect('/customer/bookings.php');
    }
}

$pageTitle = 'Leave a Review';
$customerActiveTab = 'bookings';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:560px;">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-star"></i> Review</span>
      <h1 class="section-heading"><?php echo e($booking['service_title']); ?></h1>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-w panel" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <label>Your rating</label>
      <div class="star-picker" style="display:flex;gap:6px;font-size:28px;color:var(--border);margin-bottom:6px;">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="bi bi-star-fill star-choice" data-value="<?php echo $i; ?>" style="cursor:pointer;"></i>
        <?php endfor; ?>
      </div>
      <input type="hidden" name="rating" id="rating-input" value="0" required>

      <label>Title (optional)</label>
      <input type="text" name="title" maxlength="150">

      <label>Your review</label>
      <textarea name="review_text" rows="5" required></textarea>

      <label>Photos (optional)</label>
      <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>

      <button type="submit" class="btn-w btn-primary" style="margin-top:20px;">Submit review</button>
    </form>
  </div>
</div>
<script>
(function () {
  var stars = document.querySelectorAll('.star-choice');
  var input = document.getElementById('rating-input');
  stars.forEach(function (star) {
    star.addEventListener('click', function () {
      var val = parseInt(star.getAttribute('data-value'), 10);
      input.value = val;
      stars.forEach(function (s, i) {
        s.style.color = (i < val) ? '#F59E0B' : 'var(--border)';
      });
    });
  });
})();
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
