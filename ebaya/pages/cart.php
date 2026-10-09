<?php
if (!defined('EBAYA')) { http_response_code(403); exit; }
$lines = cart_lines();
$totals = checkout_totals($lines, ['coupon' => cart_coupon(), 'email' => current_customer()['email'] ?? '', 'customer_id' => customer_id()]);
seo_set(['title' => 'Your Bag', 'noindex' => true]);
$bodyClass = 'page-cart';
require ROOT_PATH . '/templates/header.php';
?>
<section class="page-section">
  <div class="container-eb">
    <h1 class="page-title text-center">Your Bag</h1>
    <div class="cart-page" id="cartPage"><?php include ROOT_PATH . '/templates/cart-contents.php'; ?></div>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
