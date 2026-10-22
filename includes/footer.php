<?php
/** Storefront footer + scripts. */
$social = array_filter([
    'instagram' => setting('social_instagram'), 'facebook' => setting('social_facebook'), 'tiktok' => setting('social_tiktok'),
    'youtube' => setting('social_youtube'), 'whatsapp' => setting('social_whatsapp'),
]);
$footerMenus = ['footer_shop' => 'Shop', 'footer_help' => 'Customer Care', 'footer_about' => 'Urban Collections'];
$minimal = theme('theme_footer_layout') === 'minimal';
?>
</main>

<footer class="uc-footer<?= $minimal ? ' is-minimal' : '' ?>">
    <div class="uc-container">
        <?php if (!$minimal): ?>
        <div class="uc-footer-grid">
            <div class="uc-footer-brand">
                <a class="uc-logo uc-logo--footer" href="<?= e(url()) ?>">
                    <img src="<?= e(img_url(setting('logo', 'assets/images/brand/logo-round.webp'))) ?>" alt="<?= e(store_name()) ?>" width="64" height="64" loading="lazy">
                    <span class="uc-logo-text"><span>Urban</span><small>Collections</small></span>
                </a>
                <p><?= e(setting('footer_about')) ?></p>
                <ul class="uc-contact-list">
                    <?php if (setting('store_phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li><?php endif; ?>
                    <?php if (setting('store_email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li><?php endif; ?>
                    <?php if (setting('store_hours')): ?><li><i class="bi bi-clock"></i><?= e(setting('store_hours')) ?></li><?php endif; ?>
                </ul>
            </div>
            <?php foreach ($footerMenus as $menu => $title): $items = nav_items($menu); if (!$items) continue; ?>
                <div class="uc-footer-col">
                    <button class="uc-footer-title" type="button" data-footer-toggle aria-expanded="false"><?= e($title) ?><i class="bi bi-plus-lg d-md-none"></i></button>
                    <ul>
                        <?php foreach ($items as $it): ?>
                            <li><a href="<?= e(nav_url($it)) ?>"<?= (int) $it['open_new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
            <div class="uc-footer-col uc-footer-news">
                <p class="uc-footer-title">Newsletter</p>
                <p class="small">New arrivals, private sales and style notes.</p>
                <form class="uc-news-form" data-newsletter-form novalidate>
                    <div class="uc-news-inline">
                        <input type="email" name="email" placeholder="Your email address" aria-label="Email address" required>
                        <button type="submit" aria-label="Subscribe"><i class="bi bi-arrow-right"></i></button>
                    </div>
                    <label class="uc-check small"><input type="checkbox" name="consent" value="1" required> <span>I agree to receive marketing emails.</span></label>
                    <input type="hidden" name="source" value="footer">
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="uc-footer-bottom">
            <p class="mb-0"><?= e(str_replace('{year}', date('Y'), setting('copyright_text', '© {year} Urban Collections'))) ?></p>
            <?php if ($social): ?>
                <ul class="uc-social">
                    <?php foreach ($social as $net => $link): ?>
                        <li><a href="<?= e($net === 'whatsapp' && !str_starts_with($link, 'http') ? 'https://wa.me/' . preg_replace('/\D/', '', $link) : $link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($net)) ?>"><i class="bi bi-<?= e($net) ?>"></i></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="uc-pay-icons" aria-label="Payment methods">
                <?php foreach (payment_methods_all() as $pm): if (payment_method_state($pm) !== 'ready') continue; ?>
                    <span><?= e($pm['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</footer>

<?php if ($wa = setting('store_whatsapp')): ?>
    <a class="uc-whatsapp" href="https://wa.me/<?= e(preg_replace('/\D/', '', $wa)) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a>
<?php endif; ?>

<script>window.UC = <?= json_encode([
    'base' => rtrim(url(), '/') . '/',
    'csrf' => csrf_token(),
    'currency' => setting('currency_symbol', 'Rs.'),
    'placeholder' => url('assets/images/placeholder.svg'),
    'animations' => setting_bool('theme_animations', true),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/store.js')) ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
