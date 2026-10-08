<?php
/**
 * AJAX + form endpoint for cart, coupon, wishlist and live search.
 */
require __DIR__ . '/includes/bootstrap.php';

$action = $_REQUEST['action'] ?? '';

function mini_cart_html(): string
{
    $t = cart_totals();
    ob_start();
    if (!$t['items']): ?>
      <div class="mini-empty">
        <?= icon('bag', 42) ?>
        <p>Your bag is empty.</p>
        <a class="btn btn-dark" href="<?= url('shop?filter=new') ?>">Discover New Arrivals</a>
      </div>
    <?php else: ?>
      <div class="mini-items">
        <?php foreach ($t['items'] as $it): ?>
          <div class="mini-item">
            <a href="<?= product_url($it['product']) ?>"><img src="<?= e(img($it['product']['image'])) ?>" alt=""></a>
            <div>
              <a class="mini-item__name" href="<?= product_url($it['product']) ?>"><?= e($it['product']['name']) ?></a>
              <small><?= e(trim($it['size'] . ($it['color'] ? ' · ' . $it['color'] : ''), ' ·')) ?></small>
              <div class="mini-item__row">
                <div class="qty sm"><button type="button" data-cart-qty="<?= e($it['key']) ?>" data-v="<?= $it['qty'] - 1 ?>">−</button><span><?= $it['qty'] ?></span><button type="button" data-cart-qty="<?= e($it['key']) ?>" data-v="<?= $it['qty'] + 1 ?>">+</button></div>
                <strong><?= money($it['total']) ?></strong>
              </div>
            </div>
            <button class="mini-item__remove" data-cart-qty="<?= e($it['key']) ?>" data-v="0" aria-label="Remove"><?= icon('close', 14) ?></button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mini-foot">
        <?php $free = (float)setting('free_shipping_min', 0); if ($free > 0): $left = $free - $t['subtotal']; ?>
          <div class="free-bar"><p><?= $left > 0 ? 'Add <b>' . money($left) . '</b> more for free delivery' : '✦ You have unlocked free delivery' ?></p><span style="--w:<?= min(100, round($t['subtotal'] / $free * 100)) ?>%"></span></div>
        <?php endif; ?>
        <div class="mini-total"><span>Subtotal</span><strong><?= money($t['subtotal']) ?></strong></div>
        <a class="btn btn-gold btn-block" href="<?= url('checkout') ?>">Checkout</a>
        <a class="btn btn-outline btn-block" href="<?= url('cart') ?>">View Bag</a>
      </div>
    <?php endif;
    return ob_get_clean();
}

function respond(bool $ok, string $msg, array $extra = []): void
{
    if (is_ajax()) {
        json_out(array_merge(['ok' => $ok, 'message' => $msg, 'count' => cart_count()], $extra));
    }
    flash($ok ? 'success' : 'error', $msg);
    back('cart');
}

switch ($action) {
    case 'mini':
        json_out(['ok' => true, 'html' => mini_cart_html(), 'count' => cart_count()]);

    case 'search':
        $term = mb_substr(get('q'), 0, 60);
        $items = [];
        if (mb_strlen($term) >= 2) {
            foreach (product_list('(p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ?)', ["%$term%", "%$term%", "%$term%"], 'p.sales_count DESC', 6) as $p) {
                $items[] = ['name' => $p['name'], 'url' => product_url($p), 'img' => img($p['image']), 'price' => money(price_now($p))];
            }
        }
        json_out(['ok' => true, 'items' => $items]);

    case 'add':
        if (!is_post()) redirect('cart');
        require_csrf();
        [$ok, $msg] = cart_add((int)post('id'), max(1, (int)post('qty', 1)), (string)post('size'), (string)post('color'));
        if ($ok && post('buy_now') === '1') {
            if (is_ajax()) json_out(['ok' => true, 'message' => $msg, 'redirect' => url('checkout'), 'count' => cart_count()]);
            redirect('checkout');
        }
        respond($ok, $msg, ['html' => mini_cart_html()]);

    case 'update':
        if (!is_post()) redirect('cart');
        require_csrf();
        if (post('remove') !== '') {
            cart_set((string)post('remove'), 0);
            respond(true, 'Item removed from your bag.', ['html' => mini_cart_html()]);
        }
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $key => $qty) cart_set((string)$key, (int)$qty);
        } else {
            cart_set((string)post('key'), (int)post('qty'));
        }
        respond(true, 'Your bag has been updated.', ['html' => mini_cart_html()]);

    case 'coupon':
        if (!is_post()) redirect('cart');
        require_csrf();
        if (post('remove') === '1') {
            unset($_SESSION['coupon']);
            respond(true, 'Coupon removed.');
        }
        $t = cart_totals();
        [$ok, $d, $msg] = coupon_check((string)post('code'), $t['subtotal']);
        if ($ok) $_SESSION['coupon'] = strtoupper(post('code'));
        respond($ok, $ok ? 'Coupon applied — you saved ' . money($d) . '.' : $msg);

    case 'wishlist':
        if (!is_post()) redirect('wishlist');
        require_csrf();
        $pid = (int)post('id');
        if (!val('SELECT COUNT(*) FROM products WHERE id = ? AND status = 1', [$pid])) respond(false, 'Product not found.');
        $added = wishlist_toggle($pid);
        if (is_ajax()) json_out(['ok' => true, 'added' => $added, 'wish' => count(wishlist_ids()), 'message' => $added ? 'Saved to your wishlist.' : 'Removed from wishlist.']);
        back('wishlist');

    case 'shipping':
        $t = cart_totals((string)get('city'), (string)get('payment', 'cod'));
        json_out(['ok' => true, 'shipping' => $t['shipping'] > 0 ? money($t['shipping']) : 'Free', 'fee' => money($t['fee']), 'has_fee' => $t['fee'] > 0, 'total' => money($t['total'])]);
}

redirect('cart');
