<?php
require __DIR__ . '/../config.php';
wh_require_page_access('pages');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'page') {
    wh_csrf_verify();
    $pageKey = wh_input_post('page_key');
    $data = ['title' => wh_input_post('title'), 'content' => $_POST['content'] ?? '', 'seo_title' => wh_input_post('seo_title'), 'meta_description' => wh_input_post('meta_description')];
    $exists = wh_fetch_one('SELECT id FROM pages WHERE business_id=? AND page_key=?', 'is', [$businessId, $pageKey]);
    if ($exists) {
        wh_update('pages', $data, 'id = ?', [$exists['id']]);
    } else {
        $data['business_id'] = $businessId;
        $data['page_key'] = $pageKey;
        wh_insert('pages', $data);
    }
    wh_flash_set('success', ucfirst($pageKey) . ' page updated.');
    wh_redirect(BASE_URL . '/admin/pages.php?tab=' . $pageKey);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'faq') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $data = ['question' => wh_input_post('question'), 'answer' => wh_input_post('answer'), 'sort_order' => (int) wh_input_post('sort_order', 0), 'status' => wh_input_post('status') === 'inactive' ? 'inactive' : 'active'];
    if ($id) {
        wh_update('faqs', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
    } else {
        $data['business_id'] = $businessId;
        wh_insert('faqs', $data);
    }
    wh_flash_set('success', 'FAQ saved.');
    wh_redirect(BASE_URL . '/admin/pages.php?tab=faq');
}
if (isset($_GET['delete_faq'])) {
    wh_csrf_verify();
    wh_execute('DELETE FROM faqs WHERE id=? AND business_id=?', 'ii', [(int) $_GET['delete_faq'], $businessId]);
    wh_redirect(BASE_URL . '/admin/pages.php?tab=faq');
}

$tab = wh_input_get('tab', 'about');
$pageKeys = ['about' => 'About Us', 'contact' => 'Contact Us', 'privacy' => 'Privacy Policy', 'terms' => 'Terms & Conditions'];
$pages = [];
foreach (array_keys($pageKeys) as $k) {
    $pages[$k] = wh_fetch_one('SELECT * FROM pages WHERE business_id=? AND page_key=?', 'is', [$businessId, $k]) ?: ['title' => $pageKeys[$k], 'content' => '', 'seo_title' => '', 'meta_description' => ''];
}
$faqs = wh_fetch_all('SELECT * FROM faqs WHERE business_id=? ORDER BY sort_order', 'i', [$businessId]);

$pageTitle = 'Pages & FAQ';
$activePage = 'pages';
require __DIR__ . '/header.php';
?>
<div class="admin-card no-print">
  <div class="badge-row" style="margin-bottom:20px;">
    <?php foreach ($pageKeys as $k => $label): ?><a href="?tab=<?= $k ?>" class="chip" style="<?= $tab === $k ? 'background:var(--a-primary);color:#fff;' : '' ?>"><?= e($label) ?></a><?php endforeach; ?>
    <a href="?tab=faq" class="chip" style="<?= $tab === 'faq' ? 'background:var(--a-primary);color:#fff;' : '' ?>">FAQ</a>
  </div>

  <?php if ($tab !== 'faq'): $p = $pages[$tab] ?? null; if (!$p) { $tab = 'about'; $p = $pages['about']; } ?>
  <form method="post">
    <?= wh_csrf_field() ?>
    <input type="hidden" name="form" value="page">
    <input type="hidden" name="page_key" value="<?= e($tab) ?>">
    <div class="form-group"><label>Page Title</label><input type="text" name="title" value="<?= e($p['title']) ?>"></div>
    <div class="form-group"><label>Content (HTML allowed)</label><textarea name="content" rows="10"><?= e($p['content']) ?></textarea></div>
    <div class="form-grid">
      <div class="form-group"><label>SEO Title</label><input type="text" name="seo_title" value="<?= e($p['seo_title'] ?? '') ?>"></div>
      <div class="form-group"><label>Meta Description</label><input type="text" name="meta_description" value="<?= e($p['meta_description'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary">Save Page</button>
  </form>
  <?php else: ?>
  <div class="card-head"><h3>FAQs</h3><button type="button" class="btn btn-primary btn-sm" onclick="openFaqModal()"><i class="fa-solid fa-plus"></i> Add FAQ</button></div>
  <table class="admin-table">
    <thead><tr><th>Question</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($faqs as $f): ?>
      <tr>
        <td><?= e($f['question']) ?></td>
        <td><?= (int) $f['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($f['status']) ?>"><?= e(ucfirst($f['status'])) ?></span></td>
        <td>
          <button type="button" class="btn btn-light btn-sm" onclick='openFaqModal(<?= json_encode($f) ?>)'><i class="fa-solid fa-pen"></i></button>
          <a href="?tab=faq&delete_faq=<?= (int) $f['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this FAQ?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$faqs): ?><tr><td colspan="4">No FAQs yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <div class="modal-overlay" id="faqModal">
    <div class="modal">
      <div class="modal-head"><h3 id="faqModalTitle">Add FAQ</h3><button class="modal-close" data-modal-close>&times;</button></div>
      <form method="post">
        <?= wh_csrf_field() ?>
        <input type="hidden" name="form" value="faq">
        <input type="hidden" name="id" id="faqId">
        <div class="form-group"><label>Question</label><input type="text" name="question" id="faqQuestion" required></div>
        <div class="form-group"><label>Answer</label><textarea name="answer" id="faqAnswer" rows="3" required></textarea></div>
        <div class="form-grid">
          <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="faqOrder" value="0"></div>
          <div class="form-group"><label>Status</label><select name="status" id="faqStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save FAQ</button>
      </form>
    </div>
  </div>
  <script>
  function openFaqModal(f) {
    document.getElementById('faqModalTitle').textContent = f ? 'Edit FAQ' : 'Add FAQ';
    document.getElementById('faqId').value = f ? f.id : '';
    document.getElementById('faqQuestion').value = f ? f.question : '';
    document.getElementById('faqAnswer').value = f ? f.answer : '';
    document.getElementById('faqOrder').value = f ? f.sort_order : 0;
    document.getElementById('faqStatus').value = f ? f.status : 'active';
    document.getElementById('faqModal').classList.add('open');
  }
  </script>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
