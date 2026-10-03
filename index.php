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
$heroDoctors = array_slice($featuredDoctors, 0, 3);

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

// Hero headline, split into per-word spans server-side so the rise-in
// animation needs no JS and the full sentence is still plain text for SEO.
$headline = [['Healthcare', false], ['that', false], ['fits', false], ['your', true], ['schedule,', true], ['not', false], ['a', false], ['waiting', false], ['room.', false]];

$extraHead = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&display=swap">'
    . '<link rel="stylesheet" href="' . asset_url('/assets/css/home.css') . '">';
if ($homeFaqs) {
    $extraHead .= '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
        fn($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f['answer'])]],
        $homeFaqs
    )]) . '</script>';
}
$extraScripts = '<script defer src="' . asset_url('/assets/js/search-suggest.js') . '"></script>'
    . '<script defer src="' . asset_url('/assets/js/home.js') . '"></script>';
require __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="h-hero">
    <div class="h-hero-grain" aria-hidden="true"></div>
    <div class="container h-hero-grid">
        <div class="h-hero-copy">
            <span class="h-eyebrow h-fade" style="--d:0"><span class="h-live-dot"></span> <?= $totalDoctors ?> verified doctor<?= $totalDoctors == 1 ? '' : 's' ?> accepting patients</span>
            <h1 class="h-title">
                <?php foreach ($headline as $i => [$word, $serif]): ?><span class="h-word<?= $serif ? ' h-word-serif' : '' ?>"><span style="--i:<?= $i ?>"><?= e($word) ?></span></span> <?php endforeach; ?>
            </h1>
            <p class="h-lead h-fade" style="--d:6">Search verified specialists, compare fees and reviews, and book an online or in-clinic consultation in under two minutes.</p>

            <form class="search-box h-search h-fade" style="--d:8" id="hero-search-box" action="/doctors" method="get" autocomplete="off">
                <i class="ri-search-line" style="color:var(--color-text-muted);"></i>
                <input type="text" id="hero-search-input" name="q" placeholder="Doctor or condition…" aria-label="Search doctors, conditions or specialties">
                <span class="divider"></span>
                <select name="specialization" aria-label="Specialty">
                    <option value="">All Specialties</option>
                    <?php foreach ($specs as $s): ?>
                    <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="divider"></span>
                <select name="city" aria-label="City">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c['city']) ?>"><?= e($c['city']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>

            <?php if ($specs): ?>
            <div class="h-popular h-fade" style="--d:10">
                <span>Popular:</span>
                <?php foreach (array_slice($specs, 0, 4) as $s): ?>
                <a href="/specializations/<?= e($s['slug']) ?>"><?= e($s['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="h-hero-visual" data-hero-stage aria-hidden="true">
            <div class="h-parallax" data-parallax="-0.06"><div class="h-orb"></div></div>
            <div class="h-parallax h-arch-wrap" data-parallax="0.04">
                <div class="h-arch">
                    <div class="h-arch-pattern"></div>
                    <div class="h-arch-content">
                        <span class="h-arch-num"><?= $totalDoctors ?></span>
                        <span class="h-arch-label">verified doctors across<br><?= $totalSpecs ?> specialt<?= $totalSpecs == 1 ? 'y' : 'ies' ?></span>
                    </div>
                </div>
            </div>
            <div class="h-parallax h-badge-wrap" data-parallax="-0.12">
                <div class="h-badge">
                    <svg viewBox="0 0 120 120"><defs><path id="h-circle" d="M60,60 m-46,0 a46,46 0 1,1 92,0 a46,46 0 1,1 -92,0"/></defs><text><textPath href="#h-circle">VERIFIED DOCTORS · BOOK IN 2 MINUTES · </textPath></text></svg>
                    <i class="ri-shield-check-fill"></i>
                </div>
            </div>
            <?php foreach ($heroDoctors as $i => $d): $specName = specialization_names($d['specializations']) ?: 'General'; ?>
            <div class="h-tile h-tile-<?= $i + 1 ?>" data-depth="<?= [18, 30, 12][$i] ?>">
                <div class="h-tile-inner">
                    <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>" alt="" width="44" height="44">
                    <div>
                        <strong><?= e($d['full_name']) ?></strong>
                        <span><?= e($specName) ?></span>
                    </div>
                    <?php if ((int) $d['rating_count'] > 0): ?>
                    <em><i class="ri-star-fill"></i> <?= number_format((float) $d['rating_avg'], 1) ?></em>
                    <?php else: ?>
                    <em class="h-tile-new">New</em>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="#h-stats" class="h-scroll-cue" aria-label="Scroll to learn more"><span></span></a>
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
                <h2 class="h-h2">One place for <span class="h-serif">patients</span>, doctors and pharmacies.</h2>
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
                <h2 class="h-h2">Find the right <span class="h-serif">specialist</span>, fast.</h2>
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
            <h2 class="h-h2">Booking care in <span class="h-serif">three</span> simple steps.</h2>
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
                <h2 class="h-h2">Meet our <span class="h-serif">featured</span> doctors.</h2>
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
                <h2 class="h-h2">Find doctors <span class="h-serif">near you</span>.</h2>
            </div>
        </div>
        <div class="h-city-cloud">
            <?php foreach ($cities as $c): ?>
            <a href="/doctors?city=<?= urlencode($c['city']) ?>" data-reveal><?= e($c['city']) ?><sup><?= (int) $c['doctor_count'] ?></sup></a>
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
                <h2 class="h-h2">Order from <span class="h-serif">verified</span> pharmacies.</h2>
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
            <h2 class="h-h2">Questions patients ask <span class="h-serif">before</span> booking.</h2>
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
            <h2>Grow your practice with <span class="h-serif">a calendar that fills itself.</span></h2>
            <p>Join <?= e(SITE_NAME) ?> to manage appointments, get discovered by patients searching your specialty, and run your clinic's queue.</p>
            <span class="h-cta-btn">Apply as a doctor <i class="ri-arrow-right-up-line"></i></span>
        </a>
        <a href="/pharmacy-register" class="h-cta-panel h-cta-teal" data-reveal>
            <div class="h-parallax h-cta-ring" data-parallax="-0.08" aria-hidden="true"></div>
            <span class="h-kicker h-kicker-light">For pharmacies</span>
            <h2>Put your store <span class="h-serif">online</span> in an afternoon.</h2>
            <p>Register your medicine store, upload your license, and start selling to patients online.</p>
            <span class="h-cta-btn">Register your pharmacy <i class="ri-arrow-right-up-line"></i></span>
        </a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
