<?php
$__socials = mysqli_query($mysqli, "SELECT * FROM social_links WHERE status = 'active' ORDER BY sort_order");
$__footerCats = mysqli_query($mysqli, "SELECT * FROM categories WHERE parent_id IS NULL AND status='active' ORDER BY sort_order LIMIT 6");
?>
<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <h3 class="footer-brand"><?= e(get_setting('store_name', 'Fashion Store')) ?></h3>
        <p class="footer-text"><?= e(get_setting('store_tagline')) ?></p>
        <p class="footer-text small"><?= e(get_setting('store_address')) ?><br><?= e(get_setting('store_phone')) ?> &middot; <?= e(get_setting('store_email')) ?></p>
        <div class="footer-social">
          <?php while ($s = mysqli_fetch_assoc($__socials)): ?>
            <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"><i class="bi <?= e($s['icon_class']) ?>"></i></a>
          <?php endwhile; ?>
        </div>
      </div>
      <div class="col-lg-2 col-6">
        <h4 class="footer-heading">Shop</h4>
        <ul class="footer-links">
          <?php while ($c = mysqli_fetch_assoc($__footerCats)): ?>
            <li><a href="<?= BASE_URL ?>/shop.php?category=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></li>
          <?php endwhile; ?>
        </ul>
      </div>
      <div class="col-lg-2 col-6">
        <h4 class="footer-heading">Customer Service</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/page.php?slug=faq">FAQ</a></li>
          <li><a href="<?= BASE_URL ?>/page.php?slug=shipping-policy">Shipping Policy</a></li>
          <li><a href="<?= BASE_URL ?>/page.php?slug=return-policy">Return Policy</a></li>
          <li><a href="<?= BASE_URL ?>/page.php?slug=size-guide">Size Guide</a></li>
          <li><a href="<?= BASE_URL ?>/page.php?slug=terms-conditions">Terms &amp; Conditions</a></li>
          <li><a href="<?= BASE_URL ?>/page.php?slug=privacy-policy">Privacy Policy</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-6">
        <h4 class="footer-heading">Company</h4>
        <ul class="footer-links">
          <li><a href="<?= BASE_URL ?>/page.php?slug=about-us">About Us</a></li>
          <li><a href="<?= BASE_URL ?>/blog.php">Journal</a></li>
          <li><a href="<?= BASE_URL ?>/contact.php">Contact Us</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-6">
        <h4 class="footer-heading">Stay Updated</h4>
        <form id="newsletterForm" class="newsletter-form">
          <input type="email" name="email" placeholder="Your email" required>
          <button type="submit"><i class="bi bi-send"></i></button>
        </form>
        <div id="newsletterMsg" class="small mt-2"></div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="payment-icons">
        <i class="bi bi-cash-coin" title="Cash on Delivery"></i>
        <i class="bi bi-credit-card" title="Card"></i>
        <span>EasyPaisa</span><span>JazzCash</span>
      </div>
      <p class="mb-0">&copy; <?= date('Y') ?> <?= e(get_setting('store_name')) ?>. All rights reserved.</p>
    </div>
  </div>
</footer>

<a href="<?= BASE_URL ?>/cart.php" class="mobile-cart-fab d-lg-none"><i class="bi bi-bag"></i> <span class="cart-count"><?= (int)cart_count() ?></span></a>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>const BASE_URL = "<?= BASE_URL ?>";</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
