<?php
$pageTitle = 'Attributes';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_attribute') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO attributes (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Attribute added.');
        }
    } elseif ($action === 'delete_attribute') {
        mysqli_query($mysqli, "DELETE FROM attributes WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Attribute deleted.');
    } elseif ($action === 'add_value') {
        $attrId = (int)$_POST['attribute_id'];
        $value = trim($_POST['value'] ?? '');
        if ($value !== '') {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO attribute_values (attribute_id, value) VALUES (?,?)");
            mysqli_stmt_bind_param($stmt, 'is', $attrId, $value);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Value added.');
        }
    } elseif ($action === 'delete_value') {
        mysqli_query($mysqli, "DELETE FROM attribute_values WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Value deleted.');
    }
    redirect('attributes.php');
}

$attributes = mysqli_query($mysqli, "SELECT * FROM attributes ORDER BY name");
?>
<h1 class="page-title mb-4">Product Attributes</h1>

<div class="admin-card mb-4">
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_attribute">
    <div class="col-auto"><label class="form-label">New Attribute (e.g. Size, Color)</label><input type="text" name="name" class="form-control" required></div>
    <div class="col-auto"><button class="btn btn-primary text-white">Add Attribute</button></div>
  </form>
</div>

<div class="row g-4">
<?php while ($attr = mysqli_fetch_assoc($attributes)):
  $valuesRes = mysqli_query($mysqli, "SELECT * FROM attribute_values WHERE attribute_id = " . (int)$attr['id'] . " ORDER BY id");
?>
  <div class="col-md-6">
    <div class="admin-card h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><?= e($attr['name']) ?></h2>
        <form method="post" class="confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete_attribute"><input type="hidden" name="id" value="<?= (int)$attr['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
      </div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php while ($v = mysqli_fetch_assoc($valuesRes)): ?>
          <span class="badge text-bg-light border d-flex align-items-center gap-2 p-2">
            <?= e($v['value']) ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete_value"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
              <button class="btn btn-sm p-0 border-0 text-danger" style="line-height:1"><i class="bi bi-x-circle"></i></button>
            </form>
          </span>
        <?php endwhile; ?>
      </div>
      <form method="post" class="d-flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_value"><input type="hidden" name="attribute_id" value="<?= (int)$attr['id'] ?>">
        <input type="text" name="value" class="form-control form-control-sm" placeholder="Add value" required>
        <button class="btn btn-sm btn-outline-primary">Add</button>
      </form>
    </div>
  </div>
<?php endwhile; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
