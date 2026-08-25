<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$service = $slug ? db_select_one(
    $conn,
    'SELECT s.*, p.business_name, p.slug AS provider_slug, p.is_verified, p.show_phone, p.show_email, p.show_map,
        u.phone AS provider_phone, u.email AS provider_email,
        c.name AS city_name, cat.name AS category_name, cat.icon AS category_icon, cat.listing_type AS category_listing_type
     FROM services s
     JOIN providers p ON p.id = s.provider_id JOIN users u ON u.id = p.user_id
     LEFT JOIN cities c ON c.id = s.city_id LEFT JOIN categories cat ON cat.id = s.category_id
     WHERE s.slug = ? AND s.status = "approved"',
    [$slug]
) : null;

if (!$service) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$isProduct = $service['category_listing_type'] === 'product';

if ($isProduct && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_to_cart') {
    if (!is_logged_in()) {
        redirect('/customer/login.php?redirect=' . urlencode('/pages/service.php?slug=' . $slug));
    }
    verify_csrf();
    if (current_user_role() !== 'customer') {
        flash_set('danger', 'Only customer accounts can buy products.');
        redirect('/pages/service.php?slug=' . $slug);
    }

    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    if ($service['stock_quantity'] !== null && (int) $service['stock_quantity'] < 1) {
        flash_set('danger', 'Sorry, this product is out of stock.');
        redirect('/pages/service.php?slug=' . $slug);
    }
    if ($service['stock_quantity'] !== null) {
        $qty = min($qty, (int) $service['stock_quantity']);
    }

    $existing = db_select_one($conn, 'SELECT id, quantity FROM cart_items WHERE user_id = ? AND service_id = ?', [(int) current_user_id(), (int) $service['id']]);
    if ($existing) {
        $newQty = (int) $existing['quantity'] + $qty;
        if ($service['stock_quantity'] !== null) {
            $newQty = min($newQty, (int) $service['stock_quantity']);
        }
        db_execute($conn, 'UPDATE cart_items SET quantity = ? WHERE id = ?', [$newQty, (int) $existing['id']]);
    } else {
        db_execute($conn, 'INSERT INTO cart_items (user_id, service_id, quantity) VALUES (?, ?, ?)', [(int) current_user_id(), (int) $service['id'], $qty]);
    }
    flash_set('success', 'Added to cart.');
    redirect('/customer/cart.php');
}

$bookingErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'booking') {
    if (!is_logged_in()) {
        redirect('/customer/login.php?redirect=' . urlencode('/pages/service.php?slug=' . $slug));
    }
    verify_csrf();

    if (current_user_role() !== 'customer') {
        $bookingErrors[] = 'Only customer accounts can make reservations.';
    }

    $units = booking_units_from_request($service, $_POST);
    if ($units === null) {
        $bookingErrors[] = 'Please fill in the required date/time fields.';
    }

    $unit = $service['price_unit'];
    $dateFrom = clean_input($_POST['date_from'] ?? '');
    $dateTo = in_array($unit, ['night', 'day'], true) ? clean_input($_POST['date_to'] ?? '') : $dateFrom;
    $startTime = $unit === 'hour' ? clean_input($_POST['start_time'] ?? '') : null;
    $endTime = $unit === 'hour' ? clean_input($_POST['end_time'] ?? '') : null;
    $guests = max(1, (int) ($_POST['guests'] ?? 1));
    if ($service['max_guests'] && $guests > (int) $service['max_guests']) {
        $bookingErrors[] = 'This listing accepts a maximum of ' . (int) $service['max_guests'] . ' guests.';
    }

    if (!$bookingErrors && $dateFrom < date('Y-m-d')) {
        $bookingErrors[] = 'The selected date is in the past.';
    }

    if (!$bookingErrors) {
        $rangeEnd = in_array($unit, ['night', 'day'], true) ? $dateTo : date('Y-m-d', strtotime($dateFrom . ' +1 day'));
        if (!service_is_available_range($conn, $service['id'], $dateFrom, $rangeEnd)) {
            $bookingErrors[] = 'Sorry, this listing is not available for the dates you selected. Please choose different dates.';
        }
    }

    if (!$bookingErrors) {
        $breakdown = calculate_booking_price($conn, $service, $units);
        $bookingRef = generate_booking_ref();
        $bookingId = db_insert_get_id(
            $conn,
            'INSERT INTO bookings (booking_ref, service_id, customer_id, provider_id, date_from, date_to, start_time, end_time, guests, quantity, base_price, tax_amount, service_fee, commission_amount, total_amount, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, "pending")',
            [
                $bookingRef, (int) $service['id'], (int) current_user_id(), (int) $service['provider_id'],
                $dateFrom, $dateTo ?: null, $startTime, $endTime, $guests, (int) $breakdown['units'],
                $breakdown['base'], $breakdown['tax'], $breakdown['fee'], $breakdown['commission'], $breakdown['total'],
            ]
        );
        mark_service_dates($conn, $service['id'], $dateFrom, $rangeEnd, 'reserved');

        $providerUser = db_select_one($conn, 'SELECT user_id, name, email FROM users u JOIN providers p ON p.user_id = u.id WHERE p.id = ?', [(int) $service['provider_id']]);
        if ($providerUser) {
            db_execute(
                $conn,
                'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "booking_request", "New booking request", ?, "/provider/bookings.php")',
                [(int) $providerUser['user_id'], $service['title'] . ' — ' . format_date($dateFrom)]
            );
            send_email($conn, $providerUser['email'], $providerUser['name'], 'booking_created_provider', ['name' => $providerUser['name'], 'service_title' => $service['title'], 'booking_ref' => $bookingRef]);
        }
        $bookingCustomer = current_user($conn);
        send_email($conn, $bookingCustomer['email'], $bookingCustomer['name'], 'booking_created_customer', ['name' => $bookingCustomer['name'], 'service_title' => $service['title'], 'booking_ref' => $bookingRef]);

        flash_set('success', 'Booking request sent! Reference ' . $bookingRef . '. The provider will confirm shortly — track it from My Bookings.');
        redirect('/customer/bookings.php');
    }
}

if (isset($_GET['message']) && $_GET['message'] === '1') {
    if (!is_logged_in()) {
        redirect('/customer/login.php?redirect=' . urlencode('/pages/service.php?slug=' . $slug . '&message=1'));
    }
    if (current_user_role() !== 'customer') {
        flash_set('danger', 'Only customer accounts can message providers.');
        redirect('/pages/service.php?slug=' . $slug);
    }
    $convId = find_or_create_conversation($conn, (int) current_user_id(), (int) $service['provider_id'], (int) $service['id']);
    redirect('/customer/messages.php?conversation_id=' . $convId);
}

db_execute($conn, 'UPDATE services SET view_count = view_count + 1 WHERE id = ?', [(int) $service['id']]);

$images = db_select($conn, 'SELECT * FROM service_images WHERE service_id = ? ORDER BY is_cover DESC, sort_order', [(int) $service['id']]);
$amenities = db_select($conn, 'SELECT a.* FROM service_amenity_map sam JOIN amenities a ON a.id = sam.amenity_id WHERE sam.service_id = ?', [(int) $service['id']]);
$fieldValues = db_select($conn, 'SELECT cf.field_label, cf.field_type, sfv.field_value FROM service_field_values sfv JOIN category_fields cf ON cf.id = sfv.category_field_id WHERE sfv.service_id = ? ORDER BY cf.sort_order', [(int) $service['id']]);
$reviews = db_select($conn, 'SELECT r.*, u.name AS customer_name FROM reviews r JOIN users u ON u.id = r.customer_id WHERE r.service_id = ? AND r.status = "approved" ORDER BY r.created_at DESC LIMIT 10', [(int) $service['id']]);
$similar = db_select($conn, 'SELECT * FROM services WHERE category_id = ? AND id != ? AND status = "approved" ORDER BY avg_rating DESC LIMIT 4', [(int) $service['category_id'], (int) $service['id']]);

$pageTitle = $service['title'];
$metaDescription = $service['short_description'] ?: mb_substr(strip_tags((string) $service['description']), 0, 160);
$extraCss = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
$extraJs = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script src="' . ASSETS_URL . '/js/booking.js"></script>';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div style="font-size:13.5px;color:var(--ink-mute);margin-bottom:14px;">
      <a href="<?php echo url('/pages/category.php'); ?>?slug=<?php echo e($_GET['cat'] ?? ''); ?>" style="color:var(--ink-mute);"><i class="bi <?php echo e($service['category_icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($service['category_name']); ?></a>
      <?php if ($service['city_name']): ?> · <i class="bi bi-geo-alt"></i> <?php echo e($service['city_name']); ?><?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
      <h1 style="font-size:clamp(24px,3vw,34px);font-weight:800;margin:0 0 18px;"><?php echo e($service['title']); ?></h1>
      <div style="position:relative;flex-shrink:0;"><?php echo render_fav_button($conn, 'service', $service['id']); ?></div>
    </div>

    <?php if ($images): ?>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;grid-template-rows:1fr 1fr;gap:8px;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:32px;max-height:420px;">
        <img src="<?php echo e($images[0]['image_path']); ?>" style="grid-row:1/3;width:100%;height:100%;object-fit:cover;">
        <?php for ($i = 1; $i < min(5, count($images)); $i++): ?>
          <img src="<?php echo e($images[$i]['image_path']); ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php endfor; ?>
      </div>
    <?php else: ?>
      <div style="aspect-ratio:16/6;background:linear-gradient(160deg,var(--purple-soft),var(--purple));border-radius:var(--radius-lg);margin-bottom:32px;"></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:40px;align-items:start;">
      <div>
        <div class="panel" style="display:flex;align-items:center;justify-content:space-between;">
          <a href="<?php echo url('/pages/provider.php'); ?>?slug=<?php echo e($service['provider_slug']); ?>" style="display:flex;align-items:center;gap:12px;">
            <span class="avatar-dot" style="width:44px;height:44px;font-size:16px;"><?php echo e(strtoupper(substr($service['business_name'], 0, 1))); ?></span>
            <div>
              <div style="font-weight:700;"><?php echo e($service['business_name']); ?><?php if ($service['is_verified']): ?> <i class="bi bi-patch-check-fill" style="color:var(--purple);"></i><?php endif; ?></div>
              <div style="font-size:13px;color:var(--ink-mute);">View provider profile</div>
            </div>
          </a>
          <div class="card-rating"><i class="bi bi-star-fill"></i> <?php echo number_format((float) $service['avg_rating'], 1); ?> <span style="color:var(--ink-mute);font-weight:500;">(<?php echo (int) $service['review_count']; ?> reviews)</span></div>
        </div>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:10px;">About this listing</h3>
          <p style="color:var(--ink-soft);line-height:1.7;white-space:pre-line;"><?php echo e($service['description']); ?></p>
        </div>

        <?php if ($fieldValues): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Details</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 24px;">
            <?php foreach ($fieldValues as $fv): ?>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:8px;">
                <span style="color:var(--ink-mute);font-size:13.5px;"><?php echo e($fv['field_label']); ?></span>
                <strong style="font-size:13.5px;"><?php echo $fv['field_type'] === 'checkbox' ? ($fv['field_value'] === '1' ? 'Yes' : 'No') : e($fv['field_value']); ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($amenities): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Amenities</h3>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <?php foreach ($amenities as $a): ?>
              <div style="display:flex;align-items:center;gap:8px;font-size:14px;"><i class="bi <?php echo e($a['icon'] ?: 'bi-check'); ?>" style="color:var(--purple-600);"></i> <?php echo e($a['name']); ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($service['show_map']): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Location</h3>
          <?php echo render_leaflet_map($service['latitude'], $service['longitude'], e($service['title'])); ?>
          <?php if ($service['address']): ?><p style="margin-top:10px;color:var(--ink-mute);font-size:14px;"><i class="bi bi-geo-alt"></i> <?php echo e($service['address']); ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($service['cancellation_policy']): ?>
        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:10px;"><?php echo $isProduct ? 'Return & refund policy' : 'Cancellation policy'; ?></h3>
          <p style="color:var(--ink-soft);font-size:14px;line-height:1.7;"><?php echo e($service['cancellation_policy']); ?></p>
        </div>
        <?php endif; ?>

        <div class="panel">
          <h3 style="font-size:16px;margin-bottom:14px;">Reviews (<?php echo (int) $service['review_count']; ?>)</h3>
          <?php if ($reviews): ?>
            <?php foreach ($reviews as $r): ?>
              <div style="border-bottom:1px solid var(--border);padding:14px 0;">
                <div style="display:flex;justify-content:space-between;">
                  <strong style="font-size:14px;"><?php echo e($r['customer_name']); ?></strong>
                  <span class="card-rating"><i class="bi bi-star-fill"></i> <?php echo (int) $r['rating']; ?></span>
                </div>
                <?php if ($r['title']): ?><div style="font-weight:600;font-size:14px;margin-top:4px;"><?php echo e($r['title']); ?></div><?php endif; ?>
                <p style="color:var(--ink-mute);font-size:13.5px;margin-top:4px;"><?php echo e($r['review_text']); ?></p>
                <?php if ($r['provider_response']): ?>
                  <div style="background:var(--purple-50);border-radius:10px;padding:10px 14px;margin-top:8px;">
                    <strong style="font-size:11.5px;color:var(--purple-600);">Response from <?php echo e($service['business_name']); ?></strong>
                    <p style="font-size:13px;margin-top:3px;"><?php echo e($r['provider_response']); ?></p>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-star"></i></div><h4>No reviews yet</h4><p>Be the first to book and share your experience.</p></div>
          <?php endif; ?>
        </div>
      </div>

      <div style="position:sticky;top:96px;">
        <?php if ($isProduct): ?>
          <div class="panel" id="product-widget">
            <div class="price-tag" style="font-size:24px;"><?php echo format_price($service['price']); ?> <span style="font-size:14px;">/ item</span></div>

            <?php if ($service['stock_quantity'] === null || (int) $service['stock_quantity'] > 0): ?>
              <div style="font-size:13px;color:var(--success);font-weight:600;margin-top:6px;"><i class="bi bi-check-circle-fill"></i> In stock<?php echo $service['stock_quantity'] !== null ? ' (' . (int) $service['stock_quantity'] . ' left)' : ''; ?></div>
            <?php else: ?>
              <div style="font-size:13px;color:var(--danger);font-weight:600;margin-top:6px;"><i class="bi bi-x-circle-fill"></i> Out of stock</div>
            <?php endif; ?>

            <form method="post" style="margin-top:16px;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="form" value="add_to_cart">
              <label style="font-size:12px;font-weight:700;">Quantity</label>
              <input type="number" name="quantity" min="1" <?php echo $service['stock_quantity'] !== null ? 'max="' . (int) $service['stock_quantity'] . '"' : ''; ?> value="1" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);" <?php echo ($service['stock_quantity'] !== null && (int) $service['stock_quantity'] < 1) ? 'disabled' : ''; ?>>
              <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:16px;" <?php echo ($service['stock_quantity'] !== null && (int) $service['stock_quantity'] < 1) ? 'disabled' : ''; ?>><i class="bi bi-cart-plus"></i> Add to cart</button>
            </form>
            <a href="<?php echo url('/customer/cart.php'); ?>" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-bag-check"></i> View cart</a>
            <p class="form-hint" style="text-align:center;margin-top:10px;">Add more items from other stores, then check out once.</p>
            <a href="?slug=<?php echo e($slug); ?>&message=1" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-chat-dots"></i> Message seller</a>
            <?php if ($service['show_phone'] && $service['provider_phone']): ?>
              <a href="tel:<?php echo e($service['provider_phone']); ?>" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-telephone"></i> Call seller</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
        <div class="panel" id="booking-widget" data-service-id="<?php echo (int) $service['id']; ?>" data-unit="<?php echo e($service['price_unit']); ?>">
          <div class="price-tag" style="font-size:24px;"><?php echo format_price($service['price']); ?> <span style="font-size:14px;">/ <?php echo e($service['price_unit']); ?></span></div>

          <?php foreach ($bookingErrors as $err): ?>
            <div class="alert-w alert-danger" style="margin-top:14px;"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
          <?php endforeach; ?>

          <form method="post" id="booking-form" style="margin-top:16px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form" value="booking">

            <?php if (in_array($service['price_unit'], ['night', 'day'], true)): ?>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div><label style="font-size:12px;font-weight:700;">Check-in</label><input type="date" name="date_from" id="bk-date-from" min="<?php echo date('Y-m-d'); ?>" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
                <div><label style="font-size:12px;font-weight:700;">Check-out</label><input type="date" name="date_to" id="bk-date-to" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
              </div>
            <?php elseif ($service['price_unit'] === 'hour'): ?>
              <label style="font-size:12px;font-weight:700;">Date</label>
              <input type="date" name="date_from" min="<?php echo date('Y-m-d'); ?>" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                <div><label style="font-size:12px;font-weight:700;">Start</label><input type="time" name="start_time" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
                <div><label style="font-size:12px;font-weight:700;">End</label><input type="time" name="end_time" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
              </div>
            <?php else: ?>
              <label style="font-size:12px;font-weight:700;">Date</label>
              <input type="date" name="date_from" min="<?php echo date('Y-m-d'); ?>" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
              <?php if ($service['price_unit'] === 'fixed'): ?>
                <label style="font-size:12px;font-weight:700;margin-top:10px;">Quantity</label>
                <input type="number" name="quantity" min="1" value="1" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($service['max_guests'] || $service['price_unit'] === 'person'): ?>
              <label style="font-size:12px;font-weight:700;margin-top:10px;">Guests</label>
              <input type="number" name="guests" min="1" <?php echo $service['max_guests'] ? 'max="' . (int) $service['max_guests'] . '"' : ''; ?> value="1" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
            <?php endif; ?>

            <div id="price-preview" style="margin-top:16px;font-size:13.5px;color:var(--ink-mute);display:none;">
              <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Subtotal</span><strong id="pv-base" style="color:var(--ink);">-</strong></div>
              <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Service fee</span><strong id="pv-fee" style="color:var(--ink);">-</strong></div>
              <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Tax</span><strong id="pv-tax" style="color:var(--ink);">-</strong></div>
              <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:1px solid var(--border);margin-top:4px;font-size:15px;"><span style="color:var(--ink);font-weight:700;">Total</span><strong id="pv-total" style="color:var(--purple-600);">-</strong></div>
            </div>

            <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:16px;"><i class="bi bi-calendar-check"></i> Request to book</button>
          </form>
          <p class="form-hint" style="text-align:center;margin-top:10px;">You won't be charged yet — the provider confirms first.</p>
          <a href="?slug=<?php echo e($slug); ?>&message=1" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-chat-dots"></i> Message provider</a>
          <?php if ($service['show_phone'] && $service['provider_phone']): ?>
            <a href="tel:<?php echo e($service['provider_phone']); ?>" class="btn-w btn-outline btn-block" style="margin-top:10px;"><i class="bi bi-telephone"></i> Call provider</a>
          <?php endif; ?>
          <div style="position:relative;margin-top:10px;">
            <button type="button" id="add-to-trip-btn" class="btn-w btn-outline btn-block" data-service-id="<?php echo (int) $service['id']; ?>"><i class="bi bi-map"></i> Add to Trip</button>
            <div id="trip-picker" class="user-menu" style="width:100%;right:0;"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($similar): ?>
    <div style="margin-top:48px;">
      <h3 style="font-size:19px;font-weight:800;margin-bottom:20px;">Similar listings</h3>
      <div class="provider-grid">
        <?php foreach ($similar as $sim): ?>
          <div class="service-card">
            <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($sim['slug']); ?>">
              <div class="thumb"></div>
              <div class="card-body">
                <div class="card-title"><?php echo e($sim['title']); ?></div>
                <div class="price-tag"><?php echo format_price($sim['price']); ?> <?php if ($sim['price_unit'] !== 'fixed'): ?><span>/ <?php echo e($sim['price_unit']); ?></span><?php endif; ?></div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
