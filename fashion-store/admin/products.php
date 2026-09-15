<?php
$pageTitle = 'Products';
require_once __DIR__ . '/includes/admin_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM products WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Product deleted.');
    } elseif ($action === 'toggle') {
        mysqli_query($mysqli, "UPDATE products SET status = IF(status='active','inactive','active') WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Status updated.');
    }
    redirect('products.php');
}

$search = trim($_GET['q'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = "(p.name LIKE CONCAT('%',?,'%') OR p.sku LIKE CONCAT('%',?,'%'))";
    $types .= 'ss';
    $params[] = $search;
    $params[] = $search;
}
if ($categoryFilter) {
    $where[] = "p.category_id = ?";
    $types .= 'i';
    $params[] = $categoryFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($mysqli, "SELECT COUNT(*) c FROM products p $whereSql");
if ($types) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['c'];
$totalPages = max(1, ceil($total / $perPage));

$sql = "SELECT p.*, c.name AS category_name,
        (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        $whereSql ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = mysqli_prepare($mysqli, $sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt);

$categories = mysqli_query($mysqli, "SELECT id, name FROM categories ORDER BY name");
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title">Products</h1>
  <a href="product_form.php" class="btn btn-primary text-white"><i class="bi bi-plus-lg"></i> Add Product</a>
</div>

<div class="admin-card mb-3">
  <form class="row g-2">
    <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Search name or SKU" value="<?= e($search) ?>"></div>
    <div class="col-md-4">
      <select name="category" class="form-select">
        <option value="">All Categories</option>
        <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $categoryFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-dark w-100">Filter</button></div>
  </form>
</div>

<div class="admin-card">
  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead><tr><th>Image</th><th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Flags</th><th></th></tr></thead>
    <tbody>
    <?php while ($p = mysqli_fetch_assoc($products)): ?>
      <tr>
        <td><img src="<?= e(BASE_URL . '/' . ($p['image'] ?: 'assets/img/placeholder.svg')) ?>" class="thumb-sm"></td>
        <td><?= e($p['name']) ?></td>
        <td><?= e($p['sku']) ?></td>
        <td><?= e($p['category_name']) ?></td>
        <td>
          <?php if ($p['sale_price']): ?>
            <span class="text-decoration-line-through text-muted small"><?= format_price($p['regular_price']) ?></span><br><?= format_price($p['sale_price']) ?>
          <?php else: ?>
            <?= format_price($p['regular_price']) ?>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $p['stock_qty'] <= 5 ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= (int)$p['stock_qty'] ?></span></td>
        <td><span class="badge <?= $p['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($p['status']) ?></span></td>
        <td class="small text-muted">
          <?= $p['is_featured'] ? 'Featured ' : '' ?><?= $p['is_new_arrival'] ? 'New ' : '' ?><?= $p['is_best_seller'] ? 'Best ' : '' ?><?= $p['is_trending'] ? 'Trend' : '' ?>
        </td>
        <td class="text-end text-nowrap">
          <a href="product_form.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle2-on"></i></button></form>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  </div>
  <?php if ($totalPages > 1): ?>
  <nav><ul class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&category=<?= $categoryFilter ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
