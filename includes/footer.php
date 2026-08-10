<?php
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}
$footerCities = db_select($conn, 'SELECT name, slug FROM cities WHERE is_active = 1 ORDER BY sort_order LIMIT 6');
$footerCategories = db_select($conn, 'SELECT name, slug FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 6');
?>
</main>

<footer class="footer-w">
  <div class="container-xl">
    <div class="footer-grid">
      <div class="footer-col">
        <a href="/index.php" class="brand"><span class="brand-mark"><i class="bi bi-compass"></i></span><?php echo e($siteName ?? APP_NAME); ?></a>
        <p style="margin-top:16px;max-width:280px;"><?php echo e(get_setting($conn, 'site_tagline', '')); ?></p>
        <div class="footer-social">
          <a href="<?php echo e(get_setting($conn, 'facebook_url', '#')); ?>"><i class="bi bi-facebook"></i></a>
          <a href="<?php echo e(get_setting($conn, 'instagram_url', '#')); ?>"><i class="bi bi-instagram"></i></a>
          <a href="<?php echo e(get_setting($conn, 'twitter_url', '#')); ?>"><i class="bi bi-twitter-x"></i></a>
        </div>
      </div>
      <div class="footer-col">
        <h6>Cities</h6>
        <?php foreach ($footerCities as $c): ?>
          <a href="/pages/city.php?slug=<?php echo e($c['slug']); ?>"><?php echo e($c['name']); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="footer-col">
        <h6>Categories</h6>
        <?php foreach ($footerCategories as $cat): ?>
          <a href="/pages/category.php?slug=<?php echo e($cat['slug']); ?>"><?php echo e($cat['name']); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="footer-col">
        <h6>Company</h6>
        <a href="/pages/page.php?slug=about">About Us</a>
        <a href="/pages/page.php?slug=terms">Terms of Service</a>
        <a href="/pages/page.php?slug=privacy">Privacy Policy</a>
        <a href="/pages/page.php?slug=faq">FAQ</a>
      </div>
      <div class="footer-col">
        <h6>Stay in the loop</h6>
        <p>Get trip ideas and deals in your inbox.</p>
        <form class="newsletter-box" method="post" action="/ajax/newsletter.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="redirect" value="<?php echo e($_SERVER['REQUEST_URI'] ?? '/index.php'); ?>">
          <input type="email" name="email" placeholder="you@email.com" required>
          <button type="submit" class="btn-w btn-primary btn-sm"><i class="bi bi-send"></i></button>
        </form>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?php echo date('Y'); ?> <?php echo e($siteName ?? APP_NAME); ?>. All rights reserved.</span>
      <span><?php echo e(get_setting($conn, 'contact_email', '')); ?></span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
