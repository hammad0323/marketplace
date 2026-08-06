</main>

<?php
$footerAbout = mp_get_setting('footer_about_text', '');
$footerContactEmail = mp_get_setting('contact_email', '');
$footerContactPhone = mp_get_setting('contact_phone', '');
$footerSocial = array_filter([
    'Facebook'  => mp_get_setting('social_facebook', ''),
    'Instagram' => mp_get_setting('social_instagram', ''),
    'Twitter'   => mp_get_setting('social_twitter', ''),
]);
?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <span>&copy; <?= date('Y') ?> <?= mp_e(mp_get_setting('site_name', SITE_NAME)) ?></span>
            <?php if ($footerAbout): ?>
                <p style="max-width:360px; margin:.5rem 0 0; opacity:.75; font-size:.85rem;"><?= mp_e($footerAbout) ?></p>
            <?php endif; ?>
            <?php if ($footerContactEmail || $footerContactPhone): ?>
                <p style="margin:.5rem 0 0; font-size:.85rem; opacity:.75;">
                    <?= $footerContactEmail ? mp_e($footerContactEmail) : '' ?>
                    <?= $footerContactEmail && $footerContactPhone ? ' · ' : '' ?>
                    <?= $footerContactPhone ? mp_e($footerContactPhone) : '' ?>
                </p>
            <?php endif; ?>
            <?php if ($footerSocial): ?>
                <nav class="footer-links" style="margin-top:.6rem;">
                    <?php foreach ($footerSocial as $label => $url): ?>
                        <a href="<?= mp_e($url) ?>" target="_blank" rel="noopener"><?= mp_e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
        <nav class="footer-links">
            <a href="<?= mp_e(ROUTE_ARTISAN) ?>index.php">Artisan Marketplace</a>
            <a href="<?= mp_e(ROUTE_BUSINESS) ?>index.php">Business Shops</a>
            <a href="<?= mp_e(ROUTE_OFFICIAL_STORE) ?>index.php">Official Store</a>
            <a href="<?= mp_e(ROUTE_VENDOR) ?>register.php">Become a Vendor</a>
            <a href="<?= mp_e(ROUTE_ADMIN) ?>login.php">Admin</a>
        </nav>
    </div>
</footer>
<script src="<?= mp_e(ROUTE_ASSETS) ?>js/main.js"></script>
</body>
</html>
