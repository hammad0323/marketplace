<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $rate = $_POST['rate_percent'] !== '' ? (float) $_POST['rate_percent'] : null;
    $existing = db_select_one($conn, 'SELECT id FROM commissions WHERE category_id = ?', [$categoryId]);

    if ($rate === null) {
        if ($existing) {
            db_execute($conn, 'DELETE FROM commissions WHERE id = ?', [(int) $existing['id']]);
        }
        flash_set('success', 'Override removed — this category now uses the platform default.');
    } elseif ($existing) {
        db_execute($conn, 'UPDATE commissions SET rate_percent = ?, is_active = 1 WHERE id = ?', [$rate, (int) $existing['id']]);
        flash_set('success', 'Commission updated.');
    } else {
        db_execute($conn, 'INSERT INTO commissions (category_id, rate_percent, is_active) VALUES (?, ?, 1)', [$categoryId, $rate]);
        flash_set('success', 'Commission override created.');
    }
    log_audit($conn, (int) $admin['id'], 'commission', $categoryId, 'save');
    redirect('/admin/commissions.php');
}

$categories = db_select(
    $conn,
    'SELECT c.id, c.name, c.icon, cm.rate_percent
     FROM categories c LEFT JOIN commissions cm ON cm.category_id = c.id
     WHERE c.is_active = 1 ORDER BY c.sort_order'
);
$defaultRate = get_setting($conn, 'default_commission_percent', 10);

$adminPageTitle = 'Commissions';
$adminActive = 'commissions';
require __DIR__ . '/_layout_top.php';
?>

<div class="alert-w alert-info"><i class="bi bi-info-circle-fill"></i> Platform default commission is <?php echo e($defaultRate); ?>% (change it under Settings → Finance). Set a category override below to charge a different rate for that category.</div>

<div class="panel">
  <div class="panel-head"><h3>Category commission overrides</h3></div>
  <table class="table-w">
    <thead><tr><th>Category</th><th>Rate</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td><i class="bi <?php echo e($cat['icon'] ?: 'bi-tag'); ?>"></i> <?php echo e($cat['name']); ?></td>
          <td><?php echo $cat['rate_percent'] !== null ? number_format((float) $cat['rate_percent'], 2) . '%' : 'Default (' . e($defaultRate) . '%)'; ?></td>
          <td style="text-align:right;">
            <form method="post" style="display:inline-flex;gap:6px;align-items:center;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="category_id" value="<?php echo (int) $cat['id']; ?>">
              <input type="number" step="0.01" name="rate_percent" value="<?php echo e($cat['rate_percent']); ?>" placeholder="Default" style="width:90px;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);">
              <button type="submit" class="btn-w btn-outline btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
