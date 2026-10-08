</main>
<?php $siteName = setting('site_name', 'Eashal Mirha'); ?>
<footer class="site-footer">
  <div class="footer-ornament"><span></span><i>✦</i><span></span></div>
  <div class="container footer-grid">
    <div class="footer-brand" data-reveal>
      <?php if (setting('logo')): ?>
        <img class="footer-logo" src="<?= e(img(setting('logo'))) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <span class="logo__text"><?= e($siteName) ?></span>
      <?php endif; ?>
      <p><?= e(setting('footer_about')) ?></p>
      <div class="socials">
        <?php foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'pinterest'] as $s): if (setting($s)): ?>
          <a href="<?= e(setting($s)) ?>" target="_blank" rel="noopener" aria-label="<?= ucfirst($s) ?>"><?= icon($s, 18) ?></a>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div data-reveal data-delay="1">
      <h4>Shop</h4>
      <ul>
        <?php foreach (array_slice(category_tree(), 0, 6) as $cat): ?><li><a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div data-reveal data-delay="2">
      <h4>Customer Care</h4>
      <ul>
        <li><a href="<?= url('track-order') ?>">Track Your Order</a></li>
        <?php foreach (footer_pages() as $p): ?><li><a href="<?= url('page/' . $p['slug']) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= url('contact') ?>">Contact Us</a></li>
      </ul>
    </div>
    <div data-reveal data-delay="3">
      <h4>Get in Touch</h4>
      <ul class="contact-list">
        <?php if (setting('address')): ?><li><?= icon('pin', 16) ?> <?= e(setting('address')) ?></li><?php endif; ?>
        <?php if (setting('phone')): ?><li><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
        <?php if (setting('email')): ?><li><?= icon('mail', 16) ?> <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        <?php if (setting('business_hours')): ?><li><?= icon('clock', 16) ?> <?= e(setting('business_hours')) ?></li><?php endif; ?>
      </ul>
      <div class="pay-icons">
        <?php foreach (payment_methods() as $m): ?><span><?= e($m['title']) ?></span><?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container"><?= e(str_replace('{year}', date('Y'), setting('copyright', '© {year} ' . $siteName))) ?></div>
  </div>
</footer>

<?php if (setting('whatsapp')): ?>
<a class="whatsapp-float" href="https://wa.me/<?= e(preg_replace('~\D~', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><?= icon('whatsapp', 28) ?></a>
<?php endif; ?>
<button class="to-top" id="toTop" aria-label="Back to top"><?= icon('left', 20) ?></button>

<script src="<?= asset('vendor/swiper/swiper-bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
