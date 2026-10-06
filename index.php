<?php
require __DIR__ . '/config/config.php';

$pageTitle = SITE_NAME . ' — Book Verified Doctors Online & In-Person';
$metaDescription = get_setting('site_tagline') . '. Search verified specialists, book online or in-clinic appointments, and manage your care in one place.';

$specs = mysqli_query(db(), 'SELECT id, name, slug, icon, description FROM specializations WHERE is_active = 1 ORDER BY sort_order LIMIT 10')->fetch_all(MYSQLI_ASSOC);

$cities = mysqli_query(db(), "
    SELECT d.clinic_city AS city, COUNT(*) AS doctor_count
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
      AND d.clinic_city IS NOT NULL AND d.clinic_city != ''
    GROUP BY d.clinic_city
    ORDER BY doctor_count DESC, city ASC
    LIMIT 12
")->fetch_all(MYSQLI_ASSOC);

$featuredDoctors = mysqli_query(db(), "
    SELECT d.*, u.full_name, u.avatar
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY d.is_premium DESC, d.rating_avg DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);
foreach ($featuredDoctors as &$fd) {
    $fd['specializations'] = get_doctor_specializations($fd['id']);
}
unset($fd);

$featuredPharmacies = mysqli_query(db(), "
    SELECT p.*, u.full_name, u.avatar,
        (SELECT COUNT(*) FROM doctor_products dp WHERE dp.pharmacy_id = p.id AND dp.seller_type = 'pharmacy' AND dp.is_active = 1) AS product_count
    FROM pharmacies p JOIN users u ON u.id = p.user_id
    WHERE p.verification_status = 'verified' AND u.status = 'active'
    ORDER BY p.rating_avg DESC, p.created_at DESC
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$testimonials = mysqli_query(db(), 'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order LIMIT 5')->fetch_all(MYSQLI_ASSOC);
$homeFaqs = mysqli_query(db(), 'SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order LIMIT 6')->fetch_all(MYSQLI_ASSOC);
$topCityNames = array_slice(array_column($cities, 'city'), 0, 6);

$totalDoctors = (int) mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'];
$totalAppointments = (int) mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM appointments"))['c'];
$totalSpecs = (int) mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM specializations WHERE is_active=1"))['c'];
$ratingRow = mysqli_fetch_assoc(mysqli_query(db(), "
    SELECT AVG(rating_avg) AS avg_rating, SUM(rating_count) AS total_reviews
    FROM doctors WHERE verification_status = 'verified' AND rating_count > 0
"));
$avgRating = $ratingRow && $ratingRow['avg_rating'] ? round((float) $ratingRow['avg_rating'], 1) : null;
$totalReviews = $ratingRow ? (int) $ratingRow['total_reviews'] : 0;
$siteName = get_setting('site_name', SITE_NAME);

/**
 * Real "next available" slots for the hero booking preview — same rules as
 * ajax/check-availability.php (online windows, skipping blocked dates, booked
 * slots and times already past), looking up to a week ahead.
 */
function home_next_slots($doctorId)
{
    $db = db();
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("+$i day"));
        $stmt = mysqli_prepare($db, 'SELECT 1 FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
        mysqli_stmt_execute($stmt);
        $blocked = (bool) mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if ($blocked) continue;

        $dow = (int) date('w', strtotime($date));
        $stmt = mysqli_prepare($db, "SELECT start_time, end_time, slot_duration_mins FROM doctor_availability
            WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 AND consultation_type IN ('online', 'both') ORDER BY start_time");
        mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $dow);
        mysqli_stmt_execute($stmt);
        $windows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        if (!$windows) continue;

        $stmt = mysqli_prepare($db, "SELECT start_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','approved')");
        mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
        mysqli_stmt_execute($stmt);
        $booked = array_column(mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC), 'start_time');
        mysqli_stmt_close($stmt);

        $labels = [];
        foreach ($windows as $w) {
            $cursor = strtotime($date . ' ' . $w['start_time']);
            $end = strtotime($date . ' ' . $w['end_time']);
            $step = max(5, (int) $w['slot_duration_mins']) * 60;
            while ($cursor + $step <= $end && count($labels) < 6) {
                if (!in_array(date('H:i:s', $cursor), $booked, true) && $cursor > time()) {
                    $labels[] = date('g:i A', $cursor);
                }
                $cursor += $step;
            }
        }
        if ($labels) {
            $dayLabel = $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : date('D, j M', strtotime($date)));
            return ['day' => $dayLabel, 'labels' => $labels, 'duration' => max(5, (int) $windows[0]['slot_duration_mins'])];
        }
    }
    return null;
}

// Hero preview doctor: a different random verified doctor on every load.
// Doctors with an uploaded photo come first (still shuffled among
// themselves), and the first one with real open slots wins; if none of
// the sampled doctors has slots, the card shows their fees/experience.
$heroPool = mysqli_query(db(), "
    SELECT d.*, u.full_name, u.avatar
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY (u.avatar IS NOT NULL AND u.avatar != '') DESC, RAND()
    LIMIT 12
")->fetch_all(MYSQLI_ASSOC);
$heroDoctor = $heroPool[0] ?? null;
$heroSlots = null;
foreach ($heroPool as $candidate) {
    if (($candidate['booking_mode'] ?? 'slots') !== 'slots') continue;
    if ($slots = home_next_slots((int) $candidate['id'])) {
        $heroDoctor = $candidate;
        $heroSlots = $slots;
        break;
    }
}
if ($heroDoctor) {
    $heroDoctor['specializations'] = get_doctor_specializations($heroDoctor['id']);
}
$avatarStack = array_slice($featuredDoctors, 0, 4);

$extraHead = '<link rel="stylesheet" href="' . asset_url('/assets/css/home.css') . '">';
if ($homeFaqs) {
    $extraHead .= '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
        fn($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]],
        $homeFaqs
    )]) . '</script>';
}
$extraScripts = '<script defer src="' . asset_url('/assets/js/search-suggest.js') . '"></script>'
    . '<script defer src="' . asset_url('/assets/js/home.js') . '"></script>';
// Content-rich enough to carry ads (see includes/header.php).
$adsEligible = true;
require __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="h-hero">
    <div class="container h-hero-grid">
        <div class="h-hero-copy">
            <?php if ($avatarStack): ?>
            <div class="h-trust h-fade" style="--d:0">
                <span class="h-avatars">
                    <?php foreach ($avatarStack as $d): ?><img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>" alt="" width="34" height="34"><?php endforeach; ?>
                </span>
                <span>
                    <strong><?= $totalDoctors ?> verified doctor<?= $totalDoctors == 1 ? '' : 's' ?></strong>
                    <?php if ($avgRating !== null): ?><span class="h-trust-sub"><i class="ri-star-fill"></i> <?= e($avgRating) ?> average from <?= number_format($totalReviews) ?> reviews</span><?php else: ?><span class="h-trust-sub">Every credential checked by our team</span><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>

            <h1 class="h-title h-fade" style="--d:1">Book <span class="h-underline">verified doctors<svg viewBox="0 0 300 14" preserveAspectRatio="none" aria-hidden="true"><path d="M2 10 C 70 3, 150 2, 298 7"/></svg></span>, online or in-clinic.</h1>
            <p class="h-lead h-fade" style="--d:2">Compare real fees, reviews and open time slots — then confirm your appointment in under two minutes. No calls, no waiting rooms.</p>

            <form class="h-search h-fade" style="--d:3" id="hero-search-box" action="/doctors" method="get" autocomplete="off">
                <label class="h-field h-field-grow">
                    <span class="h-field-label">What</span>
                    <span class="h-field-control"><i class="ri-search-line"></i><input type="text" id="hero-search-input" name="q" placeholder="Specialty, condition or doctor"></span>
                </label>
                <label class="h-field">
                    <span class="h-field-label">Where</span>
                    <span class="h-field-control"><i class="ri-map-pin-2-line"></i>
                        <select name="city">
                            <option value="">Any city</option>
                            <?php foreach ($cities as $c): ?><option value="<?= e($c['city']) ?>"><?= e($c['city']) ?></option><?php endforeach; ?>
                        </select>
                    </span>
                </label>
                <button type="submit" class="h-search-btn"><i class="ri-search-line"></i><span>Find doctors</span></button>
            </form>

            <div class="h-search-meta h-fade" style="--d:4">
                <button type="button" class="h-nearme" id="hero-near-me"><i class="ri-crosshair-2-line"></i> Use my location</button>
                <?php if ($specs): ?>
                <span class="h-search-meta-sep"></span>
                <span class="h-popular">
                    <?php foreach (array_slice($specs, 0, 4) as $s): ?><a href="/specializations/<?= e($s['slug']) ?>"><?= e($s['name']) ?></a><?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>

            <ul class="h-checks h-fade" style="--d:5">
                <li><i class="ri-shield-check-line"></i> Credential-verified</li>
                <li><i class="ri-time-line"></i> Live availability</li>
                <li><i class="ri-video-chat-line"></i> Video or in-clinic</li>
            </ul>
        </div>

        <?php if ($heroDoctor): $heroSpec = specialization_names($heroDoctor['specializations']) ?: 'General'; $heroFee = (float) $heroDoctor['consultation_fee_online']; ?>
        <div class="h-hero-visual" data-hero-stage>
            <div class="h-panel" aria-hidden="true"></div>
            <div class="h-parallax h-card-wrap" data-parallax="0.04">
                <div class="h-book-card h-rise" style="--d:3" data-depth="10">
                    <div class="h-book-head">
                        <img src="<?= e(avatar_url($heroDoctor['avatar'], $heroDoctor['full_name'])) ?>" alt="<?= e($heroDoctor['full_name']) ?>" width="56" height="56">
                        <div>
                            <strong><?= e($heroDoctor['full_name']) ?> <i class="ri-verified-badge-fill" title="Verified"></i></strong>
                            <span><?= e($heroSpec) ?></span>
                            <?php if ((int) $heroDoctor['rating_count'] > 0): ?>
                            <span class="h-book-rating"><i class="ri-star-fill"></i> <?= number_format((float) $heroDoctor['rating_avg'], 1) ?> <em>(<?= (int) $heroDoctor['rating_count'] ?> reviews)</em></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($heroSlots): ?>
                    <div class="h-book-day"><span>Next available</span><strong><?= e($heroSlots['day']) ?></strong></div>
                    <div class="h-slots" data-slots>
                        <?php foreach ($heroSlots['labels'] as $i => $label): ?><span class="h-slot<?= $i === 1 ? ' is-selected' : '' ?>"><?= e($label) ?></span><?php endforeach; ?>
                    </div>
                    <?php else:
                        $heroFacts = array_filter([
                            ['Online fee', (float) $heroDoctor['consultation_fee_online'] > 0 ? format_currency($heroDoctor['consultation_fee_online']) : ($heroDoctor['free_consultation'] ? 'Free' : null)],
                            ['In-clinic fee', (float) $heroDoctor['consultation_fee_physical'] > 0 ? format_currency($heroDoctor['consultation_fee_physical']) : null],
                            ['Experience', (int) $heroDoctor['experience_years'] > 0 ? (int) $heroDoctor['experience_years'] . ' yrs' : null],
                            ['Location', $heroDoctor['clinic_city'] ?: null],
                        ], fn($f) => $f[1] !== null);
                        $heroFacts = array_slice($heroFacts, 0, 3);
                    ?>
                    <div class="h-book-day"><span><?= ($heroDoctor['booking_mode'] ?? 'slots') === 'tickets' ? 'Walk-in ticket queue' : 'Online &amp; in-clinic' ?></span><strong><?= ($heroDoctor['booking_mode'] ?? 'slots') === 'tickets' ? 'Get a token' : 'Accepting patients' ?></strong></div>
                    <?php if ($heroFacts): ?>
                    <div class="h-facts" style="--cols:<?= count($heroFacts) ?>">
                        <?php foreach ($heroFacts as [$label, $value]): ?><div><span><?= e($label) ?></span><strong><?= e($value) ?></strong></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= e(doctor_url($heroDoctor['slug'])) ?>" class="h-book-btn">Book appointment <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>
            <div class="h-parallax h-mini-wrap" data-parallax="-0.05">
                <div class="h-mini-card h-rise" style="--d:5" data-depth="22">
                    <span class="h-mini-icon"><i class="ri-video-chat-line"></i></span>
                    <div>
                        <strong>Online consultation</strong>
                        <span><?= $heroSlots ? (int) $heroSlots['duration'] . ' min video call' : 'Video call from home' ?><?php if ($heroFee > 0): ?> · <?= e(format_currency($heroFee)) ?><?php elseif ($heroDoctor['free_consultation']): ?> · Free<?php endif; ?></span>
                    </div>
                </div>
            </div>
            <div class="h-parallax h-pill-wrap" data-parallax="0.08">
                <div class="h-pill h-rise" style="--d:6" data-depth="16"><i class="ri-shield-check-fill"></i> Credential verified</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== MARQUEE ============================== -->
<?php if ($specs): ?>
<div class="h-marquee">
    <div class="h-marquee-track">
        <?php for ($g = 0; $g < 2; $g++): ?>
        <div class="h-marquee-group"<?= $g ? ' aria-hidden="true"' : '' ?>>
            <?php foreach ($specs as $s): ?>
            <a href="/specializations/<?= e($s['slug']) ?>" class="h-marquee-item"<?= $g ? ' tabindex="-1"' : '' ?>><?= e($s['name']) ?></a>
            <span class="h-marquee-sep"><i class="ri-asterisk"></i></span>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================== STATS ============================== -->
<section class="h-stats" id="h-stats">
    <div class="container h-stats-grid">
        <div class="h-stat" data-reveal><b class="counter" data-counter="<?= $totalDoctors ?>"><?= $totalDoctors ?></b><span>Verified doctors</span></div>
        <div class="h-stat" data-reveal><b class="counter" data-counter="<?= $totalAppointments ?>"><?= $totalAppointments ?></b><span>Appointments booked</span></div>
        <div class="h-stat" data-reveal><b class="counter" data-counter="<?= $totalSpecs ?>"><?= $totalSpecs ?></b><span>Specializations</span></div>
        <?php if ($avgRating !== null): ?>
        <div class="h-stat" data-reveal><b class="counter" data-counter="<?= e($avgRating) ?>" data-suffix="/5"><?= e($avgRating) ?>/5</b><span>From <?= number_format($totalReviews) ?> review<?= $totalReviews == 1 ? '' : 's' ?></span></div>
        <?php else: ?>
        <div class="h-stat" data-reveal><b>100%</b><span>Credential-verified</span></div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== WHO WE SERVE ============================== -->
<section class="h-section h-intro">
    <div class="container">
        <div class="h-intro-grid">
            <div data-reveal>
                <span class="h-kicker">Who we serve</span>
                <h2 class="h-h2">One place for <span class="h-accent">patients</span>, doctors and pharmacies.</h2>
            </div>
            <p class="h-intro-text" data-reveal>
                <?= e($siteName) ?> connects patients with <?= $totalDoctors ?> verified doctor<?= $totalDoctors == 1 ? '' : 's' ?> across <?= $totalSpecs ?> specialt<?= $totalSpecs == 1 ? 'y' : 'ies' ?><?php if ($topCityNames): ?>, currently practicing in <?= e(implode(', ', array_slice($topCityNames, 0, -1)) . (count($topCityNames) > 1 ? ' and ' . end($topCityNames) : $topCityNames[0])) ?><?php endif; ?>.
                Every doctor is manually credential-checked before they can accept patients, whether the visit is a five-minute online follow-up or an in-person specialist consultation.
                Doctors get a bookable public profile and calendar; verified pharmacies get an online storefront to sell directly to patients.
            </p>
        </div>
        <div class="h-audience">
            <a href="/doctors" class="h-audience-item" data-reveal>
                <span class="h-audience-icon"><i class="ri-user-heart-line"></i></span>
                <h3>For patients</h3>
                <p>Compare verified doctors, see real fees and reviews, and book online or in-clinic.</p>
                <span class="h-link">Find a doctor <i class="ri-arrow-right-line"></i></span>
            </a>
            <a href="/doctor-register" class="h-audience-item" data-reveal>
                <span class="h-audience-icon"><i class="ri-stethoscope-line"></i></span>
                <h3>For doctors</h3>
                <p>A public profile, a live booking calendar, and patient history in one dashboard.</p>
                <span class="h-link">Join as a doctor <i class="ri-arrow-right-line"></i></span>
            </a>
            <a href="/pharmacy-register" class="h-audience-item" data-reveal>
                <span class="h-audience-icon"><i class="ri-store-2-line"></i></span>
                <h3>For pharmacies</h3>
                <p>A licensed online storefront to sell medicines directly to patients near you.</p>
                <span class="h-link">Register your pharmacy <i class="ri-arrow-right-line"></i></span>
            </a>
        </div>
    </div>
</section>

<!-- ============================== SPECIALTIES INDEX ============================== -->
<?php if ($specs): ?>
<section class="h-section h-specs" id="specializations">
    <div class="container">
        <div class="h-head" data-reveal>
            <div>
                <span class="h-kicker">Browse by specialty</span>
                <h2 class="h-h2">Find the right <span class="h-accent">specialist</span>, fast.</h2>
            </div>
            <a href="/specializations" class="h-link">All specializations <i class="ri-arrow-right-line"></i></a>
        </div>
        <div class="h-spec-list">
            <?php foreach ($specs as $i => $s): ?>
            <a href="/specializations/<?= e($s['slug']) ?>" class="h-spec-row" data-reveal>
                <span class="h-spec-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span class="h-spec-icon"><i class="<?= e($s['icon']) ?>"></i></span>
                <span class="h-spec-body">
                    <h3><?= e($s['name']) ?></h3>
                    <?php if ($s['description']): ?><p><?= e($s['description']) ?></p><?php endif; ?>
                </span>
                <span class="h-spec-arrow"><i class="ri-arrow-right-up-line"></i></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="h-steps-sec" data-steps>
    <div class="h-parallax h-steps-glow" data-parallax="0.15" aria-hidden="true"></div>
    <div class="container h-steps-grid">
        <div class="h-steps-sticky">
            <span class="h-kicker h-kicker-light">How it works</span>
            <h2 class="h-h2">Booking care in <span class="h-accent">three</span> simple steps.</h2>
            <p>No phone queues, no guessing who's available. See real open slots and confirm in a couple of taps.</p>
            <a href="/doctors" class="btn h-btn-light">Start searching <i class="ri-arrow-right-line"></i></a>
        </div>
        <div class="h-steps-list">
            <div class="h-steps-line"><span></span></div>
            <div class="h-step">
                <span class="h-step-num">01</span>
                <div>
                    <h3><i class="ri-search-eye-line"></i> Search</h3>
                    <p>Filter by specialty, city, fee, rating or availability — or tap <strong>Near Me</strong> to see the closest verified doctors first.</p>
                </div>
            </div>
            <div class="h-step">
                <span class="h-step-num">02</span>
                <div>
                    <h3><i class="ri-calendar-check-line"></i> Book</h3>
                    <p>Pick an open slot from the doctor's live calendar — online or in-clinic. Some clinics give you a numbered ticket instead, so you know exactly when it's your turn.</p>
                </div>
            </div>
            <div class="h-step">
                <span class="h-step-num">03</span>
                <div>
                    <h3><i class="ri-heart-pulse-line"></i> Consult</h3>
                    <p>Meet your doctor at the scheduled time, message them afterwards, and keep every appointment in your dashboard.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FEATURED DOCTORS ============================== -->
<?php if ($featuredDoctors): ?>
<section class="h-section h-doctors" data-carousel>
    <div class="container">
        <div class="h-head" data-reveal>
            <div>
                <span class="h-kicker">Top rated</span>
                <h2 class="h-h2">Meet our <span class="h-accent">featured</span> doctors.</h2>
            </div>
            <div class="h-head-actions">
                <button type="button" class="h-round-btn" data-carousel-prev aria-label="Previous doctors"><i class="ri-arrow-left-line"></i></button>
                <button type="button" class="h-round-btn" data-carousel-next aria-label="Next doctors"><i class="ri-arrow-right-line"></i></button>
            </div>
        </div>
    </div>
    <div class="h-doctor-track" data-carousel-track>
        <?php foreach ($featuredDoctors as $d): ?>
        <div class="h-doctor-slide"><?php require __DIR__ . '/includes/doctor-card.php'; ?></div>
        <?php endforeach; ?>
    </div>
    <div class="container" style="margin-top:28px;">
        <a href="/doctors" class="h-link">Browse all doctors <i class="ri-arrow-right-line"></i></a>
    </div>
</section>
<?php endif; ?>

<!-- ============================== CITIES ============================== -->
<?php if ($cities): ?>
<section class="h-section h-cities">
    <div class="container">
        <div class="h-head" data-reveal>
            <div>
                <span class="h-kicker">Explore by location</span>
                <h2 class="h-h2">Find doctors <span class="h-accent">near you</span>.</h2>
            </div>
        </div>
        <div class="h-city-cloud">
            <?php foreach ($cities as $c): ?>
            <a href="/doctors?city=<?= urlencode($c['city']) ?>" data-reveal><i class="ri-map-pin-2-line"></i><?= e($c['city']) ?><sup><?= (int) $c['doctor_count'] ?></sup></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== PHARMACIES ============================== -->
<?php if ($featuredPharmacies): ?>
<section class="h-section h-pharm">
    <div class="container">
        <div class="h-head" data-reveal>
            <div>
                <span class="h-kicker">Medicine stores</span>
                <h2 class="h-h2">Order from <span class="h-accent">verified</span> pharmacies.</h2>
            </div>
            <a href="/pharmacies" class="h-link">All pharmacies <i class="ri-arrow-right-line"></i></a>
        </div>
        <div class="h-pharm-grid">
            <?php foreach ($featuredPharmacies as $ph): ?>
            <a href="<?= e(pharmacy_url($ph['slug'])) ?>" class="h-pharm-card" data-reveal>
                <img src="<?= e(avatar_url($ph['avatar'], $ph['store_name'])) ?>" alt="<?= e($ph['store_name']) ?>" width="56" height="56" loading="lazy">
                <div>
                    <h3><?= e($ph['store_name']) ?></h3>
                    <p><i class="ri-map-pin-line"></i> <?= e($ph['city'] ?: 'Location not set') ?> · <?= (int) $ph['product_count'] ?> listing<?= $ph['product_count'] == 1 ? '' : 's' ?></p>
                </div>
                <i class="ri-arrow-right-up-line h-pharm-arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== TESTIMONIALS ============================== -->
<?php if ($testimonials): ?>
<section class="h-section h-quotes" data-quotes>
    <div class="container">
        <span class="h-kicker" data-reveal>What people say</span>
        <h2 class="sr-only">Testimonials from patients and doctors</h2>
        <div class="h-quote-stage">
            <span class="h-quote-mark" aria-hidden="true">&ldquo;</span>
            <?php foreach ($testimonials as $i => $t): ?>
            <figure class="h-quote<?= $i === 0 ? ' is-active' : '' ?>" data-quote>
                <blockquote><?= e($t['content']) ?></blockquote>
                <figcaption>
                    <img src="<?= e(avatar_url($t['avatar'], $t['name'])) ?>" alt="<?= e($t['name']) ?><?= $t['role'] ? ', ' . e($t['role']) : '' ?>" width="48" height="48" loading="lazy">
                    <span><strong><?= e($t['name']) ?></strong><?= e($t['role']) ?></span>
                    <span class="h-quote-stars" aria-label="<?= (int) $t['rating'] ?> out of 5 stars"><?php for ($s = 0; $s < (int) $t['rating']; $s++): ?><i class="ri-star-fill"></i><?php endfor; ?></span>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
        <?php if (count($testimonials) > 1): ?>
        <div class="h-quote-dots">
            <?php foreach ($testimonials as $i => $t): ?>
            <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-quote-dot="<?= $i ?>" aria-label="Show testimonial <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FAQ ============================== -->
<?php if ($homeFaqs): ?>
<section class="h-section h-faq">
    <div class="container h-faq-grid">
        <div class="h-faq-side" data-reveal>
            <span class="h-kicker">FAQ</span>
            <h2 class="h-h2">Questions patients ask <span class="h-accent">before</span> booking.</h2>
            <a href="/faq" class="h-link">See all FAQs <i class="ri-arrow-right-line"></i></a>
        </div>
        <div class="h-faq-list" data-faq-group>
            <?php foreach ($homeFaqs as $f): ?>
            <div class="h-faq-item">
                <button type="button" class="faq-q"><?= e($f['question']) ?><i class="ri-add-line"></i></button>
                <div class="faq-a"><p><?= e(strip_tags($f['answer'])) ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== CTA ============================== -->
<section class="h-section h-cta">
    <div class="container h-cta-grid">
        <a href="/doctor-register" class="h-cta-panel h-cta-dark" data-reveal>
            <div class="h-parallax h-cta-ring" data-parallax="0.08" aria-hidden="true"></div>
            <span class="h-kicker h-kicker-light">For doctors</span>
            <h2>Grow your practice with <span class="h-accent">a calendar that fills itself.</span></h2>
            <p>Join <?= e(SITE_NAME) ?> to manage appointments, get discovered by patients searching your specialty, and run your clinic's queue.</p>
            <span class="h-cta-btn">Apply as a doctor <i class="ri-arrow-right-up-line"></i></span>
        </a>
        <a href="/pharmacy-register" class="h-cta-panel h-cta-teal" data-reveal>
            <div class="h-parallax h-cta-ring" data-parallax="-0.08" aria-hidden="true"></div>
            <span class="h-kicker h-kicker-light">For pharmacies</span>
            <h2>Put your store <span class="h-accent">online</span> in an afternoon.</h2>
            <p>Register your medicine store, upload your license, and start selling to patients online.</p>
            <span class="h-cta-btn">Register your pharmacy <i class="ri-arrow-right-up-line"></i></span>
        </a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
