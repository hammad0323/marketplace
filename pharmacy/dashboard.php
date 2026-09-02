<?php
require __DIR__ . '/../config/config.php';
require_pharmacy_page();

$user = current_user();
$pharmacyId = current_profile_id();
$pharmacy = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT * FROM pharmacies WHERE id = ' . (int) $pharmacyId));

$pageTitle = 'Dashboard';
$heading = 'My Dashboard';

if ($pharmacy['verification_status'] === 'verified') {
    $stats = mysqli_fetch_assoc(mysqli_query(db(), "
        SELECT
            (SELECT COUNT(*) FROM doctor_products WHERE pharmacy_id = $pharmacyId AND seller_type = 'pharmacy') AS total_products,
            (SELECT COUNT(*) FROM orders WHERE pharmacy_id = $pharmacyId AND seller_type = 'pharmacy' AND status = 'pending') AS pending_orders,
            (SELECT COUNT(*) FROM orders WHERE pharmacy_id = $pharmacyId AND seller_type = 'pharmacy' AND status = 'completed') AS completed_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE pharmacy_id = $pharmacyId AND seller_type = 'pharmacy' AND status = 'completed') AS revenue
    "));
}
require __DIR__ . '/includes/header.php';
?>
<?php if ($pharmacy['verification_status'] === 'pending'): ?>
<div class="card" style="padding:48px 32px;text-align:center;max-width:560px;margin:0 auto;" data-reveal>
    <div style="width:64px;height:64px;border-radius:18px;background:var(--color-warning);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;"><i class="ri-time-line"></i></div>
    <h3 style="margin-bottom:10px;">Registration under review</h3>
    <p style="color:var(--color-text-muted);margin-bottom:20px;">Our team is reviewing your registration number and certificates. You'll get an email as soon as your store is verified and can start selling.</p>
</div>
<?php elseif ($pharmacy['verification_status'] === 'rejected'): ?>
<div class="card" style="padding:48px 32px;text-align:center;max-width:560px;margin:0 auto;" data-reveal>
    <div style="width:64px;height:64px;border-radius:18px;background:var(--color-danger);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;"><i class="ri-close-line"></i></div>
    <h3 style="margin-bottom:10px;">Registration not approved</h3>
    <p style="color:var(--color-text-muted);margin-bottom:8px;">Your pharmacy registration could not be approved.</p>
    <?php if ($pharmacy['verification_note']): ?><p style="color:var(--color-text-muted);margin-bottom:20px;font-style:italic;">"<?= e($pharmacy['verification_note']) ?>"</p><?php endif; ?>
    <a href="/contact" class="btn btn-primary">Contact Support</a>
</div>
<?php else: ?>
<div class="grid grid-4 stagger" style="margin-bottom:32px;">
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--gradient-primary);"><i class="ri-store-2-line"></i></div>
        <div><b><?= (int) $stats['total_products'] ?></b><span>Listings</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-warning);"><i class="ri-time-line"></i></div>
        <div><b><?= (int) $stats['pending_orders'] ?></b><span>Pending Orders</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-success);"><i class="ri-checkbox-circle-line"></i></div>
        <div><b><?= (int) $stats['completed_orders'] ?></b><span>Completed Orders</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:#F59E0B;"><i class="ri-money-dollar-circle-line"></i></div>
        <div><b><?= format_currency($stats['revenue']) ?></b><span>Revenue</span></div>
    </div>
</div>
<div class="card" style="padding:32px;text-align:center;" data-reveal>
    <h3 style="margin-bottom:10px;">Welcome, <?= e($pharmacy['store_name']) ?>!</h3>
    <p style="color:var(--color-text-muted);margin-bottom:20px;">Manage your catalog and incoming orders from My Store.</p>
    <a href="/pharmacy/products" class="btn btn-primary">Go to My Store</a>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
