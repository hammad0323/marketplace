<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

// Singleton pages that have no SEO fields of their own anywhere else in the
// app (cities, categories, blog posts and static pages already have their
// own seo_title/seo_description columns, edited on their own forms — this
// page is for the pages that don't: the homepage and the search results
// page). page_reference_id stays NULL for all of these.
$pageTypes = [
    'home' => ['label' => 'Homepage', 'has_title' => true, 'hint' => 'Shown in Google and browser tabs for the homepage (/).'],
    'search' => ['label' => 'Search results', 'has_title' => false, 'hint' => 'Search results pages set their own title from the query, but you can still control the meta description shown in search engines.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pageType = $_POST['page_type'] ?? '';
    if (!isset($pageTypes[$pageType])) {
        flash_set('danger', 'Unknown page.');
        redirect('/admin/seo.php');
    }

    $data = [
        'meta_title' => clean_input($_POST['meta_title'] ?? ''),
        'meta_description' => clean_input($_POST['meta_description'] ?? ''),
        'meta_keywords' => clean_input($_POST['meta_keywords'] ?? ''),
        'og_image' => clean_input($_POST['og_image'] ?? ''),
        'canonical_url' => clean_input($_POST['canonical_url'] ?? ''),
    ];

    $existing = db_select_one($conn, 'SELECT id FROM seo_settings WHERE page_type = ? AND page_reference_id IS NULL', [$pageType]);
    if ($existing) {
        db_execute(
            $conn,
            'UPDATE seo_settings SET meta_title = ?, meta_description = ?, meta_keywords = ?, og_image = ?, canonical_url = ? WHERE id = ?',
            [$data['meta_title'], $data['meta_description'], $data['meta_keywords'], $data['og_image'], $data['canonical_url'], $existing['id']]
        );
    } else {
        db_execute(
            $conn,
            'INSERT INTO seo_settings (page_type, page_reference_id, meta_title, meta_description, meta_keywords, og_image, canonical_url) VALUES (?, NULL, ?, ?, ?, ?, ?)',
            [$pageType, $data['meta_title'], $data['meta_description'], $data['meta_keywords'], $data['og_image'], $data['canonical_url']]
        );
    }
    log_audit($conn, (int) $admin['id'], 'seo_settings', null, 'update', null, ['page_type' => $pageType] + $data);
    flash_set('success', $pageTypes[$pageType]['label'] . ' SEO updated.');
    redirect('/admin/seo.php');
}

$rows = db_select($conn, 'SELECT * FROM seo_settings WHERE page_reference_id IS NULL');
$byType = [];
foreach ($rows as $r) {
    $byType[$r['page_type']] = $r;
}

$cityCount = db_count($conn, 'SELECT COUNT(*) FROM cities', []);
$categoryCount = db_count($conn, 'SELECT COUNT(*) FROM categories', []);
$blogCount = db_count($conn, 'SELECT COUNT(*) FROM blogs', []);
$pageCount = db_count($conn, 'SELECT COUNT(*) FROM pages', []);

$adminPageTitle = 'SEO';
$adminActive = 'seo';
require __DIR__ . '/_layout_top.php';
?>

<div class="panel">
  <div class="panel-head"><h3>Site-wide pages</h3></div>
  <p style="color:var(--ink-mute);font-size:13.5px;margin-top:-8px;margin-bottom:18px;">These pages don't belong to a single city, category or listing, so they need their own meta title &amp; description here.</p>

  <?php foreach ($pageTypes as $key => $meta): ?>
    <?php $row = $byType[$key] ?? []; ?>
    <form method="post" class="form-w" style="border-top:1px solid var(--border);padding-top:18px;margin-top:18px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="page_type" value="<?php echo e($key); ?>">
      <h4 style="font-size:15px;margin-bottom:2px;"><?php echo e($meta['label']); ?></h4>
      <p class="form-hint" style="margin-top:0;margin-bottom:12px;"><?php echo e($meta['hint']); ?></p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <?php if ($meta['has_title']): ?>
          <div>
            <label>Meta title</label>
            <input type="text" name="meta_title" maxlength="180" value="<?php echo e($row['meta_title'] ?? ''); ?>" placeholder="Leave blank to use the site name">
          </div>
        <?php endif; ?>
        <div>
          <label>Meta keywords <span style="color:var(--ink-mute);font-weight:400;">(optional, comma-separated)</span></label>
          <input type="text" name="meta_keywords" maxlength="255" value="<?php echo e($row['meta_keywords'] ?? ''); ?>" placeholder="travel, trip planner, hotel booking">
        </div>
        <div style="grid-column:1/-1;">
          <label>Meta description</label>
          <textarea name="meta_description" rows="2" maxlength="300" placeholder="Shown under the title in search results — aim for 150–160 characters."><?php echo e($row['meta_description'] ?? ''); ?></textarea>
        </div>
        <div>
          <label>Social share image (og:image) URL</label>
          <input type="text" name="og_image" value="<?php echo e($row['og_image'] ?? ''); ?>" placeholder="https://…">
        </div>
        <div>
          <label>Canonical URL <span style="color:var(--ink-mute);font-weight:400;">(optional override)</span></label>
          <input type="text" name="canonical_url" value="<?php echo e($row['canonical_url'] ?? ''); ?>" placeholder="Leave blank to auto-generate">
        </div>
      </div>
      <button type="submit" class="btn-w btn-primary btn-sm" style="margin-top:14px;">Save <?php echo e($meta['label']); ?> SEO</button>
    </form>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="panel-head"><h3>Per-listing SEO</h3></div>
  <p style="color:var(--ink-mute);font-size:13.5px;margin-top:-8px;margin-bottom:18px;">Cities, categories and blog posts each carry their own SEO title &amp; description, edited right on their own page — no need to duplicate that here.</p>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
    <a href="<?php echo url('/admin/cities.php'); ?>" class="roadmap-card" style="text-decoration:none;color:inherit;">
      <div class="icon-wrap"><i class="bi bi-geo-alt"></i></div>
      <div><strong><?php echo (int) $cityCount; ?></strong> cities<div style="font-size:12.5px;color:var(--ink-mute);">Manage SEO on each city</div></div>
    </a>
    <a href="<?php echo url('/admin/categories.php'); ?>" class="roadmap-card" style="text-decoration:none;color:inherit;">
      <div class="icon-wrap"><i class="bi bi-grid"></i></div>
      <div><strong><?php echo (int) $categoryCount; ?></strong> categories<div style="font-size:12.5px;color:var(--ink-mute);">Manage SEO on each category</div></div>
    </a>
    <a href="<?php echo url('/admin/blog.php'); ?>" class="roadmap-card" style="text-decoration:none;color:inherit;">
      <div class="icon-wrap"><i class="bi bi-journal-text"></i></div>
      <div><strong><?php echo (int) $blogCount; ?></strong> blog posts<div style="font-size:12.5px;color:var(--ink-mute);">Manage SEO on each post</div></div>
    </a>
    <div class="roadmap-card">
      <div class="icon-wrap"><i class="bi bi-file-text"></i></div>
      <div><strong><?php echo (int) $pageCount; ?></strong> static pages<div style="font-size:12.5px;color:var(--ink-mute);">About, Terms, Privacy, FAQ</div></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
