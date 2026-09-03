</main>
<?php if (!isset($hideFooter)): ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <h3 class="footer-logo"><?= clean(site_name()) ?></h3>
      <p><?= clean(get_setting('footer_text')) ?></p>
    </div>
    <div>
      <h4>Shop</h4>
      <ul>
        <li><a href="<?= base_url('search.php') ?>">All Products</a></li>
        <li><a href="<?= base_url() ?>">Categories</a></li>
        <li><a href="<?= base_url('wishlist.php') ?>">Wishlist</a></li>
      </ul>
    </div>
    <div>
      <h4>Sell</h4>
      <ul>
        <li><a href="<?= shop_url('register.php') ?>">Become a Vendor</a></li>
        <li><a href="<?= shop_url('login.php') ?>">Vendor Login</a></li>
      </ul>
    </div>
    <div>
      <h4>Contact</h4>
      <ul>
        <li><?= clean(get_setting('site_address')) ?></li>
        <li><?= clean(get_setting('site_phone')) ?></li>
        <li><?= clean(get_setting('site_email')) ?></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom container"><?= get_setting('copyright_text') ?></div>
</footer>
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>window.BEGLET_BASE_URL = "<?= base_url() ?>";</script>
<script src="<?= asset_url('js/main.js') ?>"></script>
</body>
</html>
