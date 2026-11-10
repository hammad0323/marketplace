<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
$total = (int) db_val("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status <> 'draft'", [$c['id']]);
$pg = paginate($total, 15);
$recent = db_all("SELECT * FROM orders WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 15 OFFSET " . $pg['offset'], [$c['id']]);
$active = 'orders'; $title = 'My Orders';
require FS_ROOT . '/partials/account-start.php';
?>
<div class="box"><?php require FS_ROOT . '/partials/account-orders-table.php'; ?><div class="mt-3"><?= render_pagination($pg) ?></div></div>
<?php require FS_ROOT . '/partials/account-end.php';
