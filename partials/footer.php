<?php if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$footerCats = category_children(null, true, true);
$footerPages = db_all('SELECT title, slug FROM pages WHERE is_active = 1 AND show_in_footer = 1 ORDER BY sort_order, title');
?>
</main>

<section class="trust-strip">
  <div class="container">
    <div class="row g-3 text-center text-md-start">
      <div class="col-6 col-md-3 d-md-flex align-items-center gap-3"><i class="bi bi-truck"></i><div><strong>Home Delivery</strong><small>Fast delivery across the city</small></div></div>
      <div class="col-6 col-md-3 d-md-flex align-items-center gap-3"><i class="bi bi-shop"></i><div><strong>Store Pickup</strong><small>Packed &amp; ready on time</small></div></div>
      <div class="col-6 col-md-3 d-md-flex align-items-center gap-3"><i class="bi bi-cash-coin"></i><div><strong>Cash on Delivery</strong><small>Easypaisa, JazzCash, Bank</small></div></div>
      <div class="col-6 col-md-3 d-md-flex align-items-center gap-3"><i class="bi bi-patch-check"></i><div><strong>100% Original</strong><small>Trusted brands only</small></div></div>
    </div>
  </div>
</section>

<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <img src="<?= url('assets/images/logo-white.svg') ?>" alt="Fahim Store" width="220" height="53" loading="lazy" class="mb-3">
        <p class="small opacity-75"><?= e(setting('site_tagline')) ?>. <?= e(setting('meta_description')) ?></p>
        <ul class="list-unstyled small footer-contact">
          <li><i class="bi bi-geo-alt"></i><?= e(setting('store_address')) ?>, <?= e(setting('store_city')) ?></li>
          <li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li>
          <?php if ($wa = setting('store_whatsapp')): ?><li><i class="bi bi-whatsapp"></i><a href="<?= e(whatsapp_link($wa)) ?>" target="_blank" rel="noopener">WhatsApp us</a></li><?php endif; ?>
          <li><i class="bi bi-envelope"></i><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li>
          <li><i class="bi bi-clock"></i><?= e(setting('opening_hours')) ?></li>
        </ul>
        <div class="social">
          <?php foreach (['facebook_url' => 'facebook', 'instagram_url' => 'instagram', 'youtube_url' => 'youtube', 'tiktok_url' => 'tiktok'] as $k => $icon): if ($u = setting($k)): ?>
          <a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= ucfirst($icon) ?>"><i class="bi bi-<?= $icon ?>"></i></a>
          <?php endif; endforeach; ?>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="footer-title">Shop</h2>
        <ul class="list-unstyled footer-links">
          <?php foreach ($footerCats as $c): ?><li><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
          <li><a href="<?= url('monthly-rashan/') ?>">Monthly Rashan</a></li>
          <li><a href="<?= url('offers/') ?>">Offers</a></li>
          <li><a href="<?= url('brands/') ?>">Brands</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="footer-title">Help</h2>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= url('my-account/') ?>">My Account</a></li>
          <li><a href="<?= url('track-order/') ?>">Track Order</a></li>
          <li><a href="<?= url('contact/') ?>">Contact Us</a></li>
          <?php foreach ($footerPages as $p): ?><li><a href="<?= url($p['slug'] . '/') ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="col-lg-4">
        <h2 class="footer-title">Get the best deals</h2>
        <p class="small opacity-75">Subscribe for weekly grocery deals and school offers.</p>
        <form class="js-newsletter d-flex gap-2" novalidate>
          <input type="email" name="email" class="form-control" placeholder="Your email address" required aria-label="Email address">
          <button class="btn btn-warning fw-semibold" type="submit">Subscribe</button>
        </form>
        <div class="small mt-2 js-newsletter-msg"></div>
        <div class="pay-badges mt-4">
          <span>Cash on Delivery</span><span>Easypaisa</span><span>JazzCash</span><span>Bank Transfer</span>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'Fahim Store')) ?>. All rights reserved.</span>
      <a href="<?= url('sitemap.xml') ?>">Sitemap</a>
    </div>
  </div>
</footer>

<!-- Mobile bottom navigation -->
<nav class="bottom-nav d-lg-none" aria-label="Quick links">
  <a href="<?= url('') ?>"><i class="bi bi-house"></i><span>Home</span></a>
  <a href="#" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"><i class="bi bi-grid"></i><span>Categories</span></a>
  <a href="<?= url('monthly-rashan/') ?>" class="center"><i class="bi bi-basket2-fill"></i><span>Rashan</span></a>
  <a href="<?= url('cart/') ?>"><i class="bi bi-cart3"></i><span>Cart</span><b class="js-cart-count<?= cart_count() ? '' : ' d-none' ?>"><?= cart_count() ?></b></a>
  <a href="<?= url(customer_id() ? 'my-account/' : 'login/') ?>"><i class="bi bi-person"></i><span>Account</span></a>
</nav>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastBox"></div>
<button type="button" class="back-to-top" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (page_get('scripts', []) as $s): ?><script src="<?= asset($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
