<?php
$c = require_customer();
seo_set(['title' => 'My Alterations', 'noindex' => true]);
$rows = db_all('SELECT * FROM alteration_requests WHERE customer_id = ? ORDER BY id DESC', [(int) $c['id']]);
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <h1 class="uc-page-title">My Alterations</h1>
    <div class="uc-account">
        <?php partial('components/account-nav', ['active' => 'alterations']); ?>
        <div class="uc-account-main">
            <div class="uc-card-box">
                <?php if ($rows): ?>
                <div class="table-responsive"><table class="table uc-table align-middle mb-0"><thead><tr><th>Reference</th><th>Date</th><th>Status</th><th>Price</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['reference']) ?></strong></td><td><?= e(format_date($r['created_at'])) ?></td><td><?= alteration_status_badge($r['status']) ?></td>
                    <td><?= $r['quoted_price'] !== null ? e(money($r['quoted_price'])) : e(alteration_price_range((float) $r['est_min'], (float) $r['est_max'])) . ' <small class="text-muted">est.</small>' ?></td>
                    <td class="text-end"><a href="<?= e(alteration_link($r)) ?>">View</a></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><p class="text-muted mb-0">No alteration requests yet. <a href="<?= e(url('alterations')) ?>">Request an alteration</a></p><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
