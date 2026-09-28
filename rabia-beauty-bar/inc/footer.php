</main>

<footer class="site-footer">
    <div class="footer-petals" aria-hidden="true"></div>
    <div class="container footer-grid">
        <div>
            <a href="index.php" class="brand brand-light">
                <span class="brand-mark">RK</span>
                <span class="brand-text"><strong>Rabia Khan's</strong><small>Beauty Bar &amp; Academy</small></span>
            </a>
            <p class="footer-tag script"><?= e(setting('tagline')) ?></p>
            <p>Empowering beauty, inspiring confidence.</p>
            <div class="socials">
                <a href="<?= e(setting('instagram')) ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="<?= e(setting('facebook')) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            </div>
        </div>
        <div>
            <h4>Explore</h4>
            <ul>
                <li><a href="services.php">Services &amp; Prices</a></li>
                <li><a href="courses.php">Beauty Academy</a></li>
                <li><a href="booking.php">Book an Appointment</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </div>
        <div>
            <h4>Visit Us</h4>
            <ul class="contact-list">
                <li><i class="fa-solid fa-location-dot"></i><?= e(setting('address')) ?></li>
                <li><i class="fa-solid fa-phone"></i><a href="tel:<?= e(preg_replace('/\s/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
                <li><i class="fa-regular fa-clock"></i><?= e(setting('hours')) ?></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">© <?= date('Y') ?> <?= e(setting('site_name')) ?>. All rights reserved.</div>
    </div>
</footer>

<a href="<?= e(whatsapp_link('Hi! I would like to book an appointment.')) ?>" class="whatsapp-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
<button class="back-to-top" id="backToTop" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>

<script src="assets/js/main.js?v=1"></script>
</body>
</html>
