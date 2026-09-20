<?php
require __DIR__ . '/config/config.php';

$pageTitle = SITE_NAME . ' — Book Verified Doctors Online & In-Person';
$metaDescription = get_setting('site_tagline') . '. Search verified specialists, book online or in-clinic appointments, and manage your care in one place.';

$specs = mysqli_query(db(), 'SELECT id, name, slug, icon, description FROM specializations WHERE is_active = 1 ORDER BY sort_order LIMIT 10');

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
    LIMIT 6
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

$testimonials = mysqli_query(db(), 'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order LIMIT 3');
$homeFaqs = mysqli_query(db(), 'SELECT question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order LIMIT 5')->fetch_all(MYSQLI_ASSOC);
$topCityNames = array_slice(array_column($cities, 'city'), 0, 6);

$totalDoctors = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'];
$totalAppointments = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM appointments"))['c'];
$totalSpecs = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM specializations WHERE is_active=1"))['c'];
$ratingRow = mysqli_fetch_assoc(mysqli_query(db(), "
    SELECT AVG(rating_avg) AS avg_rating, SUM(rating_count) AS total_reviews
    FROM doctors WHERE verification_status = 'verified' AND rating_count > 0
"));
$avgRating = $ratingRow && $ratingRow['avg_rating'] ? round((float) $ratingRow['avg_rating'], 1) : null;
$totalReviews = $ratingRow ? (int) $ratingRow['total_reviews'] : 0;

$extraScripts = '<script defer src="' . asset_url('/assets/js/search-suggest.js') . '"></script>';
if ($homeFaqs) {
    $extraHead = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
        fn($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]],
        $homeFaqs
    )]) . '</script>';
}
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="floating-shape" style="width:70px;height:70px;background:var(--color-accent);opacity:0.5;top:140px;left:6%;"></div>
    <div class="floating-shape" style="width:40px;height:40px;background:var(--color-primary);opacity:0.4;top:280px;left:14%;animation-delay:1.2s;"></div>
    <div class="container hero-grid">
        <div data-reveal="left">
            <span class="eyebrow"><i class="ri-verified-badge-fill"></i> Trusted by <?= (int)$totalDoctors ?>+ verified doctors</span>
            <h1>Healthcare that fits <span class="text-gradient">your schedule</span>, not a waiting room.</h1>
            <p class="lead">Search verified specialists, compare fees and reviews, and book an online or in-clinic consultation in under two minutes.</p>

            <form class="search-box" id="hero-search-box" action="/doctors" method="get" style="margin-bottom:32px;" autocomplete="off">
                <i class="ri-search-line" style="color:var(--color-text-muted);"></i>
                <input type="text" id="hero-search-input" name="q" placeholder="Search doctor, condition, or specialization…">
                <span class="divider"></span>
                <select name="specialization">
                    <option value="">All Specialties</option>
                    <?php mysqli_data_seek($specs, 0); while ($s = mysqli_fetch_assoc($specs)): ?>
                    <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <span class="divider"></span>
                <select name="city">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c['city']) ?>"><?= e($c['city']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <div class="hero-stats">
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalDoctors ?>"><?= (int)$totalDoctors ?></b><span>Verified Doctors</span></div>
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalAppointments ?>"><?= (int)$totalAppointments ?></b><span>Appointments Booked</span></div>
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalSpecs ?>"><?= (int)$totalSpecs ?></b><span>Specializations</span></div>
                <?php if ($avgRating !== null): ?>
                <div class="hero-stat"><b class="counter" data-counter="<?= e($avgRating) ?>" data-suffix="/5"><?= e($avgRating) ?>/5</b><span>Average Rating</span></div>
                <?php else: ?>
                <div class="hero-stat"><b>100%</b><span>Credential-Verified</span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-visual" data-reveal="right">
            <div class="hero-card-float" style="top:10%;left:6%;">
                <span class="icon-badge" style="background:var(--color-success);"><i class="ri-checkbox-circle-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">Appointment Confirmed</strong><span style="font-size:12.5px;color:var(--color-text-muted);">Online consultation — Today, 10:00 AM</span></div>
            </div>
            <div class="hero-card-float" style="top:44%;right:2%;animation-delay:0.6s;">
                <span class="icon-badge" style="background:var(--color-primary);"><i class="ri-video-chat-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">Online Consultation</strong><span style="font-size:12.5px;color:var(--color-text-muted);">Starts in 5 minutes</span></div>
            </div>
            <?php if ($avgRating !== null): ?>
            <div class="hero-card-float" style="bottom:6%;left:14%;animation-delay:1.1s;">
                <span class="icon-badge" style="background:var(--color-warning);"><i class="ri-star-fill"></i></span>
                <div><strong style="display:block;font-size:14px;"><?= e($avgRating) ?> average rating</strong><span style="font-size:12.5px;color:var(--color-text-muted);">from <?= number_format($totalReviews) ?> review<?= $totalReviews == 1 ? '' : 's' ?></span></div>
            </div>
            <?php else: ?>
            <div class="hero-card-float" style="bottom:6%;left:14%;animation-delay:1.1s;">
                <span class="icon-badge" style="background:var(--color-warning);"><i class="ri-shield-check-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">Manually verified</strong><span style="font-size:12.5px;color:var(--color-text-muted);">Every doctor credential-checked</span></div>
            </div>
            <?php endif; ?>
            <div style="position:absolute;inset:14% 10%;border-radius:32px;background:var(--gradient-primary);opacity:0.12;"></div>
        </div>
    </div>
</section>

<section class="section" style="padding-bottom:0;">
    <div class="container">
        <div class="section-head" style="max-width:760px;" data-reveal>
            <span class="eyebrow">Who We Serve</span>
            <h2>Healthcare for patients, care teams for doctors and pharmacies</h2>
            <p>
                <?= e(get_setting('site_name', SITE_NAME)) ?> connects patients with <?= (int) $totalDoctors ?> verified doctor<?= $totalDoctors == 1 ? '' : 's' ?> across <?= (int) $totalSpecs ?> specialt<?= $totalSpecs == 1 ? 'y' : 'ies' ?><?php if ($topCityNames): ?>, currently practicing in <?= e(implode(', ', array_slice($topCityNames, 0, -1)) . (count($topCityNames) > 1 ? ' and ' . end($topCityNames) : $topCityNames[0])) ?><?php endif; ?>.
                Every doctor is manually credential-checked before they can accept patients, whether the visit is a five-minute online follow-up or an in-person specialist consultation.
                Doctors get a bookable public profile and calendar; verified pharmacies get an online storefront to sell directly to patients.
            </p>
        </div>
    </div>
</section>

<section class="section" id="specializations">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Browse by Specialty</span>
            <h2>Find the right specialist, fast</h2>
            <p>Every doctor is verified by our medical credentialing team before they can accept patients.</p>
        </div>
        <div class="grid grid-4 stagger">
            <?php mysqli_data_seek($specs, 0); while ($s = mysqli_fetch_assoc($specs)): ?>
            <a href="/specializations/<?= e($s['slug']) ?>" class="card card-hover spec-card" data-reveal data-tilt>
                <div class="icon"><i class="<?= e($s['icon']) ?>"></i></div>
                <h3><?= e($s['name']) ?></h3>
                <p><?= e($s['description']) ?></p>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<?php if (count($cities) > 0): ?>
<section class="section" style="padding-top:0;">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Explore by Location</span>
            <h2>Find doctors near you</h2>
            <p>Browse verified doctors practicing in your city.</p>
        </div>
        <div class="city-carousel-wrap" data-reveal data-carousel>
            <button type="button" class="carousel-nav-btn" data-carousel-prev aria-label="Scroll left"><i class="ri-arrow-left-s-line"></i></button>
            <div class="city-carousel-track" data-carousel-track>
                <?php foreach ($cities as $c): ?>
                <a href="/doctors?city=<?= e($c['city']) ?>" class="city-chip">
                    <span class="city-chip-icon"><i class="ri-map-pin-2-fill"></i></span>
                    <span class="city-chip-name"><?= e($c['city']) ?></span>
                    <span class="city-chip-count"><?= (int) $c['doctor_count'] ?> doctor<?= $c['doctor_count'] == 1 ? '' : 's' ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="carousel-nav-btn" data-carousel-next aria-label="Scroll right"><i class="ri-arrow-right-s-line"></i></button>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="background:var(--color-surface);">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Top Rated</span>
            <h2>Meet our featured doctors</h2>
            <p>Highly rated, premium-verified specialists accepting new patients this week.</p>
        </div>
        <div class="grid grid-3 stagger">
            <?php foreach ($featuredDoctors as $d): require __DIR__ . '/includes/doctor-card.php'; endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:44px;" data-reveal>
            <a href="/doctors" class="btn btn-primary">Browse All Doctors <i class="ri-arrow-right-line"></i></a>
        </div>
    </div>
</section>

<?php if (count($featuredPharmacies) > 0): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Medicine Stores</span>
            <h2>Order from verified pharmacies</h2>
            <p>Registered, license-verified pharmacies selling medicines directly to you.</p>
        </div>
        <div class="grid grid-3 stagger">
            <?php foreach ($featuredPharmacies as $ph): ?>
            <a href="<?= e(pharmacy_url($ph['slug'])) ?>" class="card card-hover" style="padding:20px;display:block;" data-reveal data-tilt>
                <img src="<?= e(avatar_url($ph['avatar'], $ph['store_name'])) ?>" alt="<?= e($ph['store_name']) ?>" width="52" height="52" loading="lazy" style="width:52px;height:52px;border-radius:14px;object-fit:cover;margin-bottom:14px;">
                <h3 style="font-size:16px;margin-bottom:6px;"><?= e($ph['store_name']) ?></h3>
                <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;"><i class="ri-map-pin-line"></i> <?= e($ph['city'] ?: 'Location not set') ?></p>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
                    <span style="font-size:12.5px;color:var(--color-text-muted);"><?= (int) $ph['product_count'] ?> listing<?= $ph['product_count'] == 1 ? '' : 's' ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:44px;" data-reveal>
            <a href="/pharmacies" class="btn btn-primary">Browse All Pharmacies <i class="ri-arrow-right-line"></i></a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">How It Works</span>
            <h2>Booking care in three simple steps</h2>
        </div>
        <div class="grid grid-3 stagger">
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-search-eye-line"></i></div>
                <h3 style="margin-bottom:8px;font-size:17px;">1. Search</h3>
                <p style="color:var(--color-text-muted);font-size:14.5px;">Filter by specialty, fee, rating, or availability to find the right doctor.</p>
            </div>
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-calendar-check-line"></i></div>
                <h3 style="margin-bottom:8px;font-size:17px;">2. Book</h3>
                <p style="color:var(--color-text-muted);font-size:14.5px;">Pick an open slot from the doctor's live calendar — online or in-clinic.</p>
            </div>
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-heart-pulse-line"></i></div>
                <h3 style="margin-bottom:8px;font-size:17px;">3. Consult</h3>
                <p style="color:var(--color-text-muted);font-size:14.5px;">Meet your doctor at the scheduled time and manage everything from your dashboard.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background:var(--color-surface);">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">Testimonials</span>
            <h2>Loved by patients and doctors alike</h2>
        </div>
        <div class="grid grid-3 stagger">
            <?php while ($t = mysqli_fetch_assoc($testimonials)): ?>
            <div class="card card-hover" style="padding:28px;" data-reveal>
                <div class="rating" style="margin-bottom:14px;">
                    <?php for ($i = 0; $i < $t['rating']; $i++): ?><i class="ri-star-fill"></i><?php endfor; ?>
                </div>
                <p style="margin-bottom:18px;font-size:14.5px;">&ldquo;<?= e($t['content']) ?>&rdquo;</p>
                <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= e(avatar_url($t['avatar'], $t['name'])) ?>" alt="<?= e($t['name']) ?><?= $t['role'] ? ', ' . $t['role'] : '' ?>" width="40" height="40" loading="lazy" style="width:40px;height:40px;border-radius:50%;">
                    <div><strong style="display:block;font-size:14px;"><?= e($t['name']) ?></strong><span style="font-size:12.5px;color:var(--color-text-muted);"><?= e($t['role']) ?></span></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<?php if ($homeFaqs): ?>
<section class="section" style="background:var(--color-surface);">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">FAQ</span>
            <h2>Questions patients ask before booking</h2>
        </div>
        <div class="grid grid-2 stagger" style="max-width:920px;margin:0 auto;">
            <?php foreach ($homeFaqs as $f): ?>
            <div class="card" style="padding:24px;" data-reveal>
                <h3 style="font-size:16px;margin-bottom:8px;"><?= e($f['question']) ?></h3>
                <p style="color:var(--color-text-muted);font-size:14px;"><?= e($f['answer']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:32px;" data-reveal>
            <a href="/faq" style="color:var(--color-primary);font-weight:600;">See all FAQs <i class="ri-arrow-right-line"></i></a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="grid grid-2" style="gap:24px;">
            <div class="card-gradient-border" data-reveal="zoom">
                <div class="card-inner" style="padding:48px 32px;text-align:center;">
                    <h2 style="margin-bottom:12px;font-size:24px;">Are you a doctor?</h2>
                    <p style="color:var(--color-text-muted);margin-bottom:24px;">Join <?= e(SITE_NAME) ?> to manage your appointments, grow your patient base, and get discovered by patients searching for your specialty.</p>
                    <a href="/doctor-register" class="btn btn-primary">Apply as a Doctor <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>
            <div class="card-gradient-border" data-reveal="zoom">
                <div class="card-inner" style="padding:48px 32px;text-align:center;">
                    <h2 style="margin-bottom:12px;font-size:24px;">Have a pharmacy?</h2>
                    <p style="color:var(--color-text-muted);margin-bottom:24px;">Register your medicine store on <?= e(SITE_NAME) ?>, upload your license, and start selling to patients online.</p>
                    <a href="/pharmacy-register" class="btn btn-primary">Register Your Pharmacy <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
