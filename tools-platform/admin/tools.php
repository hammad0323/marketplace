<?php
require __DIR__ . '/../includes/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $action = $_POST['bulk_action'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if ($ids && $action) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $map = [
            'publish' => "UPDATE tools SET status='published' WHERE id IN ($placeholders)",
            'disable' => "UPDATE tools SET status='disabled' WHERE id IN ($placeholders)",
            'draft' => "UPDATE tools SET status='draft' WHERE id IN ($placeholders)",
            'feature' => "UPDATE tools SET is_featured=1 WHERE id IN ($placeholders)",
            'unfeature' => "UPDATE tools SET is_featured=0 WHERE id IN ($placeholders)",
            'popular' => "UPDATE tools SET is_popular=1 WHERE id IN ($placeholders)",
            'trending' => "UPDATE tools SET is_trending=1 WHERE id IN ($placeholders)",
            'delete' => "DELETE FROM tools WHERE id IN ($placeholders)",
        ];
        if (isset($map[$action])) {
            if ($action === 'delete') {
                $slugs = tp_query("SELECT slug FROM tools WHERE id IN ($placeholders)", $types, $ids);
                foreach ($slugs as $row) {
                    tp_delete_route_file($row['slug']);
                }
            }
            tp_execute($map[$action], $types, $ids);
            tp_log_activity($_SESSION['admin_id'], 'bulk_' . $action, 'tool', null, implode(',', $ids));
        }
        tp_flash_set('success', 'Bulk action applied to ' . count($ids) . ' tool(s).');
    }
    header('Location: ' . tp_url('admin/tools.php'));
    exit;
}

if (isset($_GET['duplicate'])) {
    $srcId = (int) $_GET['duplicate'];
    $src = get_tool($srcId);
    if ($src) {
        $newName = $src['name'] . ' (Copy)';
        $newSlug = tp_unique_slug('tools', $src['slug'] . '-copy');
        $result = tp_execute(
            'INSERT INTO tools (category_id, subcategory_id, name, slug, short_description, description, icon, featured_image, tool_file, tool_type, status, is_featured, is_popular, is_trending, sort_order)
             SELECT category_id, subcategory_id, ?, ?, short_description, description, icon, featured_image, tool_file, tool_type, "draft", 0, 0, 0, sort_order FROM tools WHERE id = ?',
            'ssi',
            [$newName, $newSlug, $srcId]
        );
        $newId = $result['insert_id'];
        if ($newId) {
            $content = tp_query_one('SELECT * FROM tool_content WHERE tool_id = ?', 'i', [$srcId]);
            if ($content) {
                unset($content['tool_id']);
                $cols = implode(',', array_keys($content));
                $ph = implode(',', array_fill(0, count($content), '?'));
                tp_execute("INSERT INTO tool_content (tool_id, $cols) VALUES (?, $ph)", 'i' . str_repeat('s', count($content)), [$newId, ...array_values($content)]);
            }
            foreach (tp_query('SELECT * FROM tool_faqs WHERE tool_id = ?', 'i', [$srcId]) as $faq) {
                tp_execute('INSERT INTO tool_faqs (tool_id, question, answer, sort_order, status) VALUES (?,?,?,?,?)', 'issis', [$newId, $faq['question'], $faq['answer'], $faq['sort_order'], $faq['status']]);
            }
            foreach (tp_query('SELECT * FROM tool_formulas WHERE tool_id = ?', 'i', [$srcId]) as $f) {
                tp_execute('INSERT INTO tool_formulas (tool_id, label, formula_text, sort_order) VALUES (?,?,?,?)', 'issi', [$newId, $f['label'], $f['formula_text'], $f['sort_order']]);
            }
            foreach (tp_query('SELECT * FROM tool_examples WHERE tool_id = ?', 'i', [$srcId]) as $ex) {
                tp_execute('INSERT INTO tool_examples (tool_id, title, input_summary, output_summary, sort_order) VALUES (?,?,?,?,?)', 'isssi', [$newId, $ex['title'], $ex['input_summary'], $ex['output_summary'], $ex['sort_order']]);
            }
            foreach (tp_query('SELECT related_tool_id, sort_order FROM tool_related WHERE tool_id = ?', 'i', [$srcId]) as $rel) {
                tp_execute('INSERT INTO tool_related (tool_id, related_tool_id, sort_order) VALUES (?,?,?)', 'iii', [$newId, $rel['related_tool_id'], $rel['sort_order']]);
            }
            tp_write_tool_route($newSlug);
            tp_log_activity($_SESSION['admin_id'], 'duplicate_tool', 'tool', $newId, "from #$srcId");
            tp_flash_set('success', 'Tool duplicated as draft: ' . $newName);
        }
    }
    header('Location: ' . tp_url('admin/tools.php'));
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tools-export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Slug', 'Category', 'Short Description', 'SEO Title', 'Meta Description', 'Focus Keyword', 'Status']);
    $rows = tp_query(
        "SELECT t.name, t.slug, c.name AS category_name, t.short_description, s.seo_title, s.meta_description, s.focus_keyword, t.status
         FROM tools t JOIN categories c ON c.id = t.category_id
         LEFT JOIN seo_settings s ON s.entity_type='tool' AND s.entity_id = t.id"
    );
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$categoryFilter = (int) ($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$where = ['1=1'];
$types = '';
$params = [];
if ($categoryFilter) { $where[] = 't.category_id = ?'; $types .= 'i'; $params[] = $categoryFilter; }
if ($statusFilter) { $where[] = 't.status = ?'; $types .= 's'; $params[] = $statusFilter; }

$tools = tp_query(
    "SELECT t.*, c.name AS category_name FROM tools t JOIN categories c ON c.id = t.category_id
     WHERE " . implode(' AND ', $where) . " ORDER BY t.updated_at DESC",
    $types,
    $params
);
$categories = get_categories(false);

$adminPageTitle = 'Tools';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <form class="d-flex gap-2">
    <select name="category" class="form-select" onchange="this.form.submit()">
      <option value="0">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $categoryFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach (['published', 'draft', 'disabled', 'scheduled'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="d-flex gap-2">
    <a href="<?= tp_url('admin/tools.php?export=csv') ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Export CSV</a>
    <a href="<?= tp_url('admin/tool-form.php') ?>" class="btn tp-btn-calc" style="width:auto;"><i class="bi bi-plus-lg"></i> Add New Tool</a>
  </div>
</div>

<form method="post" class="admin-card">
  <?= tp_csrf_field() ?>
  <div class="d-flex gap-2 mb-3">
    <select name="bulk_action" id="bulkActionSelect" class="form-select" style="max-width:220px;">
      <option value="">Bulk action...</option>
      <option value="publish">Publish</option>
      <option value="draft">Set to Draft</option>
      <option value="disable">Disable</option>
      <option value="feature">Mark Featured</option>
      <option value="unfeature">Unmark Featured</option>
      <option value="popular">Mark Popular</option>
      <option value="trending">Mark Trending</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" id="applyBulkAction" class="btn btn-outline-dark">Apply</button>
  </div>
  <table class="table tp-datatable">
    <thead><tr>
      <th><input type="checkbox" id="selectAllRows"></th>
      <th>Tool</th><th>Category</th><th>Status</th><th>Views</th>
      <th>Featured</th><th>Popular</th><th>Trending</th><th>SEO Score</th><th>Updated</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($tools as $t): ?>
      <tr>
        <td><input type="checkbox" class="row-checkbox" name="ids[]" value="<?= (int) $t['id'] ?>"></td>
        <td><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?> me-1"></i><?= e($t['name']) ?><br><small class="text-muted">/<?= e($t['slug']) ?></small></td>
        <td><?= e($t['category_name']) ?></td>
        <td><span class="badge bg-<?= $t['status'] === 'published' ? 'success' : ($t['status'] === 'draft' ? 'secondary' : 'danger') ?>"><?= e($t['status']) ?></span></td>
        <td><?= number_format($t['views']) ?></td>
        <td><?= $t['is_featured'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
        <td><?= $t['is_popular'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
        <td><?= $t['is_trending'] ? '<i class="bi bi-check-lg text-success"></i>' : '' ?></td>
        <td><?= (int) $t['seo_score'] ?>%</td>
        <td><?= date('M j', strtotime($t['updated_at'])) ?></td>
        <td class="text-nowrap">
          <a href="<?= tp_url('admin/tool-form.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <a href="<?= tp_url('admin/tools.php?duplicate=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary">Duplicate</a>
          <a href="<?= tp_url($t['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Preview</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</form>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
