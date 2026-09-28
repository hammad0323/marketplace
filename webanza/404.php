<?php
if (!defined('ROOT_PATH')) {
    require __DIR__ . '/config.php';
}
require_once __DIR__ . '/partials/sections.php';
http_response_code(404);
$page_title = 'Page not found';
require __DIR__ . '/partials/header.php';
page_hero('Oops! Page *not found*', 'The page you are looking for may have been moved or no longer exists.', ['404' => ''], '404');
?>
<section class="section center">
  <div class="container">
    <a href="<?= e(url()) ?>" class="btn magnetic">Back to Home <i class="fa-solid fa-arrow-right"></i></a>
    <a href="<?= e(url('contact.php')) ?>" class="btn btn-outline magnetic">Contact Us</a>
  </div>
</section>
<?php
require __DIR__ . '/partials/footer.php';
