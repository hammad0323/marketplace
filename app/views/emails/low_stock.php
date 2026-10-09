<h2 style="font-family:Georgia,serif;font-weight:normal;color:#101D35;margin:0 0 12px;">Low stock alert</h2>
<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
<tr style="background:#F7F5F0;"><th align="left">Product</th><th align="left">SKU</th><th align="right">On hand</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?><?= $r['label'] ? ' — ' . e($r['label']) : '' ?></td><td><?= e($r['sku']) ?></td><td align="right"><?= (int) $r['quantity'] ?></td></tr><?php endforeach; ?>
</table>
<p><a href="<?= e(url('admin/inventory', ['filter' => 'low'])) ?>">Open inventory</a></p>
