<?php
if (!defined('ROOT_PATH')) {
    exit;
}
$footer_services = rows('SELECT title, slug FROM services WHERE is_active = 1 ORDER BY sort_order, id LIMIT 8');
$footer_pages    = rows('SELECT title, slug FROM pages WHERE is_active = 1 ORDER BY sort_order, id');
$wa = whatsapp_link('Hi ' . setting('site_name', 'Webanza Tech') . ', I would like to discuss a project.');
?>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-top">
      <div>
        <a href="<?= e(url()) ?>" class="footer-logo"><img src="<?= e(media(setting('logo_light', 'assets/img/logo-light.png'))) ?>" alt="<?= e(setting('site_name')) ?>"></a>
        <p><?= e(setting('footer_about')) ?></p>
        <div class="socials">
          <?php foreach (social_links() as $s): ?>
            <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['name']) ?>"><i class="<?= e($s['icon']) ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h5>Services</h5>
        <ul>
          <?php foreach ($footer_services as $s): ?>
            <li><a href="<?= e(url('service.php?slug=' . $s['slug'])) ?>"><?= e($s['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h5>Company</h5>
        <ul>
          <li><a href="<?= e(url('about.php')) ?>">About Us</a></li>
          <li><a href="<?= e(url('packages.php')) ?>">Packages &amp; Pricing</a></li>
          <li><a href="<?= e(url('portfolio.php')) ?>">Portfolio</a></li>
          <li><a href="<?= e(url('blog.php')) ?>">Blog</a></li>
          <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
          <?php foreach ($footer_pages as $p): ?>
            <li><a href="<?= e(url('page.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h5>Stay in the loop</h5>
        <p>Tips on growing your business online — no spam, ever.</p>
        <form class="newsletter" method="post" action="<?= e(url('subscribe.php')) ?>">
          <?= csrf_field() ?>
          <input type="email" name="email" placeholder="Your email address" required aria-label="Email address">
          <button type="submit" aria-label="Subscribe"><i class="fa-solid fa-arrow-right"></i></button>
        </form>
        <ul class="footer-contact">
          <?php if (setting('email')): ?><li><i class="fa-solid fa-envelope"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
          <?php if (setting('phone')): ?><li><i class="fa-solid fa-phone"></i><a href="tel:<?= e(preg_replace('~[^\d+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
          <?php if (setting('address')): ?><li><i class="fa-solid fa-location-dot"></i><span><?= e(setting('address')) ?></span></li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="footer-big" aria-hidden="true" style="--len:<?= max(6, mb_strlen(setting('site_name', 'Webanza Tech'))) ?>"><?= e(strtoupper(setting('site_name', 'Webanza Tech'))) ?></div>
  <div class="container">
    <div class="footer-bottom">
      <span><?= e(str_replace('{year}', date('Y'), setting('copyright', '© {year} Webanza Tech.'))) ?></span>
      <nav>
        <a href="<?= e(url('packages.php')) ?>">Pricing</a>
        <a href="<?= e(url('contact.php')) ?>">Support</a>
        <a href="<?= e(url('admin/')) ?>" rel="nofollow">Admin</a>
      </nav>
    </div>
  </div>
</footer>

<?php if ($wa): ?>
<a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
<?php endif; ?>
<button class="to-top" id="toTop" type="button" aria-label="Back to top">
  <svg viewBox="0 0 54 54"><circle cx="27" cy="27" r="24"/></svg>
  <i class="fa-solid fa-arrow-up"></i>
</button>

<script src="<?= e(asset('vendor/lenis.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?= setting('footer_code') ?>
</body>
</html>
