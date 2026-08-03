<header class="site-nav">
    <div class="site-nav-inner">
        <a href="/" class="site-brand"><?= e(config_get('app_name')) ?></a>

        <nav class="marketplace-nav">
            <a href="/artisan">Artisan Marketplace</a>
            <a href="/business">Business Shops</a>
            <a href="/store/official-store">Official Store</a>
        </nav>

        <form method="get" action="/search" class="site-search">
            <input type="search" name="q" placeholder="Search all marketplaces&hellip;" value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit">Search</button>
        </form>

        <nav class="account-nav">
            <?php if (Auth::vendor()): ?>
                <a href="/vendor/dashboard">My Store</a>
            <?php else: ?>
                <a href="/vendor/login">Vendor Login</a>
                <a href="/vendor/register" class="cta-link">Sell With Us</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
