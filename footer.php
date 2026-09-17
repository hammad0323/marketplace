<?php
$businessId = wh_current_business_id();
$settings = wh_get_settings($businessId);
?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4 style="font-family:var(--font-head);font-size:1.2rem;color:#fff;"><?= e($settings['site_name'] ?? 'Wedding Hall') ?></h4>
        <p style="max-width:320px;"><?= e($settings['footer_text'] ?? '') ?></p>
        <div class="social-row">
          <?php if (!empty($settings['facebook'])): ?><a href="<?= e($settings['facebook']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
          <?php if (!empty($settings['instagram'])): ?><a href="<?= e($settings['instagram']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i></a><?php endif; ?>
          <?php if (!empty($settings['youtube'])): ?><a href="<?= e($settings['youtube']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-youtube"></i></a><?php endif; ?>
          <?php if (!empty($settings['tiktok'])): ?><a href="<?= e($settings['tiktok']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-tiktok"></i></a><?php endif; ?>
        </div>
      </div>
      <div>
        <h4>Explore</h4>
        <a href="<?= e(BASE_URL) ?>/halls">Our Halls</a>
        <a href="<?= e(BASE_URL) ?>/gallery">Gallery</a>
        <a href="<?= e(BASE_URL) ?>/availability">Availability</a>
        <a href="<?= e(BASE_URL) ?>/booking">Book Online</a>
        <a href="<?= e(BASE_URL) ?>/blog">Blog</a>
      </div>
      <div>
        <h4>Company</h4>
        <a href="<?= e(BASE_URL) ?>/about">About Us</a>
        <a href="<?= e(BASE_URL) ?>/faq">FAQ</a>
        <a href="<?= e(BASE_URL) ?>/privacy">Privacy Policy</a>
        <a href="<?= e(BASE_URL) ?>/terms">Terms &amp; Conditions</a>
        <a href="<?= e(BASE_URL) ?>/contact">Contact</a>
      </div>
      <div>
        <h4>Contact</h4>
        <?php if (!empty($settings['phone'])): ?><a href="tel:<?= e($settings['phone']) ?>"><i class="fa-solid fa-phone"></i> <?= e($settings['phone']) ?></a><?php endif; ?>
        <?php if (!empty($settings['whatsapp'])): ?><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $settings['whatsapp'])) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
        <?php if (!empty($settings['email'])): ?><a href="mailto:<?= e($settings['email']) ?>"><i class="fa-solid fa-envelope"></i> <?= e($settings['email']) ?></a><?php endif; ?>
        <?php if (!empty($settings['address'])): ?><a href="<?= e($settings['google_maps_url'] ?? '#') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot"></i> <?= e($settings['address']) ?></a><?php endif; ?>
      </div>
    </div>
    <div class="footer-bottom">
      <span><?= e($settings['copyright'] ?? ('© ' . date('Y') . ' ' . ($settings['site_name'] ?? ''))) ?></span>
      <span>Built for Pakistan's wedding &amp; event industry.</span>
    </div>
  </div>
</footer>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<script src="<?= e(BASE_URL) ?>/assets/js/main.js"></script>
</body>
</html>
