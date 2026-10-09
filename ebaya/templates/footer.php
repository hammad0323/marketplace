<?php
/** Storefront footer, quick-view modal, scripts. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$menus = [];
foreach (db_all("SELECT * FROM navigation_items WHERE menu <> 'header' AND is_visible = 1 AND parent_id IS NULL ORDER BY sort_order, id") as $n) {
    $menus[$n['menu']][] = $n;
}
$menuTitles = ['footer_shop' => 'Shop', 'footer_help' => 'Customer Care', 'footer_about' => 'Ebaya'];
$socials = array_filter([
    'instagram' => setting('social_instagram'), 'facebook' => setting('social_facebook'), 'tiktok' => setting('social_tiktok'),
    'pinterest' => setting('social_pinterest'), 'youtube' => setting('social_youtube'),
]);
$centered = setting('footer_layout') === 'centered';
$copy = str_replace('{year}', date('Y'), (string)setting('copyright_text', '© {year} Ebaya'));
?>
</main>

<footer class="site-footer<?= $centered ? ' footer-centered' : '' ?>">
  <div class="container-eb">
    <div class="row g-5">
      <div class="<?= $centered ? 'col-12 text-center' : 'col-lg-4' ?>">
        <a href="<?= e(url()) ?>" class="footer-brand">
          <?php if ($l = setting('logo_light')): ?><img src="<?= e(img_url($l)) ?>" alt="<?= e(setting('site_name')) ?>" style="height:<?= (int)setting('logo_height', 42) ?>px"><?php else: ?><span class="brand-word"><?= e(setting('site_name', 'Ebaya')) ?></span><?php endif; ?>
        </a>
        <p class="footer-tagline"><?= e(setting('tagline')) ?></p>
        <ul class="footer-contact">
          <?php if ($v = setting('contact_email')): ?><li><i class="bi bi-envelope"></i> <a href="mailto:<?= e($v) ?>"><?= e($v) ?></a></li><?php endif; ?>
          <?php if ($v = setting('contact_phone')): ?><li><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v)) ?>"><?= e($v) ?></a></li><?php endif; ?>
          <?php if ($v = setting('whatsapp_number')): ?><li><i class="bi bi-whatsapp"></i> <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $v)) ?>" target="_blank" rel="noopener">WhatsApp us</a></li><?php endif; ?>
          <?php if ($v = setting('business_hours')): ?><li><i class="bi bi-clock"></i> <?= e($v) ?></li><?php endif; ?>
          <?php if ($v = setting('address')): ?><li><i class="bi bi-geo-alt"></i> <?= nl2br(e($v)) ?></li><?php endif; ?>
        </ul>
        <?php if ($socials): ?>
        <div class="footer-social">
          <?php foreach ($socials as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><i class="bi bi-<?= e($k) ?>"></i></a><?php endforeach; ?>
        </div>
        <?php if ($h = setting('instagram_handle')): ?><p class="footer-handle"><?= e($h) ?></p><?php endif; ?>
        <?php endif; ?>
      </div>
      <?php foreach ($menuTitles as $key => $title): if (empty($menus[$key])) continue; ?>
        <div class="<?= $centered ? 'col-md-4 text-center' : 'col-6 col-md-4 col-lg-2' ?><?= $key === 'footer_help' && !$centered ? ' col-lg-3' : '' ?>">
          <h3 class="footer-title"><?= e($title) ?></h3>
          <ul class="footer-links">
            <?php foreach ($menus[$key] as $n): ?><li><a href="<?= e(url($n['url'])) ?>"<?= $n['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($n['label']) ?></a></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="footer-bottom">
      <span><?= e($copy) ?></span>
      <span class="footer-pay">
        <?php foreach (payment_methods_for_checkout(null, 0) + (payment_gateway_available('cod') ? ['cod' => ['name' => 'Cash on Delivery']] : []) as $pm): ?>
          <span class="pay-chip"><?= e($pm['name']) ?></span>
        <?php endforeach; ?>
      </span>
    </div>
  </div>
</footer>

<!-- Quick view -->
<div class="modal fade" id="quickView" tabindex="-1" aria-label="Quick view" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close qv-close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body p-0" id="quickViewBody"></div>
    </div>
  </div>
</div>

<script>
window.EB = {
  base: <?= json_encode(BASE_PATH) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  currency: <?= json_encode(currency_symbol()) ?>,
  reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches
};
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11.1.14/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($pageScripts)) echo $pageScripts; ?>
</body>
</html>
