<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
require FS_ROOT . '/partials/product-card.php';
$ids = wishlist_ids();
$items = $ids ? products_query(['ids' => $ids, 'sort' => 'ids', 'limit' => 200])['items'] : [];
$active = 'wishlist'; $title = 'My Wishlist';
require FS_ROOT . '/partials/account-start.php';
?>
<div class="js-wish-page">
<?= $items ? render_product_grid($items, 'row-cols-2 row-cols-md-3 row-cols-xl-4') : '<div class="empty-state"><i class="bi bi-heart"></i><p>Your wishlist is empty.</p><a href="' . url('shop/') . '" class="btn btn-brand">Browse products</a></div>' ?>
</div>
<?php require FS_ROOT . '/partials/account-end.php';
