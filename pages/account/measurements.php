<?php
$c = require_customer();
seo_set(['title' => 'My Measurements', 'noindex' => true]);
if (is_post()) {
    csrf_check();
    db_exec('DELETE FROM customer_measurements WHERE id = ? AND customer_id = ?', [input_int('id'), (int) $c['id']]);
    flash('success', 'Measurements removed.');
    redirect('account/measurements');
}
$rows = db_all('SELECT * FROM customer_measurements WHERE customer_id = ? ORDER BY COALESCE(updated_at, created_at) DESC', [(int) $c['id']]);
$defs = studio_definitions();
$names = array_column(studio_garments(false), 'name', 'code');
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">My Measurements</h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'measurements']); ?>
        <div class="uc-account-main">
            <p class="text-muted">Measurements you save in the <a href="<?= e(url('custom-size')) ?>">Custom Size Studio</a> appear here and can be reused with one tap.</p>
            <?php foreach ($rows as $r): $data = json_decode($r['data'], true) ?: []; $def = $defs[$r['garment_code']] ?? null; ?>
                <div class="uc-card-box">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div><strong><?= e($r['profile_name']) ?></strong><div class="small text-muted"><?= e($names[$r['garment_code']] ?? $r['garment_code']) ?> · updated <?= e(format_date($r['updated_at'] ?? $r['created_at'])) ?></div></div>
                        <form method="post" data-confirm="Delete these measurements?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="uc-link-btn text-danger">Delete</button></form>
                    </div>
                    <dl class="uc-specs mt-2">
                        <?php foreach ($data as $k => $v): ?><dt><?= e($def['measurements'][$k]['label'] ?? $k) ?></dt><dd><?= e($v) ?>"</dd><?php endforeach; ?>
                    </dl>
                </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><div class="uc-empty"><i class="bi bi-rulers"></i><h2>No saved measurements</h2><p>Customise a garment and tick “Save these measurements”.</p><a class="uc-btn" href="<?= e(url('custom-size')) ?>">Open Custom Size Studio</a></div><?php endif; ?>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
