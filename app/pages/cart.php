<?php
meta_set(['title' => 'Your Bag', 'noindex' => true]);
header('Cache-Control: no-store');
$lines = cart_lines();
$GLOBALS['body_class'] = 'page-cart';
partial('header');
?>
<div class="container container--wide page-pad">
  <h1 class="page-title">Your Bag</h1>
  <div class="cart-page" data-cart-page><?php partial('cart-contents', ['lines' => $lines, 'mode' => 'page']); ?></div>
  <?php if ($lines): $cross = []; foreach ($lines as $l) { if ($l['product']) { $cross = array_merge($cross, product_relations((int) $l['product_id'], 'cross_sell', 4)); } }
    $inCart = array_column($lines, 'product_id'); $cross = array_slice(array_values(array_filter(array_column($cross, null, 'id'), fn($c) => !in_array((int) $c['id'], $inCart, true))), 0, 4);
    if ($cross): ?>
    <section class="pdp__rail">
      <div class="section-head"><div><p class="eyebrow">Complete the set</p><h2 class="section-title">You might also like</h2></div></div>
      <div class="product-grid product-grid--4"><?php foreach ($cross as $cp) { partial('product-card', ['p' => $cp]); } ?></div>
    </section>
  <?php endif; endif; ?>
</div>
<?php partial('footer');
