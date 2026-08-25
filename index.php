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

$testimonials = mysqli_query(db(), 'SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order LIMIT 3');

$totalDoctors = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'];
$totalAppointments = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM appointments"))['c'];
$totalSpecs = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM specializations WHERE is_active=1"))['c'];

$extraScripts = '<script src="/assets/js/search-suggest.js"></script>';
require __DIR__ . '/includes/header.php';
?>

<script type="application/ld+json">
{"@context":"https://schema.org","@type":"MedicalOrganization","name":<?= json_encode(SITE_NAME) ?>,"url":<?= json_encode(APP_URL) ?>,"description":<?= json_encode($metaDescription) ?>}
</script>

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
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalDoctors ?>">0</b><span>Verified Doctors</span></div>
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalAppointments ?>">0</b><span>Appointments Booked</span></div>
                <div class="hero-stat"><b class="counter" data-counter="<?= (int)$totalSpecs ?>">0</b><span>Specializations</span></div>
                <div class="hero-stat"><b class="counter" data-counter="4.8" data-suffix="/5">0</b><span>Average Rating</span></div>
            </div>
        </div>
        <div class="hero-visual" data-reveal="right">
            <div class="hero-card-float" style="top:10%;left:6%;">
                <span class="icon-badge" style="background:var(--color-success);"><i class="ri-checkbox-circle-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">Appointment Confirmed</strong><span style="font-size:12.5px;color:var(--color-text-muted);">Dr. Sarah Chen — Today, 10:00 AM</span></div>
            </div>
            <div class="hero-card-float" style="top:44%;right:2%;animation-delay:0.6s;">
                <span class="icon-badge" style="background:var(--color-primary);"><i class="ri-video-chat-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">Online Consultation</strong><span style="font-size:12.5px;color:var(--color-text-muted);">Starts in 5 minutes</span></div>
            </div>
            <div class="hero-card-float" style="bottom:6%;left:14%;animation-delay:1.1s;">
                <span class="icon-badge" style="background:var(--color-warning);"><i class="ri-star-fill"></i></span>
                <div><strong style="display:block;font-size:14px;">4.9 average rating</strong><span style="font-size:12.5px;color:var(--color-text-muted);">from 1,200+ reviews</span></div>
            </div>
            <div style="position:absolute;inset:14% 10%;border-radius:32px;background:var(--gradient-primary);opacity:0.12;"></div>
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
            <a href="/doctors?specialization=<?= e($s['slug']) ?>" class="card card-hover spec-card" data-reveal data-tilt>
                <div class="icon"><i class="<?= e($s['icon']) ?>"></i></div>
                <h4><?= e($s['name']) ?></h4>
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
            <?php foreach ($featuredDoctors as $d): ?>
            <div class="card card-hover doctor-card" data-reveal data-tilt>
                <div class="doctor-card-top">
                    <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>" alt="<?= e($d['full_name']) ?>">
                    <div>
                        <h3><?= e($d['full_name']) ?></h3>
                        <div class="spec"><?= e(specialization_names($d['specializations']) ?: 'General') ?></div>
                        <div class="rating"><i class="ri-star-fill"></i> <?= number_format($d['rating_avg'], 1) ?> (<?= (int)$d['rating_count'] ?>)</div>
                    </div>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
                    <?php if ($d['is_premium']): ?><span class="badge badge-premium"><i class="ri-vip-crown-fill"></i> Premium</span><?php endif; ?>
                    <?php if ($d['free_consultation']): ?><span class="badge badge-free">Free Consult</span><?php endif; ?>
                </div>
                <div class="doctor-card-meta">
                    <span><i class="ri-briefcase-line"></i> <?= (int)$d['experience_years'] ?> yrs exp</span>
                    <span><i class="ri-map-pin-line"></i> <?= e($d['clinic_city'] ?: 'Online') ?></span>
                </div>
                <div class="doctor-card-footer">
                    <div class="fee"><?= format_currency($d['consultation_fee_online']) ?> <small>/ online</small></div>
                    <a href="<?= e(doctor_url($d['slug'])) ?>" class="btn btn-outline btn-sm">View Profile</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:44px;" data-reveal>
            <a href="/doctors" class="btn btn-primary">Browse All Doctors <i class="ri-arrow-right-line"></i></a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">How It Works</span>
            <h2>Booking care in three simple steps</h2>
        </div>
        <div class="grid grid-3 stagger">
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-search-eye-line"></i></div>
                <h4 style="margin-bottom:8px;">1. Search</h4>
                <p style="color:var(--color-text-muted);font-size:14.5px;">Filter by specialty, fee, rating, or availability to find the right doctor.</p>
            </div>
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-calendar-check-line"></i></div>
                <h4 style="margin-bottom:8px;">2. Book</h4>
                <p style="color:var(--color-text-muted);font-size:14.5px;">Pick an open slot from the doctor's live calendar — online or in-clinic.</p>
            </div>
            <div class="card card-hover" style="padding:32px;text-align:center;" data-reveal>
                <div class="icon" style="margin:0 auto 20px;width:60px;height:60px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;"><i class="ri-heart-pulse-line"></i></div>
                <h4 style="margin-bottom:8px;">3. Consult</h4>
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
                    <img src="<?= e(avatar_url($t['avatar'], $t['name'])) ?>" alt="" style="width:40px;height:40px;border-radius:50%;">
                    <div><strong style="display:block;font-size:14px;"><?= e($t['name']) ?></strong><span style="font-size:12.5px;color:var(--color-text-muted);"><?= e($t['role']) ?></span></div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="card-gradient-border" data-reveal="zoom">
            <div class="card-inner" style="padding:56px 40px;text-align:center;">
                <h2 style="margin-bottom:12px;">Are you a doctor?</h2>
                <p style="color:var(--color-text-muted);margin-bottom:28px;max-width:480px;margin-left:auto;margin-right:auto;">Join MediConnect to manage your appointments, grow your patient base, and get discovered by patients searching for your specialty.</p>
                <a href="/doctor-register" class="btn btn-primary">Apply as a Doctor <i class="ri-arrow-right-line"></i></a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
