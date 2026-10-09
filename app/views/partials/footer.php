<?php
$footerPages = [];
foreach (db_all("SELECT title, slug, footer_group FROM pages WHERE is_published = 1 AND footer_group <> 'none' ORDER BY sort_order, title") as $pg) {
    $footerPages[$pg['footer_group']][] = $pg;
}
$socials = [
    'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok',
    'youtube' => 'YouTube', 'pinterest' => 'Pinterest', 'x' => 'X',
];
$icons = ['instagram' => 'instagram', 'facebook' => 'facebook', 'tiktok' => 'tiktok', 'youtube' => 'youtube', 'pinterest' => 'pinterest', 'x' => 'twitter-x'];
$methods = checkout_payment_methods();
$whatsapp = preg_replace('/[^0-9]/', '', (string) setting('whatsapp_number', ''));
$footerLayout = theme('theme_footer_layout');
?>
</main>

<footer class="site-footer site-footer--<?= e($footerLayout) ?>">
  <div class="container container--wide">
    <div class="site-footer__top">
      <div class="site-footer__brand">
        <a class="brand brand--footer" href="<?= e(path_url('/')) ?>">
          <span class="brand__word"><?= e(strtoupper(setting('brand_name', 'Beglet'))) ?></span>
          <span class="brand__tag"><?= e(setting('site_tagline', 'Crafted Leather')) ?></span>
        </a>
        <p class="site-footer__about"><?= e(setting('footer_about', '')) ?></p>
        <div class="social">
          <?php foreach ($socials as $key => $label): $u = setting('social_' . $key); if (!$u) continue; ?>
            <a href="<?= e(safe_link($u)) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><i class="bi bi-<?= e($icons[$key]) ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="site-footer__col">
        <h3>Shop</h3>
        <ul>
          <li><a href="<?= e(path_url('shop')) ?>">All products</a></li>
          <?php foreach (array_slice(category_tree(), 0, 6) as $c): ?><li><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <div class="site-footer__col">
        <h3>Customer Care</h3>
        <ul>
          <li><a href="<?= e(path_url('contact')) ?>">Contact us</a></li>
          <li><a href="<?= e(path_url('track-order')) ?>">Track your order</a></li>
          <li><a href="<?= e(path_url('account')) ?>">My account</a></li>
          <?php foreach ($footerPages['service'] ?? [] as $pg): ?><li><a href="<?= e(path_url($pg['slug'])) ?>"><?= e($pg['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <div class="site-footer__col">
        <h3>Company</h3>
        <ul>
          <?php foreach ($footerPages['company'] ?? [] as $pg): ?><li><a href="<?= e(path_url($pg['slug'])) ?>"><?= e($pg['title']) ?></a></li><?php endforeach; ?>
          <?php foreach ($footerPages['policy'] ?? [] as $pg): ?><li><a href="<?= e(path_url($pg['slug'])) ?>"><?= e($pg['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>

      <div class="site-footer__col site-footer__contact">
        <h3>Get in touch</h3>
        <ul>
          <?php if (setting('contact_phone')): ?><li><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
          <?php if ($whatsapp): ?><li><i class="bi bi-whatsapp"></i> <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp us</a></li><?php endif; ?>
          <?php if (setting('support_email')): ?><li><i class="bi bi-envelope"></i> <a href="mailto:<?= e(setting('support_email')) ?>"><?= e(setting('support_email')) ?></a></li><?php endif; ?>
          <?php if (setting('business_address')): ?><li><i class="bi bi-geo-alt"></i> <span><?= nl2br(e(setting('business_address'))) ?></span></li><?php endif; ?>
          <?php if (setting('business_hours')): ?><li><i class="bi bi-clock"></i> <span><?= e(setting('business_hours')) ?></span></li><?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="site-footer__bottom">
      <p class="site-footer__copy">&copy; <?= date('Y') ?> <?= e(setting('copyright_text', setting('site_name', 'Beglet') . '. All rights reserved.')) ?></p>
      <?php if ($methods): ?>
        <ul class="pay-badges" aria-label="Payment methods we accept">
          <?php foreach ($methods as $m): ?><li><?= e($m['name']) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php if ($whatsapp && setting_bool('whatsapp_float', true)): ?>
<a class="whatsapp-float" href="https://wa.me/<?= e($whatsapp) ?>?text=<?= rawurlencode(setting('whatsapp_message', 'Hello Beglet, I have a question.')) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"><i class="bi bi-whatsapp"></i></a>
<?php endif; ?>

<!-- Quick view -->
<div class="modal fade quickview" id="quickView" tabindex="-1" aria-label="Quick view" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close quickview__close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body p-0" data-quickview-body></div>
    </div>
  </div>
</div>

<script>
window.BEGLET = <?= json_encode([
    'base' => base_path(),
    'csrf' => csrf_token(),
    'currency' => setting('currency_symbol', 'Rs.'),
    'currencyPos' => setting('currency_position', 'before'),
    'decimals' => (int) setting('currency_decimals', '0'),
    'animations' => setting_bool('theme_animations', true),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
</script>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($GLOBALS['page_scripts'] ?? [] as $s): ?><script src="<?= e(asset($s)) ?>"></script><?php endforeach; ?>
</body>
</html>
