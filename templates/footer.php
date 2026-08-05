</main>

<footer class="site-footer">
    <div class="site-footer-inner">
        <span>&copy; <?= date('Y') ?> <?= mp_e(SITE_NAME) ?></span>
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
