<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();

$stmt = mysqli_prepare(db(), 'SELECT is_premium FROM doctors WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$isPremium = (bool) (mysqli_stmt_get_result($stmt)->fetch_assoc()['is_premium'] ?? 0);
mysqli_stmt_close($stmt);

$pageTitle = 'My Store';
$heading = 'My Store';

if ($isPremium) {
    $stmt = mysqli_prepare(db(), 'SELECT p.*, c.name AS category_name FROM doctor_products p LEFT JOIN product_categories c ON c.id = p.category_id WHERE p.doctor_id = ? ORDER BY p.created_at DESC');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $categories = mysqli_query(db(), 'SELECT id, name FROM product_categories ORDER BY name')->fetch_all(MYSQLI_ASSOC);

    $stmt = mysqli_prepare(db(), "
        SELECT o.*, u.full_name AS patient_name, u.avatar AS patient_avatar,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', dp.name) SEPARATOR ', ') FROM order_items oi JOIN doctor_products dp ON dp.id = oi.product_id WHERE oi.order_id = o.id) AS items_label
        FROM orders o
        JOIN patients p ON p.id = o.patient_id
        JOIN users u ON u.id = p.user_id
        WHERE o.doctor_id = ?
        ORDER BY o.created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $orders = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $extraScripts = '<script src="/assets/js/doctor-products.js"></script>';
}
require __DIR__ . '/includes/header.php';
?>
<?php if (!$isPremium): ?>
<div class="card" style="padding:48px 32px;text-align:center;max-width:560px;margin:0 auto;" data-reveal>
    <div style="width:64px;height:64px;border-radius:18px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;"><i class="ri-vip-crown-fill"></i></div>
    <h3 style="margin-bottom:10px;">Premium doctors only</h3>
    <p style="color:var(--color-text-muted);margin-bottom:20px;">Selling products and services on your public profile is a Premium feature. Contact the MediConnect team to upgrade your account and unlock your store.</p>
    <a href="/contact" class="btn btn-primary">Contact Us to Upgrade</a>
</div>
<?php else: ?>
<div class="tabs-row">
    <button class="tab-btn active" data-tab="catalog">My Catalog</button>
    <button class="tab-btn" data-tab="orders">Orders <?php if ($pendingCount = count(array_filter($orders, fn($o) => $o['status'] === 'pending'))): ?><span class="badge badge-pending" style="margin-left:4px;"><?= $pendingCount ?></span><?php endif; ?></button>
</div>

<div class="doc-tab-panel" id="panel-catalog">
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <button type="button" class="btn btn-primary btn-sm" id="add-product-btn"><i class="ri-add-line"></i> Add Product / Service</button>
    </div>
    <?php if (!$products): ?>
    <div class="card empty-state" data-reveal><i class="ri-store-2-line"></i><h4>No listings yet</h4><p>Add a product or service to start selling from your public profile.</p></div>
    <?php else: ?>
    <div class="grid grid-3 stagger" id="product-list">
        <?php foreach ($products as $p): ?>
        <div class="card" style="padding:20px;<?= $p['is_active'] ? '' : 'opacity:0.55;' ?>" data-reveal data-product-id="<?= (int) $p['id'] ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                <span class="badge badge-<?= $p['type'] === 'service' ? 'pending' : 'verified' ?>"><?= $p['type'] === 'service' ? 'Service' : 'Product' ?></span>
                <?php if (!$p['is_active']): ?><span class="badge">Inactive</span><?php endif; ?>
            </div>
            <?php if ($p['image']): ?><img src="/uploads/<?= e($p['image']) ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:12px;margin-bottom:10px;"><?php endif; ?>
            <strong style="display:block;margin-bottom:4px;"><?= e($p['name']) ?></strong>
            <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;min-height:36px;"><?= e(excerpt($p['description'] ?? '', 80)) ?></p>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="color:var(--color-primary);font-size:16px;"><?= format_currency($p['price']) ?></strong>
                <span style="font-size:12px;color:var(--color-text-muted);"><?= $p['type'] === 'service' ? e($p['duration_label'] ?: '') : ((int) $p['stock']) . ' in stock' ?></span>
            </div>
            <div style="display:flex;gap:8px;margin-top:14px;">
                <button type="button" class="btn btn-outline btn-sm btn-block btn-edit-product"
                    data-id="<?= (int) $p['id'] ?>" data-type="<?= e($p['type']) ?>" data-name="<?= e($p['name']) ?>"
                    data-category="<?= (int) ($p['category_id'] ?? 0) ?>" data-description="<?= e($p['description'] ?? '') ?>"
                    data-price="<?= e($p['price']) ?>" data-stock="<?= e((string) $p['stock']) ?>" data-duration="<?= e($p['duration_label'] ?? '') ?>"
                    data-active="<?= (int) $p['is_active'] ?>"
                    data-meta-title="<?= e($p['meta_title'] ?? '') ?>" data-meta-description="<?= e($p['meta_description'] ?? '') ?>">Edit</button>
                <button type="button" class="btn-icon btn-delete-product" data-id="<?= (int) $p['id'] ?>" style="width:36px;height:36px;flex-shrink:0;"><i class="ri-delete-bin-line"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="doc-tab-panel" id="panel-orders" style="display:none;">
    <?php if (!$orders): ?>
    <div class="card empty-state" data-reveal><i class="ri-shopping-bag-3-line"></i><h4>No orders yet</h4><p>Requests from patients for your products/services will show up here.</p></div>
    <?php else: ?>
    <div class="card table-card" data-reveal>
        <div class="table-scroll"><table class="data-table">
            <thead><tr><th>Patient</th><th>Items</th><th>Total</th><th>Notes</th><th>Status</th><th></th></tr></thead>
            <tbody id="order-list">
            <?php foreach ($orders as $o): ?>
            <tr data-order-id="<?= (int) $o['id'] ?>">
                <td class="table-user"><img src="<?= e(avatar_url($o['patient_avatar'], $o['patient_name'])) ?>"><?= e($o['patient_name']) ?></td>
                <td><?= e($o['items_label']) ?></td>
                <td><?= format_currency($o['total_amount']) ?></td>
                <td style="max-width:220px;font-size:12.5px;color:var(--color-text-muted);">
                    <?= e(excerpt($o['notes'] ?? '', 60)) ?>
                    <?php if ($o['contact_phone']): ?><br><i class="ri-phone-line"></i> <?= e($o['contact_phone']) ?><?php endif; ?>
                    <?php if (!empty($o['shipping_address'])): ?><br><i class="ri-map-pin-line"></i> <?= e($o['shipping_address']) ?><?php endif; ?>
                </td>
                <td><span class="status-pill status-<?= e($o['status']) ?> order-status-label"><?= ucfirst($o['status']) ?></span></td>
                <td>
                    <?php if ($o['status'] === 'pending'): ?>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn btn-primary btn-sm btn-order-action" data-action="confirmed">Confirm</button>
                        <button type="button" class="btn btn-outline btn-sm btn-order-action" data-action="cancelled">Cancel</button>
                    </div>
                    <?php elseif ($o['status'] === 'confirmed'): ?>
                    <button type="button" class="btn btn-primary btn-sm btn-order-action" data-action="completed">Mark Completed</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="product-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:560px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;">
            <h3 style="margin-bottom:20px;" id="product-modal-title">Add Product / Service</h3>
            <form id="product-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="product-id">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <div style="display:flex;gap:16px;">
                        <label class="checkbox-row"><input type="radio" name="type" value="product" checked> Physical Product</label>
                        <label class="checkbox-row"><input type="radio" name="type" value="service"> Service</label>
                    </div>
                </div>
                <div class="form-group" data-field="name">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-control" name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="price">
                        <label class="form-label">Price ($)</label>
                        <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" id="stock-field">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" name="stock" min="0" step="1">
                    </div>
                    <div class="form-group" id="duration-field" style="display:none;">
                        <label class="form-label">Duration / Sessions</label>
                        <input type="text" class="form-control" name="duration_label" placeholder="e.g. 3 sessions, 45 min">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Image (optional)</label>
                    <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <details style="margin-bottom:20px;">
                    <summary style="cursor:pointer;font-size:13.5px;font-weight:600;color:var(--color-text-muted);margin-bottom:12px;">SEO (optional — auto-filled from name/description if left blank)</summary>
                    <div class="form-group" style="margin-top:12px;">
                        <label class="form-label">Meta Title</label>
                        <input type="text" class="form-control" name="meta_title" id="product-meta-title" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea class="form-control" name="meta_description" id="product-meta-description" rows="2" maxlength="300"></textarea>
                    </div>
                </details>
                <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="is_active" value="1" checked> Active (visible on my public profile)</label>
                <button type="submit" class="btn btn-primary btn-block">Save Listing</button>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.tabs-row .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tabs-row .tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.doc-tab-panel').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById('panel-' + btn.getAttribute('data-tab')).style.display = 'block';
    });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
