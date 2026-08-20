<?php
require __DIR__ . '/includes/config.php';

$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$toolType = trim((string) ($_GET['type'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? '')); // popular|trending|featured|recent
$sort = trim((string) ($_GET['sort'] ?? 'popular'));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ["t.status = 'published'"];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = '(t.name LIKE ? OR t.short_description LIKE ? OR t.description LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($categoryId) {
    $where[] = 't.category_id = ?';
    $types .= 'i';
    $params[] = $categoryId;
}
if ($toolType !== '') {
    $where[] = 't.tool_type = ?';
    $types .= 's';
    $params[] = $toolType;
}
if ($filter === 'popular') { $where[] = 't.is_popular = 1'; }
if ($filter === 'trending') { $where[] = 't.is_trending = 1'; }
if ($filter === 'featured') { $where[] = 't.is_featured = 1'; }
if ($filter === 'recent') { $where[] = 't.created_at >= (NOW() - INTERVAL 30 DAY)'; }

$orderBy = match ($sort) {
    'viewed' => 't.views DESC',
    'recent' => 't.created_at DESC',
    'az' => 't.name ASC',
    default => 't.is_popular DESC, t.views DESC',
};

$whereSql = implode(' AND ', $where);
$countRow = tp_query_one("SELECT COUNT(*) c FROM tools t JOIN categories c ON c.id=t.category_id WHERE {$whereSql}", $types, $params);
$total = (int) ($countRow['c'] ?? 0);

$sql = "SELECT t.*, c.name AS category_name, c.slug AS category_slug FROM tools t
        JOIN categories c ON c.id = t.category_id
        WHERE {$whereSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
$results = tp_query($sql, $types . 'ii', [...$params, $perPage, ($page - 1) * $perPage]);

$categories = get_categories(true);
$totalPages = max(1, (int) ceil($total / $perPage));

$pageTitle = ($q !== '' ? 'Search: ' . $q : 'Search Tools') . ' — ' . tp_setting('site_name');
$pageDescriptionFallback = 'Search hundreds of calculators, converters, and generators.';
require __DIR__ . '/includes/header.php';
?>
<div class="tp-container py-4">
  <h1 class="h3 fw-bold mb-3"><?= $q !== '' ? 'Results for "' . e($q) . '"' : 'Search Tools' ?></h1>

  <form method="get" class="tp-card p-3 mb-4">
    <div class="row g-2">
      <div class="col-md-4"><input type="text" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search tools..."></div>
      <div class="col-md-3">
        <select name="category" class="form-select">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="filter" class="form-select">
          <option value="">All</option>
          <?php foreach (['popular' => 'Popular', 'trending' => 'Trending', 'featured' => 'Featured', 'recent' => 'Recently Added'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="sort" class="form-select">
          <?php foreach (['popular' => 'Most Popular', 'viewed' => 'Most Viewed', 'recent' => 'Recently Added', 'az' => 'A–Z'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button class="btn tp-btn-calc h-100">Go</button></div>
    </div>
  </form>

  <p class="text-muted"><?= (int) $total ?> tools found</p>
  <div class="row g-3">
    <?php foreach ($results as $t): ?>
      <div class="col-sm-6 col-lg-4">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
          <div class="d-flex justify-content-between text-muted small">
            <span><?= e($t['category_name']) ?></span>
            <span><?= e(ucfirst(str_replace('_', ' ', $t['tool_type']))) ?></span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$results): ?><p class="text-muted">No tools matched your search. Try a different keyword.</p><?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-4"><ul class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
