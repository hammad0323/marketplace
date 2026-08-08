<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "SELECT d.*, u.full_name, u.avatar, u.email, u.phone, u.last_active_at
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.slug = ? AND u.status = 'active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$doctor || $doctor['verification_status'] !== 'verified') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

mysqli_query(db(), 'UPDATE doctors SET profile_views = profile_views + 1 WHERE id = ' . (int) $doctor['id']);
$doctorSpecializations = get_doctor_specializations($doctor['id']);
$specNames = specialization_names($doctorSpecializations);

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_privacy_settings WHERE doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctor['id']);
mysqli_stmt_execute($stmt);
$privacy = mysqli_stmt_get_result($stmt)->fetch_assoc() ?: [
    'show_certificates' => 1, 'show_fees' => 1, 'show_availability' => 1, 'show_clinic_address' => 1,
    'show_phone' => 0, 'show_email' => 0, 'show_free_consultation' => 1, 'show_store' => 1, 'show_reviews' => 1,
];

$certificates = [];
if ($privacy['show_certificates']) {
    $stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_certificates WHERE doctor_id = ? ORDER BY issued_year DESC');
    mysqli_stmt_bind_param($stmt, 'i', $doctor['id']);
    mysqli_stmt_execute($stmt);
    $certificates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

$products = [];
if ($doctor['is_premium'] && $privacy['show_store']) {
    $stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_products WHERE doctor_id = ? AND is_active = 1 ORDER BY type, name');
    mysqli_stmt_bind_param($stmt, 'i', $doctor['id']);
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

$reviews = [];
if ($privacy['show_reviews']) {
    $stmt = mysqli_prepare(db(), "SELECT r.*, u.full_name, u.avatar FROM reviews r
        JOIN patients p ON p.id = r.patient_id JOIN users u ON u.id = p.user_id
        WHERE r.doctor_id = ? AND r.status = 'visible' ORDER BY r.created_at DESC LIMIT 20");
    mysqli_stmt_bind_param($stmt, 'i', $doctor['id']);
    mysqli_stmt_execute($stmt);
    $reviews = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

$pageTitle = $doctor['full_name'] . ' — ' . ($specNames ?: 'Doctor') . ' | ' . SITE_NAME;
$metaDescription = excerpt($doctor['bio'] ?: ($doctor['full_name'] . ' is a verified ' . ($specNames ?: 'doctor') . ' on ' . SITE_NAME . '.'), 155);
$canonical = APP_URL . '/doctor-profile?slug=' . $doctor['slug'];
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org', '@type' => 'Physician', 'name' => $doctor['full_name'],
    'medicalSpecialty' => array_column($doctorSpecializations, 'name'), 'url' => APP_URL . '/doctor-profile?slug=' . $doctor['slug'],
    'aggregateRating' => $doctor['rating_count'] > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => $doctor['rating_avg'], 'reviewCount' => $doctor['rating_count']] : null,
]) . '</script>';
$extraScripts = '<script src="/assets/js/booking.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 40px);">
    <div class="container">
        <nav class="breadcrumb">
            <a href="/">Home</a> <i class="ri-arrow-right-s-line"></i>
            <a href="/doctors">Find Doctors</a> <i class="ri-arrow-right-s-line"></i>
            <span><?= e($doctor['full_name']) ?></span>
        </nav>

        <div class="split-sidebar-right" style="gap:32px;">
            <div>
                <div class="card" style="padding:32px;margin-bottom:24px;" data-reveal>
                    <div style="display:flex;gap:20px;flex-wrap:wrap;">
                        <img src="<?= e(avatar_url($doctor['avatar'], $doctor['full_name'])) ?>" alt="<?= e($doctor['full_name']) ?>" style="width:110px;height:110px;border-radius:24px;object-fit:cover;">
                        <div style="flex:1;min-width:220px;">
                            <h1 style="font-size:26px;margin-bottom:4px;"><?= e($doctor['full_name']) ?></h1>
                            <p style="color:var(--color-primary);font-weight:600;margin-bottom:6px;"><?= e($doctor['qualification'] ?: $specNames) ?></p>
                            <?php if ($doctorSpecializations): ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
                                <?php foreach ($doctorSpecializations as $ds): ?>
                                <span class="badge badge-free"><?= e($ds['name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                                <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
                                <?php if ($doctor['is_premium']): ?><span class="badge badge-premium"><i class="ri-vip-crown-fill"></i> Premium</span><?php endif; ?>
                                <?php if ($privacy['show_free_consultation'] && $doctor['free_consultation']): ?><span class="badge badge-free">Free Consultation</span><?php endif; ?>
                            </div>
                            <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13.5px;color:var(--color-text-muted);">
                                <span class="rating"><i class="ri-star-fill"></i> <?= number_format($doctor['rating_avg'], 1) ?> (<?= (int)$doctor['rating_count'] ?> reviews)</span>
                                <span><i class="ri-briefcase-line"></i> <?= (int)$doctor['experience_years'] ?> years experience</span>
                                <?php if ($privacy['show_clinic_address'] && $doctor['clinic_city']): ?><span><i class="ri-map-pin-line"></i> <?= e($doctor['clinic_city']) ?>, <?= e($doctor['clinic_state']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tabs-row">
                    <button class="tab-btn active" data-tab-target="overview">Overview</button>
                    <?php if ($certificates): ?><button class="tab-btn" data-tab-target="certificates">Certificates</button><?php endif; ?>
                    <?php if ($products): ?><button class="tab-btn" data-tab-target="store">Products &amp; Services (<?= count($products) ?>)</button><?php endif; ?>
                    <?php if ($privacy['show_reviews']): ?><button class="tab-btn" data-tab-target="reviews">Reviews (<?= count($reviews) ?>)</button><?php endif; ?>
                </div>

                <div class="tab-panel" id="tab-overview">
                    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
                        <h4 style="margin-bottom:12px;">About</h4>
                        <p style="color:var(--color-text-muted);line-height:1.8;"><?= nl2br(e($doctor['bio'])) ?></p>
                    </div>
                    <?php if ($privacy['show_clinic_address'] && $doctor['clinic_name']): ?>
                    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
                        <h4 style="margin-bottom:12px;"><i class="ri-hospital-line"></i> Clinic</h4>
                        <p style="font-weight:600;margin-bottom:4px;"><?= e($doctor['clinic_name']) ?></p>
                        <p style="color:var(--color-text-muted);"><?= e($doctor['clinic_address']) ?>, <?= e($doctor['clinic_city']) ?>, <?= e($doctor['clinic_state']) ?>, <?= e($doctor['clinic_country']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($privacy['show_phone'] || $privacy['show_email']): ?>
                    <div class="card" style="padding:28px;" data-reveal>
                        <h4 style="margin-bottom:12px;">Contact</h4>
                        <?php if ($privacy['show_phone']): ?><p style="margin-bottom:6px;"><i class="ri-phone-line"></i> <?= e($doctor['phone']) ?></p><?php endif; ?>
                        <?php if ($privacy['show_email']): ?><p><i class="ri-mail-line"></i> <?= e($doctor['email']) ?></p><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($certificates): ?>
                <div class="tab-panel" id="tab-certificates" style="display:none;">
                    <div class="grid grid-2">
                        <?php foreach ($certificates as $c): ?>
                        <div class="card" style="padding:20px;display:flex;gap:14px;align-items:center;">
                            <div class="icon-badge" style="width:46px;height:46px;border-radius:12px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex-shrink:0;"><i class="ri-award-line"></i></div>
                            <div><strong style="display:block;"><?= e($c['title']) ?></strong><span style="font-size:13px;color:var(--color-text-muted);"><?= e($c['issued_by']) ?> · <?= e($c['issued_year']) ?></span></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($products): ?>
                <div class="tab-panel" id="tab-store" style="display:none;">
                    <div class="grid grid-2 stagger">
                        <?php foreach ($products as $p): ?>
                        <div class="card" style="padding:20px;" data-reveal>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                <span class="badge badge-<?= $p['type'] === 'service' ? 'pending' : 'verified' ?>"><?= $p['type'] === 'service' ? 'Service' : 'Product' ?></span>
                                <strong style="color:var(--color-primary);font-size:17px;"><?= format_currency($p['price']) ?></strong>
                            </div>
                            <?php if ($p['image']): ?><img src="/uploads/<?= e($p['image']) ?>" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:12px;margin-bottom:10px;"><?php endif; ?>
                            <strong style="display:block;margin-bottom:4px;"><?= e($p['name']) ?></strong>
                            <p style="font-size:13.5px;color:var(--color-text-muted);margin-bottom:10px;"><?= e($p['description']) ?></p>
                            <p style="font-size:12px;color:var(--color-text-muted);margin-bottom:14px;">
                                <?= $p['type'] === 'service' ? e($p['duration_label'] ?: '') : (((int) $p['stock'] > 0) ? (int) $p['stock'] . ' in stock' : '<span style="color:var(--color-danger);">Out of stock</span>') ?>
                            </p>
                            <a href="/product-detail?slug=<?= e($p['slug']) ?>" class="btn btn-primary btn-block">View Details &amp; Order</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($privacy['show_reviews']): ?>
                <div class="tab-panel" id="tab-reviews" style="display:none;">
                    <?php if (!$reviews): ?>
                    <div class="empty-state card"><i class="ri-chat-quote-line"></i><h4>No reviews yet</h4><p>Be the first patient to leave a review after your consultation.</p></div>
                    <?php else: foreach ($reviews as $r): ?>
                    <div class="card" style="padding:22px;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                            <div style="display:flex;gap:10px;align-items:center;">
                                <img src="<?= e(avatar_url($r['avatar'], $r['full_name'])) ?>" style="width:36px;height:36px;border-radius:50%;">
                                <strong><?= e($r['full_name']) ?></strong>
                            </div>
                            <span style="font-size:12px;color:var(--color-text-muted);"><?= time_ago($r['created_at']) ?></span>
                        </div>
                        <div class="rating" style="margin-bottom:8px;"><?php for ($i=0;$i<$r['rating'];$i++): ?><i class="ri-star-fill"></i><?php endfor; ?></div>
                        <p style="color:var(--color-text-muted);"><?= e($r['comment']) ?></p>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="position:sticky;top:calc(var(--header-height) + 20px);">
                <div class="card" style="padding:24px;" id="booking-widget" data-doctor-id="<?= (int)$doctor['id'] ?>" data-reveal="right">
                    <h4 style="margin-bottom:16px;">Book an Appointment</h4>
                    <?php if ($privacy['show_fees']): ?>
                    <div style="display:flex;gap:10px;margin-bottom:18px;">
                        <div class="card" style="flex:1;padding:12px;text-align:center;"><i class="ri-video-chat-line" style="color:var(--color-primary);"></i><div style="font-weight:700;"><?= format_currency($doctor['consultation_fee_online']) ?></div><span style="font-size:11.5px;color:var(--color-text-muted);">Online</span></div>
                        <div class="card" style="flex:1;padding:12px;text-align:center;"><i class="ri-hospital-line" style="color:var(--color-primary);"></i><div style="font-weight:700;"><?= format_currency($doctor['consultation_fee_physical']) ?></div><span style="font-size:11.5px;color:var(--color-text-muted);">In-Person</span></div>
                    </div>
                    <?php endif; ?>

                    <div class="consult-type-toggle" style="display:flex;gap:8px;margin-bottom:18px;">
                        <button type="button" class="btn btn-primary btn-sm" data-type="online" style="flex:1;">Online</button>
                        <button type="button" class="btn btn-outline btn-sm" data-type="physical" style="flex:1;">In-Person</button>
                    </div>

                    <div style="font-size:12.5px;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:8px;">Select a date</div>
                    <div class="calendar-grid" id="booking-calendar" style="margin-bottom:16px;"></div>
                    <div id="selected-date-label" style="font-size:13px;color:var(--color-text-muted);margin-bottom:8px;"></div>
                    <div class="slot-grid" id="slot-grid"></div>

                    <form id="booking-form" style="margin-top:18px;">
                        <div class="form-group">
                            <label class="form-label">Reason for visit (optional)</label>
                            <textarea class="form-control" id="booking-reason" rows="3" placeholder="Briefly describe your symptoms or reason for the visit"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" id="confirm-booking-btn" disabled>Confirm Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-panel').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById('tab-' + btn.getAttribute('data-tab-target')).style.display = 'block';
    });
});
</script>
<?php
$viewerIsGuest = !is_logged_in();
$viewerCanMessage = $viewerIsGuest || current_role() === 'patient';
if ($viewerCanMessage && doctor_chat_visible($doctor, $viewerIsGuest)):
    $chatOnline = doctor_chat_available($doctor);
?>
<div class="doctor-chat-widget" data-reveal="zoom">
    <a href="/patient/messages?doctor_id=<?= (int)$doctor['id'] ?>" class="doctor-chat-fab" <?= $viewerIsGuest ? 'data-requires-auth' : '' ?>>
        <img src="<?= e(avatar_url($doctor['avatar'], $doctor['full_name'])) ?>" alt="">
        <span>
            Message <?= e($doctor['full_name']) ?>
            <span style="display:flex;align-items:center;gap:5px;font-weight:500;font-size:11.5px;color:var(--color-text-muted);margin-top:2px;">
                <span class="status-dot<?= $chatOnline ? ' online' : '' ?>"></span> <?= $chatOnline ? 'Online now' : e(doctor_chat_hours_label($doctor)) ?>
            </span>
        </span>
    </a>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
