<?php
$footerSpecs = mysqli_query(db(), 'SELECT name, slug FROM specializations WHERE is_active = 1 ORDER BY sort_order LIMIT 6');
?>
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="brand" style="color:#fff;margin-bottom:14px;">
                    <span class="brand-mark"><i class="ri-heart-pulse-fill"></i></span>
                    <?= brand_wordmark_html() ?>
                </div>
                <p style="max-width:280px;font-size:14px;margin-bottom:20px;"><?= e(get_setting('site_tagline', 'Trusted care, one click away.')) ?></p>
                <div class="social-row">
                    <a href="#" aria-label="Facebook"><i class="ri-facebook-fill"></i></a>
                    <a href="#" aria-label="Twitter"><i class="ri-twitter-x-fill"></i></a>
                    <a href="#" aria-label="Instagram"><i class="ri-instagram-line"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="ri-linkedin-fill"></i></a>
                </div>
            </div>
            <div>
                <h5>Specializations</h5>
                <ul>
                    <?php while ($s = mysqli_fetch_assoc($footerSpecs)): ?>
                    <li><a href="/doctors?specialization=<?= e($s['slug']) ?>"><?= e($s['name']) ?></a></li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div>
                <h5>Company</h5>
                <ul>
                    <li><a href="/about">About Us</a></li>
                    <li><a href="/blog">Blog</a></li>
                    <li><a href="/medicines">Medicine Info</a></li>
                    <li><a href="/pharmacies">Pharmacies</a></li>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/faq">FAQs</a></li>
                    <li><a href="/doctor-register">Join as a Doctor</a></li>
                    <li><a href="/pharmacy-register">Register Your Pharmacy</a></li>
                </ul>
            </div>
            <div>
                <h5>Legal</h5>
                <ul>
                    <li><a href="/privacy-policy">Privacy Policy</a></li>
                    <li><a href="/terms">Terms &amp; Conditions</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
            <span>Made for better healthcare access.</span>
        </div>
    </div>
</footer>

<?php require __DIR__ . '/auth-modal.php'; ?>

<script>
window.APP = { loggedIn: <?= is_logged_in() ? 'true' : 'false' ?>, role: <?= json_encode(current_role()) ?>, csrfToken: <?= json_encode(csrf_token()) ?>, currencySymbol: <?= json_encode(get_setting('currency_symbol', '$')) ?> };
</script>
<script src="/assets/js/vendor/jquery.min.js"></script>
<script src="/assets/js/toast.js"></script>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/auth-modal.js"></script>
<?php foreach (($flash ?? flash_get()) as $f): ?>
<script>document.addEventListener('DOMContentLoaded', function(){ showToast('<?= e($f['type']) ?>', '<?= $f['type'] === 'success' ? 'Success' : ($f['type'] === 'error' ? 'Error' : 'Notice') ?>', '<?= e(addslashes($f['message'])) ?>'); });</script>
<?php endforeach; ?>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
