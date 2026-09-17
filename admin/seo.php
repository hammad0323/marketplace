<?php
require __DIR__ . '/../config.php';
wh_require_page_access('seo');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $pageKey = wh_input_post('page_key');
    $data = [
        'seo_title' => wh_input_post('seo_title'),
        'meta_description' => wh_input_post('meta_description'),
        'keywords' => wh_input_post('keywords'),
        'canonical_url' => wh_input_post('canonical_url'),
        'og_image' => wh_input_post('og_image'),
        'robots' => wh_input_post('robots') ?: 'index,follow',
    ];
    $exists = wh_fetch_one('SELECT id FROM seo_settings WHERE business_id=? AND page_key=?', 'is', [$businessId, $pageKey]);
    if ($exists) {
        wh_update('seo_settings', $data, 'id = ?', [$exists['id']]);
    } else {
        $data['business_id'] = $businessId;
        $data['page_key'] = $pageKey;
        wh_insert('seo_settings', $data);
    }
    wh_flash_set('success', 'SEO settings saved for "' . $pageKey . '".');
    wh_redirect(BASE_URL . '/admin/seo.php');
}

$pages = ['home' => 'Homepage', 'halls' => 'Halls Listing', 'gallery' => 'Gallery', 'availability' => 'Availability', 'booking' => 'Online Booking', 'contact' => 'Contact', 'blog' => 'Blog', 'about' => 'About Us'];
$rows = wh_fetch_all('SELECT * FROM seo_settings WHERE business_id=?', 'i', [$businessId]);
$byKey = [];
foreach ($rows as $r) { $byKey[$r['page_key']] = $r; }

$pageTitle = 'SEO Settings';
$activePage = 'seo';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <h3 style="margin-bottom:16px;">Page-by-Page SEO</h3>
  <p class="hint">Control meta title, description, keywords, canonical URL, robots and Open Graph image for every major page — no code editing required.</p>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Page</th><th>SEO Title</th><th>Meta Description</th><th>Robots</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pages as $key => $label): $row = $byKey[$key] ?? null; ?>
      <tr>
        <td><strong><?= e($label) ?></strong></td>
        <td><?= e($row['seo_title'] ?? '—') ?></td>
        <td><?= e(mb_strimwidth($row['meta_description'] ?? '—', 0, 60, '…')) ?></td>
        <td><?= e($row['robots'] ?? 'index,follow') ?></td>
        <td><button type="button" class="btn btn-light btn-sm" onclick='openSeoModal("<?= e($key) ?>", <?= json_encode($row ?: new stdClass()) ?>)'><i class="fa-solid fa-pen"></i> Edit</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal-overlay" id="seoModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-head"><h3 id="seoModalTitle">Edit SEO</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="page_key" id="seoPageKey">
      <div class="form-group"><label>SEO Title</label><input type="text" name="seo_title" id="seoTitle"></div>
      <div class="form-group"><label>Meta Description</label><textarea name="meta_description" id="seoMeta" rows="2"></textarea></div>
      <div class="form-group"><label>Keywords</label><input type="text" name="keywords" id="seoKeywords"></div>
      <div class="form-grid">
        <div class="form-group"><label>Canonical URL</label><input type="text" name="canonical_url" id="seoCanonical"></div>
        <div class="form-group"><label>Robots</label>
          <select name="robots" id="seoRobots">
            <option value="index,follow">index, follow</option>
            <option value="noindex,follow">noindex, follow</option>
            <option value="index,nofollow">index, nofollow</option>
            <option value="noindex,nofollow">noindex, nofollow</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Open Graph Image (path)</label><input type="text" name="og_image" id="seoOgImage" placeholder="uploads/gallery/..."></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save SEO</button>
    </form>
  </div>
</div>
<script>
function openSeoModal(key, row) {
  document.getElementById('seoModalTitle').textContent = 'Edit SEO — ' + key;
  document.getElementById('seoPageKey').value = key;
  document.getElementById('seoTitle').value = row.seo_title || '';
  document.getElementById('seoMeta').value = row.meta_description || '';
  document.getElementById('seoKeywords').value = row.keywords || '';
  document.getElementById('seoCanonical').value = row.canonical_url || '';
  document.getElementById('seoRobots').value = row.robots || 'index,follow';
  document.getElementById('seoOgImage').value = row.og_image || '';
  document.getElementById('seoModal').classList.add('open');
}
</script>

<div class="admin-card">
  <h3 style="margin-bottom:16px;">Technical SEO</h3>
  <p>These are generated automatically and always up to date:</p>
  <ul>
    <li><a href="<?= e(BASE_URL) ?>/sitemap.xml" target="_blank">/sitemap.xml</a> — auto-built from halls and published blog posts.</li>
    <li><a href="<?= e(BASE_URL) ?>/robots.txt" target="_blank">/robots.txt</a> — blocks /admin, /ajax, /uploads and links to the sitemap.</li>
    <li>Every public page emits canonical URL, Open Graph tags and a Twitter card automatically.</li>
  </ul>
</div>
<?php require __DIR__ . '/footer.php'; ?>
