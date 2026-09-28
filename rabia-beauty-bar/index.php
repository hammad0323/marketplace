<?php
require __DIR__ . '/config.php';

$categories = q('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$services   = q('SELECT s.*, c.name AS category FROM services s JOIN service_categories c ON c.id = s.category_id
                 WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.sort_order, s.sort_order, s.name')->fetchAll();
$courses    = q('SELECT * FROM courses WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order, id DESC LIMIT 3')->fetchAll();
$reviews    = q('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$gallery    = q('SELECT * FROM gallery ORDER BY sort_order, id DESC LIMIT 8')->fetchAll();

$byCat = [];
foreach ($services as $s) {
    $byCat[$s['category_id']][] = $s;
}
$marquee = array_slice(array_column($services, 'name'), 0, 20);

$activeNav = 'home';
$bodyClass = 'home';
require __DIR__ . '/inc/header.php';
?>

<!-- ============ 1. HERO (parallax) ============ -->
<section class="hero" id="top">
    <div class="hero-bg" data-parallax="0.35"></div>
    <div class="hero-glow glow-1" data-depth="0.04"></div>
    <div class="hero-glow glow-2" data-depth="-0.03"></div>
    <div class="petals" aria-hidden="true">
        <?php for ($i = 0; $i < 14; $i++): ?><span></span><?php endfor; ?>
    </div>

    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="script hero-kicker reveal" data-delay="100"><?= e(setting('tagline')) ?></p>
            <h1 class="hero-title split-text"><?= e(setting('hero_title')) ?></h1>
            <p class="hero-text reveal" data-delay="600"><?= e(setting('hero_text')) ?></p>
            <div class="hero-actions reveal" data-delay="800">
                <a href="booking.php" class="btn btn-primary"><i class="fa-regular fa-calendar-check"></i> Book Appointment</a>
                <a href="courses.php" class="btn btn-ghost-light">Join the Academy</a>
            </div>
            <div class="hero-meta reveal" data-delay="1000">
                <div><strong><?= e(setting('rating')) ?> <i class="fa-solid fa-star"></i></strong><span><?= e(setting('review_count')) ?> Google reviews</span></div>
                <div><strong><?= number_format((int) setting('followers')) ?>+</strong><span>Happy followers</span></div>
                <div><strong><?= count($services) ?>+</strong><span>Beauty services</span></div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="arch" data-depth="0.02">
                <img src="assets/img/model.jpg" alt="Bridal makeup and hairstyling by Rabia Khan">
            </div>
            <div class="orbit orbit-1" data-depth="-0.05"><img src="assets/img/bridal-bun.jpg" alt="Bridal bun"></div>
            <div class="orbit orbit-2" data-depth="0.06"><img src="assets/img/bridal-side.jpg" alt="Side curls hairstyle"></div>
            <div class="float-card" data-depth="-0.04">
                <i class="fa-solid fa-crown"></i>
                <div><strong>Bridal Specialist</strong><span>Makeup · Hair · Nails</span></div>
            </div>
            <div class="sparkle s1">✦</div><div class="sparkle s2">✦</div><div class="sparkle s3">✧</div>
        </div>
    </div>
    <a href="#about" class="scroll-down" aria-label="Scroll down"><span></span></a>
</section>

<!-- ============ 2. MARQUEE ============ -->
<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        <?php for ($r = 0; $r < 2; $r++): foreach ($marquee as $m): ?>
            <span><?= e($m) ?></span><i>✦</i>
        <?php endforeach; endfor; ?>
    </div>
</div>

<!-- ============ 3. ABOUT ============ -->
<section class="section about" id="about">
    <div class="container about-grid">
        <div class="about-media reveal-left">
            <div class="about-img main" data-parallax="-0.08"><img src="assets/img/nails.jpg" alt="Acrylic nail extensions" loading="lazy"></div>
            <div class="about-img small" data-parallax="0.12"><img src="assets/img/brushes.jpg" alt="Professional makeup kit" loading="lazy"></div>
            <div class="about-badge">
                <span class="counter" data-target="<?= e(setting('rating')) ?>" data-decimals="1">0</span>
                <small>Google rating</small>
            </div>
        </div>
        <div class="about-copy reveal-right">
            <p class="eyebrow">About Us</p>
            <h2 class="section-title">A little bar of <em>beauty</em>, a big world of <em>confidence</em></h2>
            <p><?= nl2br(e(setting('about_text'))) ?></p>
            <ul class="check-list">
                <li><i class="fa-solid fa-check"></i> Owner-led service by Rabia Khan</li>
                <li><i class="fa-solid fa-check"></i> Premium, hygienic products &amp; tools</li>
                <li><i class="fa-solid fa-check"></i> Bridal, party &amp; everyday looks</li>
                <li><i class="fa-solid fa-check"></i> Professional academy with certificates</li>
            </ul>
            <div class="signature script">Rabia Khan</div>
        </div>
    </div>
</section>

<!-- ============ 4. SERVICES ============ -->
<section class="section services-sec" id="services">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">What We Do</p>
            <h2 class="section-title">Our Signature <em>Services</em></h2>
            <p class="section-sub">From a quick trim to your big day — every service is delivered with care and artistry.</p>
        </div>
        <div class="service-grid">
            <?php foreach ($categories as $i => $c): ?>
                <a href="services.php#<?= e($c['slug']) ?>" class="service-card tilt reveal" data-delay="<?= ($i % 4) * 120 ?>">
                    <?php if ($c['image']): ?><span class="service-bg" style="background-image:url('<?= img($c['image']) ?>')"></span><?php endif; ?>
                    <div class="service-icon"><i class="fa-solid <?= e($c['icon']) ?>"></i></div>
                    <h3><?= e($c['name']) ?></h3>
                    <p><?= e($c['description']) ?></p>
                    <span class="service-count"><?= count($byCat[$c['id']] ?? []) ?> services <i class="fa-solid fa-arrow-right-long"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ 5. PARALLAX QUOTE BANNER ============ -->
<section class="parallax-banner">
    <div class="parallax-layer" data-parallax="0.4" style="background-image:url('assets/img/nails.jpg')"></div>
    <div class="parallax-overlay"></div>
    <div class="container parallax-content reveal-zoom">
        <p class="script">Learn · Practice · Succeed</p>
        <h2>“Beauty begins the moment you decide to be yourself.”</h2>
        <a href="booking.php" class="btn btn-light">Treat Yourself Today</a>
    </div>
</section>

<!-- ============ 6. PRICE MENU ============ -->
<section class="section menu-sec" id="prices">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Price List</p>
            <h2 class="section-title">The Beauty <em>Menu</em></h2>
        </div>
        <div class="tabs reveal" role="tablist">
            <?php $first = true; foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
                <button class="tab <?= $first ? 'active' : '' ?>" data-tab="cat-<?= $c['id'] ?>"><i class="fa-solid <?= e($c['icon']) ?>"></i> <?= e($c['name']) ?></button>
            <?php $first = false; endforeach; ?>
        </div>
        <div class="menu-card reveal">
            <?php $first = true; foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
                <div class="tab-panel <?= $first ? 'active' : '' ?>" id="cat-<?= $c['id'] ?>">
                    <ul class="price-list">
                        <?php foreach ($byCat[$c['id']] as $s): ?>
                            <li>
                                <span class="pl-name"><?= e($s['name']) ?><?php if ($s['description']): ?><small><?= e($s['description']) ?></small><?php endif; ?></span>
                                <span class="pl-dots"></span>
                                <span class="pl-price"><?= $s['price_from'] && $s['price'] ? 'From ' : '' ?><?= money($s['price']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php $first = false; endforeach; ?>
            <div class="menu-foot">
                <a href="services.php" class="btn btn-outline">Full Services Menu</a>
                <a href="booking.php" class="btn btn-primary">Book a Service</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ 7. ACADEMY / COURSES ============ -->
<section class="section academy" id="academy">
    <div class="academy-bg" data-parallax="0.2"></div>
    <div class="container">
        <div class="section-head light reveal">
            <p class="eyebrow">Beauty Academy</p>
            <h2 class="section-title">Build Your Skills, <em>Create Your Future</em></h2>
            <p class="section-sub">Hands-on professional courses taught by Rabia Khan. Limited seats in every batch.</p>
        </div>
        <div class="course-grid">
            <?php foreach ($courses as $i => $c): ?>
                <article class="course-card reveal" data-delay="<?= $i * 150 ?>">
                    <a href="course.php?slug=<?= e($c['slug']) ?>" class="course-img">
                        <img src="<?= img($c['image']) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
                        <?php if ($c['duration']): ?><span class="course-pill"><i class="fa-regular fa-clock"></i> <?= e($c['duration']) ?></span><?php endif; ?>
                    </a>
                    <div class="course-body">
                        <h3><a href="course.php?slug=<?= e($c['slug']) ?>"><?= e($c['title']) ?></a></h3>
                        <p><?= e($c['tagline']) ?></p>
                        <ul class="course-meta">
                            <?php if ($c['schedule']): ?><li><i class="fa-regular fa-calendar"></i> <?= e($c['schedule']) ?></li><?php endif; ?>
                            <?php if ($c['timing']): ?><li><i class="fa-regular fa-clock"></i> <?= e($c['timing']) ?></li><?php endif; ?>
                            <?php if ($c['extras']): ?><li><i class="fa-solid fa-award"></i> <?= e($c['extras']) ?></li><?php endif; ?>
                        </ul>
                        <div class="course-foot">
                            <span class="fee"><?= money($c['fee'], 'Contact us') ?></span>
                            <a href="course.php?slug=<?= e($c['slug']) ?>#enroll" class="btn btn-primary btn-sm">Enroll Now</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="center reveal"><a href="courses.php" class="btn btn-ghost-light">View All Courses</a></div>
    </div>
</section>

<!-- ============ 8. WHY US + COUNTERS ============ -->
<section class="section why">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Why Choose Us</p>
            <h2 class="section-title">Care You Can <em>Feel</em></h2>
        </div>
        <div class="why-grid">
            <?php
            $why = [
                ['fa-user-tie',       'Expert Artists',     'Owner-led, trained professionals who stay current with every trend.'],
                ['fa-pump-soap',      'Hygiene First',      'Sanitised tools, fresh disposables and a spotless studio every day.'],
                ['fa-gem',            'Premium Products',   'Quality brands that are kind to your skin, hair and nails.'],
                ['fa-heart',          'Caring Staff',       'A warm, welcoming space where you can truly relax.'],
            ];
            foreach ($why as $i => [$icon, $t, $d]): ?>
                <div class="why-item reveal" data-delay="<?= $i * 120 ?>">
                    <div class="why-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                    <h3><?= $t ?></h3>
                    <p><?= $d ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="stats reveal">
            <div class="stat"><span class="counter" data-target="<?= (int) setting('followers') ?>">0</span><small>Instagram family</small></div>
            <div class="stat"><span class="counter" data-target="<?= (int) setting('review_count') ?>">0</span><small>5-star style reviews</small></div>
            <div class="stat"><span class="counter" data-target="<?= count($services) ?>">0</span><small>Beauty services</small></div>
            <div class="stat"><span class="counter" data-target="<?= (int) q('SELECT COUNT(*) FROM courses WHERE is_active=1')->fetchColumn() ?>">0</span><small>Pro courses</small></div>
        </div>
    </div>
</section>

<!-- ============ 9. GALLERY ============ -->
<section class="section gallery-sec" id="gallery">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Our Work</p>
            <h2 class="section-title">The <em>Gallery</em></h2>
        </div>
        <div class="gallery">
            <?php foreach ($gallery as $i => $g): ?>
                <a href="<?= img($g['image']) ?>" class="gallery-item reveal-zoom" data-delay="<?= ($i % 4) * 100 ?>" data-caption="<?= e($g['caption']) ?>">
                    <img src="<?= img($g['image']) ?>" alt="<?= e($g['caption']) ?>" loading="lazy">
                    <span class="gallery-cap"><i class="fa-solid fa-magnifying-glass-plus"></i> <?= e($g['caption']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="center reveal">
            <a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" class="btn btn-outline"><i class="fa-brands fa-instagram"></i> Follow @rabiakhans_beauty_bar</a>
        </div>
    </div>
</section>

<!-- ============ 10. TESTIMONIALS ============ -->
<section class="section testimonials">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">Kind Words</p>
            <h2 class="section-title">Loved by Our <em>Clients</em></h2>
            <p class="section-sub"><i class="fa-solid fa-star gold"></i> <?= e(setting('rating')) ?> average from <?= e(setting('review_count')) ?> Google reviews</p>
        </div>
        <div class="slider reveal" id="reviewSlider">
            <div class="slides">
                <?php foreach ($reviews as $r): ?>
                    <blockquote class="slide">
                        <i class="fa-solid fa-quote-left quote-mark"></i>
                        <p><?= e($r['text']) ?></p>
                        <div class="stars"><?= str_repeat('<i class="fa-solid fa-star"></i>', max(1, min(5, (int) $r['rating']))) ?></div>
                        <cite><?= e($r['name']) ?></cite>
                    </blockquote>
                <?php endforeach; ?>
            </div>
            <div class="slider-dots"></div>
        </div>
    </div>
</section>

<!-- ============ 11. BOOKING CTA (parallax) ============ -->
<section class="section booking-cta" id="book">
    <div class="parallax-layer" data-parallax="0.3" style="background-image:url('assets/img/model.jpg')"></div>
    <div class="parallax-overlay dark"></div>
    <div class="container booking-cta-grid">
        <div class="reveal-left light-text">
            <p class="eyebrow">Appointments</p>
            <h2 class="section-title">Reserve Your <em>Glow</em> Moment</h2>
            <p>Pick a service, choose a date and time that suits you, and we'll confirm your appointment by phone or WhatsApp.</p>
            <a href="<?= e(whatsapp_link('Hi! I would like to book an appointment.')) ?>" target="_blank" rel="noopener" class="btn btn-ghost-light"><i class="fa-brands fa-whatsapp"></i> Book on WhatsApp</a>
        </div>
        <form action="booking.php" method="get" class="quick-book reveal-right">
            <h3>Quick Booking</h3>
            <label>Service
                <select name="service">
                    <?php foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
                        <optgroup label="<?= e($c['name']) ?>">
                            <?php foreach ($byCat[$c['id']] as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Preferred date
                <input type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </label>
            <button class="btn btn-primary btn-block">Continue Booking <i class="fa-solid fa-arrow-right"></i></button>
        </form>
    </div>
</section>

<!-- ============ 12. CONTACT + MAP ============ -->
<section class="section contact-sec" id="contact">
    <div class="container contact-grid">
        <div class="contact-cards reveal-left">
            <p class="eyebrow">Visit the Studio</p>
            <h2 class="section-title">Find <em>Us</em></h2>
            <div class="info-card"><i class="fa-solid fa-location-dot"></i><div><strong>Address</strong><span><?= e(setting('address')) ?></span></div></div>
            <div class="info-card"><i class="fa-solid fa-phone"></i><div><strong>Call / WhatsApp</strong><a href="tel:<?= e(preg_replace('/\s/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></div></div>
            <div class="info-card"><i class="fa-regular fa-clock"></i><div><strong>Hours</strong><span><?= e(setting('hours')) ?></span></div></div>
            <a href="contact.php" class="btn btn-primary">Send us a message</a>
        </div>
        <div class="map-wrap reveal-right">
            <iframe title="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q=<?= rawurlencode(setting('map_query')) ?>&z=16&output=embed"></iframe>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" aria-hidden="true">
    <button class="lb-close" aria-label="Close">&times;</button>
    <button class="lb-prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
    <figure><img src="" alt=""><figcaption></figcaption></figure>
    <button class="lb-next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
