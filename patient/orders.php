<?php
require __DIR__ . '/../config/config.php';
require_patient_page();

$patientId = current_profile_id();

$orders = mysqli_query(db(), "
    SELECT o.*, u.full_name AS doctor_name, u.avatar AS doctor_avatar, d.slug AS doctor_slug,
        (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', dp.name) SEPARATOR ', ') FROM order_items oi JOIN doctor_products dp ON dp.id = oi.product_id WHERE oi.order_id = o.id) AS items_label
    FROM orders o JOIN doctors d ON d.id = o.doctor_id JOIN users u ON u.id = d.user_id
    WHERE o.patient_id = $patientId
    ORDER BY o.created_at DESC
");

$pageTitle = 'My Orders';
$heading = 'My Orders';
require __DIR__ . '/includes/header.php';
?>
<?php if (mysqli_num_rows($orders) === 0): ?>
<div class="card empty-state" data-reveal><i class="ri-shopping-bag-3-line"></i><h4>No orders yet</h4><p>Products and services you request from a doctor's profile will show up here.</p></div>
<?php else: ?>
<div class="grid grid-2 stagger">
    <?php while ($o = mysqli_fetch_assoc($orders)): ?>
    <div class="card" style="padding:22px;" data-reveal>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div style="display:flex;gap:10px;align-items:center;">
                <img src="<?= e(avatar_url($o['doctor_avatar'], $o['doctor_name'])) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
                <div>
                    <a href="<?= e(doctor_url($o['doctor_slug'])) ?>" style="font-weight:700;"><?= e($o['doctor_name']) ?></a>
                    <div style="font-size:12px;color:var(--color-text-muted);"><?= e($o['order_number']) ?></div>
                </div>
            </div>
            <span class="status-pill status-<?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span>
        </div>
        <p style="margin-bottom:8px;"><?= e($o['items_label']) ?></p>
        <?php if ($o['notes']): ?><p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:8px;"><?= e($o['notes']) ?></p><?php endif; ?>
        <?php if (!empty($o['shipping_address'])): ?><p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:8px;"><i class="ri-map-pin-line"></i> <?= e($o['shipping_address']) ?></p><?php endif; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong style="color:var(--color-primary);"><?= format_currency($o['total_amount']) ?></strong>
            <span style="font-size:12px;color:var(--color-text-muted);"><?= time_ago($o['created_at']) ?></span>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
