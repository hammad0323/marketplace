<?php
require __DIR__ . '/config.php';

$categories = q('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$byCat = [];
$serviceIndex = [];
foreach (q('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll() as $s) {
    $byCat[$s['category_id']][] = $s;
    $serviceIndex[$s['id']] = $s;
}

$selService = (int) ($_POST['service'] ?? $_GET['service'] ?? 0);
$selDate    = (string) ($_POST['date'] ?? $_GET['date'] ?? date('Y-m-d', strtotime('+1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate) || $selDate < date('Y-m-d')) {
    $selDate = date('Y-m-d', strtotime('+1 day'));
}
$selTime = (string) ($_POST['time'] ?? '');

$errors  = [];
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = post('name');
    $phone = post('phone');
    $email = post('email');
    $notes = post('notes');

    if (!csrf_ok())                                       $errors[] = 'Your session expired — please submit the form again.';
    if (post('website') !== '')                           $errors[] = 'Spam check failed.';
    if (!isset($serviceIndex[$selService]))               $errors[] = 'Please choose a service.';
    if (mb_strlen($name) < 2)                             $errors[] = 'Please enter your name.';
    if (!preg_match('/^[0-9+\-\s()]{10,20}$/', $phone))   $errors[] = 'Please enter a valid phone number.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!in_array($selTime, all_slots(), true))           $errors[] = 'Please choose a time slot.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Lock the slot's rows so two people can't grab the last seat at once.
            $count = (int) q("SELECT COUNT(*) FROM bookings WHERE booking_date = ? AND booking_time = ?
                              AND status IN ('pending','confirmed') FOR UPDATE", [$selDate, $selTime])->fetchColumn();
            if (!in_array($selTime, available_slots($selDate), true) || $count >= max(1, (int) setting('slot_capacity', '2'))) {
                $errors[] = 'Sorry, that time slot was just taken or is no longer available. Please pick another.';
                $pdo->rollBack();
            } else {
                $ref = 'RK' . date('md') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
                q('INSERT INTO bookings (ref, service_id, service_name, name, phone, email, booking_date, booking_time, notes)
                   VALUES (?,?,?,?,?,?,?,?,?)',
                  [$ref, $selService, $serviceIndex[$selService]['name'], $name, $phone, $email, $selDate, $selTime, $notes]);
                $pdo->commit();
                $booking = ['ref' => $ref, 'name' => $name, 'service' => $serviceIndex[$selService]['name'], 'date' => $selDate, 'time' => $selTime];
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Something went wrong while saving your booking. Please try again or call us.';
        }
    }
}

$pageTitle  = 'Book an Appointment';
$activeNav  = 'booking';
$heroTitle  = 'Book an Appointment';
$heroScript = 'Reserve your glow moment';
$heroText   = 'Choose your service, date and time — we\'ll confirm by phone or WhatsApp.';
$heroImage  = 'assets/img/bridal-side.jpg';
require __DIR__ . '/inc/header.php';
require __DIR__ . '/inc/page-hero.php';
?>
<section class="section">
    <div class="container two-col">
        <div class="card reveal">
            <?php if ($booking): ?>
                <div class="success-box">
                    <div class="big-icon"><i class="fa-solid fa-check"></i></div>
                    <h2>Booking request received!</h2>
                    <p>Thank you, <?= e($booking['name']) ?>. Your reference number is</p>
                    <div class="ref"><?= e($booking['ref']) ?></div>
                    <p><strong><?= e($booking['service']) ?></strong><br>
                       <?= e(date('l, j F Y', strtotime($booking['date']))) ?> at <?= e(slot_label($booking['time'])) ?></p>
                    <p>Your appointment is <strong>pending confirmation</strong> — we'll contact you shortly.</p>
                    <?php $wa = "Hi! I just booked {$booking['service']} on " . date('j M Y', strtotime($booking['date'])) . ' at ' . slot_label($booking['time']) . ". Ref: {$booking['ref']}"; ?>
                    <a href="<?= e(whatsapp_link($wa)) ?>" target="_blank" rel="noopener" class="btn btn-primary"><i class="fa-brands fa-whatsapp"></i> Confirm on WhatsApp</a>
                    <a href="index.php" class="btn btn-outline">Back to Home</a>
                </div>
            <?php else: ?>
                <h2 style="font-size:36px">Your Appointment</h2>
                <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
                <form method="post" action="booking.php">
                    <?= csrf_field() ?>
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                    <label>Service *
                        <select name="service" required>
                            <option value="">— Choose a service —</option>
                            <?php foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
                                <optgroup label="<?= e($c['name']) ?>">
                                    <?php foreach ($byCat[$c['id']] as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $s['id'] === $selService ? 'selected' : '' ?>>
                                            <?= e($s['name']) ?> — <?= money($s['price']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Date *
                        <input type="date" name="date" id="bookingDate" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" value="<?= e($selDate) ?>">
                    </label>
                    <label style="margin-bottom:0">Time *</label>
                    <div class="slots" id="slotBox" data-selected="<?= e($selTime) ?>">
                        <?php $avail = available_slots($selDate); ?>
                        <?php if (!$avail): ?><p class="slots-msg">No slots available on this date — please choose another day.</p><?php endif; ?>
                        <?php foreach ($avail as $s): ?>
                            <label class="slot"><input type="radio" name="time" value="<?= $s ?>" <?= $s === $selTime ? 'checked' : '' ?> required><span><?= slot_label($s) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-row">
                        <label>Your name *<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
                        <label>Phone / WhatsApp *<input name="phone" type="tel" required placeholder="03xx xxxxxxx" value="<?= e($_POST['phone'] ?? '') ?>"></label>
                    </div>
                    <label>Email (optional)<input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
                    <label>Notes<textarea name="notes" rows="3" placeholder="e.g. bridal trial, hair length, number of people…"><?= e($_POST['notes'] ?? '') ?></textarea></label>
                    <button class="btn btn-primary btn-block"><i class="fa-regular fa-calendar-check"></i> Request Appointment</button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="sticky">
            <div class="card reveal-right">
                <h3 style="font-size:28px">Good to know</h3>
                <ul class="facts">
                    <li><span>Hours</span><span><?= e(setting('hours')) ?></span></li>
                    <li><span>Phone</span><span><a href="tel:<?= e(preg_replace('/\s/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></span></li>
                    <li><span>Location</span><span><?= e(setting('address')) ?></span></li>
                </ul>
                <p style="font-size:15px;color:var(--muted)">For bridal bookings we recommend reserving at least 2–3 weeks in advance. Please arrive 10 minutes early for your appointment.</p>
                <a href="<?= e(whatsapp_link('Hi! I have a question about booking.')) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-block"><i class="fa-brands fa-whatsapp"></i> Ask on WhatsApp</a>
            </div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
