<?php
if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}
$footerCategories = get_categories(true);
$popularForFooter = get_popular_tools(6);
?>
<footer class="tp-footer">
  <div class="tp-container">
    <div class="row g-4">
      <div class="col-12 col-lg-4 mb-2">
        <a href="<?= tp_url() ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-2">
          <span class="brand-mark" style="width:30px;height:30px;font-size:.9rem;"><i class="bi bi-grid-1x2-fill"></i></span>
          <strong style="color:var(--tp-text);font-family:'Plus Jakarta Sans',sans-serif;"><?= e(tp_setting('site_name')) ?></strong>
        </a>
        <p class="small mb-0" style="max-width:320px;"><?= e(tp_setting('site_tagline')) ?></p>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Categories</h6>
        <?php foreach (array_slice($footerCategories, 0, 8) as $cat): ?>
          <a href="<?= tp_url($cat['slug']) ?>" class="d-block"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Popular Tools</h6>
        <?php foreach ($popularForFooter as $t): ?>
          <a href="<?= tp_url($t['slug']) ?>" class="d-block"><?= e($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Company</h6>
        <a href="<?= tp_url('about') ?>" class="d-block">About Us</a>
        <a href="<?= tp_url('contact') ?>" class="d-block">Contact</a>
        <a href="<?= tp_url('blog') ?>" class="d-block">Blog</a>
        <a href="<?= tp_url('all-tools') ?>" class="d-block">All Tools</a>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Legal</h6>
        <a href="<?= tp_url('privacy-policy') ?>" class="d-block">Privacy Policy</a>
        <a href="<?= tp_url('terms') ?>" class="d-block">Terms of Service</a>
        <a href="<?= tp_url('disclaimer') ?>" class="d-block">Disclaimer</a>
        <a href="<?= tp_url('cookie-policy') ?>" class="d-block">Cookie Policy</a>
      </div>
    </div>
    <div class="tp-sub d-flex flex-wrap justify-content-between gap-2">
      <span><?= e(tp_setting('footer_copyright')) ?></span>
      <span>Fast • Free • No Registration Required</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= tp_asset('js/tp-calculator.js') ?>"></script>
<script src="<?= tp_asset('js/main.js') ?>"></script>
<?php if ($fs = tp_setting('footer_scripts')): ?><?= $fs /* admin-controlled, trusted input */ ?><?php endif; ?>
</body>
</html>
