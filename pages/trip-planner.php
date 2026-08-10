<?php
require_once __DIR__ . '/../config/config.php';

$cities = db_select($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        $_SESSION['pending_trip'] = $_POST;
        redirect('/customer/login.php?redirect=' . urlencode('/pages/trip-planner.php?resume=1'));
    }
    verify_csrf();
    $cityId = (int) ($_POST['city_id'] ?? 0);
    $dateFrom = clean_input($_POST['date_from'] ?? '');
    $dateTo = clean_input($_POST['date_to'] ?? '');
    $adults = max(1, (int) ($_POST['adults'] ?? 1));
    $children = max(0, (int) ($_POST['children'] ?? 0));
    $budgetMode = in_array($_POST['budget_mode'] ?? '', ['economy', 'standard', 'luxury', 'custom'], true) ? $_POST['budget_mode'] : 'standard';
    $maxBudget = $_POST['max_budget'] !== '' ? (float) ($_POST['max_budget'] ?? 0) : null;

    if (!$cityId) {
        $errors[] = 'Please choose a destination.';
    }

    if (!$errors) {
        $city = db_select_one($conn, 'SELECT name FROM cities WHERE id = ?', [$cityId]);
        $tripId = db_insert_get_id(
            $conn,
            'INSERT INTO trips (user_id, trip_name, destination_city_id, date_from, date_to, adults, children, budget_mode, max_budget, status) VALUES (?,?,?,?,?,?,?,?,?, "draft")',
            [(int) current_user_id(), 'Trip to ' . $city['name'], $cityId, $dateFrom ?: null, $dateTo ?: null, $adults, $children, $budgetMode, $maxBudget]
        );
        generate_trip_days($conn, $tripId, $dateFrom, $dateTo);
        recalculate_trip_budget($conn, $tripId);
        unset($_SESSION['pending_trip']);
        redirect('/customer/trip-builder.php?id=' . $tripId);
    }
}

// Resume a trip creation that was interrupted by a login redirect.
if (is_logged_in() && !empty($_SESSION['pending_trip']) && isset($_GET['resume'])) {
    $_POST = $_SESSION['pending_trip'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['csrf_token'] = csrf_token();
    require __FILE__;
    exit;
}

$pageTitle = 'Trip Planner';
require ROOT_PATH . '/includes/header.php';
?>
<div class="hero" style="padding:72px 0 80px;">
  <div class="hero-blob hero-blob-1"></div>
  <div class="hero-blob hero-blob-2"></div>
  <div class="container-xl" style="position:relative;z-index:2;text-align:center;">
    <span class="hero-eyebrow"><i class="bi bi-map"></i> Trip Planner</span>
    <h1 class="hero-title" style="margin-top:16px;">Plan the whole trip. <span class="accent">Automatically.</span></h1>
    <p class="hero-sub" style="max-width:560px;margin:0 auto 40px;">Tell us where and when — we'll build a day-by-day itinerary and a live budget estimate you can adjust as you go.</p>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger" style="max-width:520px;margin:0 auto 16px;text-align:left;"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="panel reveal in-view" style="max-width:560px;margin:0 auto;text-align:left;background:var(--white);">
      <?php echo csrf_field(); ?>
      <label style="font-size:13px;font-weight:600;">Destination</label>
      <select name="city_id" required style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
        <option value="">Choose a city</option>
        <?php foreach ($cities as $c): ?>
          <option value="<?php echo (int) $c['id']; ?>"><?php echo e($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 14px;">
        <div><label style="font-size:13px;font-weight:600;">Start date</label><input type="date" name="date_from" min="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;"></div>
        <div><label style="font-size:13px;font-weight:600;">End date</label><input type="date" name="date_to" min="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;"></div>
        <div><label style="font-size:13px;font-weight:600;">Adults</label><input type="number" name="adults" min="1" value="2" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;"></div>
        <div><label style="font-size:13px;font-weight:600;">Children</label><input type="number" name="children" min="0" value="0" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;"></div>
      </div>
      <label style="font-size:13px;font-weight:600;">Budget style</label>
      <select name="budget_mode" id="budget-mode" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
        <option value="economy">Economy</option>
        <option value="standard" selected>Standard</option>
        <option value="luxury">Luxury</option>
        <option value="custom">Custom budget</option>
      </select>
      <div id="custom-budget-wrap" style="display:none;">
        <label style="font-size:13px;font-weight:600;">Maximum budget (USD)</label>
        <input type="number" name="max_budget" min="0" style="width:100%;padding:12px;border-radius:10px;border:1.5px solid var(--border);margin-bottom:14px;">
      </div>
      <button type="submit" class="btn-w btn-primary btn-block"><i class="bi bi-magic"></i> Build my itinerary</button>
    </form>
  </div>
</div>
<script>
document.getElementById('budget-mode').addEventListener('change', function () {
  document.getElementById('custom-budget-wrap').style.display = this.value === 'custom' ? 'block' : 'none';
});
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
