<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$type = $_GET['type'] ?? 'homepage';
$id = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;

$entityName = 'Homepage';
if ($type === 'category') $entityName = db_fetch_one("SELECT name FROM categories WHERE id=?", 'i', [$id])['name'] ?? '';
if ($type === 'shop') $entityName = db_fetch_one("SELECT shop_name as name FROM shops WHERE id=?", 'i', [$id])['name'] ?? '';
if ($type === 'product') $entityName = db_fetch_one("SELECT name FROM products WHERE id=?", 'i', [$id])['name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $faqs = [];
    if (!empty($_POST['faq_question'])) {
        foreach ($_POST['faq_question'] as $i => $q) {
            if (trim($q) && trim($_POST['faq_answer'][$i] ?? '')) $faqs[] = ['question' => trim($q), 'answer' => trim($_POST['faq_answer'][$i])];
        }
    }
    $scoreInput = [
        'meta_title' => $_POST['meta_title'], 'meta_description' => $_POST['meta_description'],
        'focus_keyword' => $_POST['focus_keyword'], 'content' => $entityName . ' ' . $_POST['meta_description'] . ' ' . $_POST['entity_description'],
        'canonical_url' => $_POST['canonical_url'], 'og_image' => $_POST['og_image'], 'schema_type' => $_POST['schema_type'],
    ];
    $result = calculate_seo_score($scoreInput);

    save_seo_meta($type, $id, [
        'meta_title' => trim($_POST['meta_title']), 'meta_description' => trim($_POST['meta_description']),
        'focus_keyword' => trim($_POST['focus_keyword']), 'keywords' => trim($_POST['keywords']),
        'canonical_url' => trim($_POST['canonical_url']), 'robots' => $_POST['robots'],
        'og_title' => trim($_POST['og_title']), 'og_description' => trim($_POST['og_description']), 'og_image' => trim($_POST['og_image']),
        'twitter_title' => trim($_POST['twitter_title']), 'twitter_description' => trim($_POST['twitter_description']), 'twitter_image' => trim($_POST['twitter_image']),
        'schema_type' => trim($_POST['schema_type']), 'faq_json' => json_encode($faqs),
        'entity_description' => clean_html($_POST['entity_description'] ?? ''), 'key_facts' => trim($_POST['key_facts'] ?? ''),
        'seo_score' => $result['score'],
    ]);
    flash('success', "SEO saved. Score: {$result['score']}/100");
    redirect(admin_url('seo/form.php?type=' . $type . '&id=' . $id));
}

$seo = get_seo_meta($type, $id) ?: [];
$faqs = !empty($seo['faq_json']) ? json_decode($seo['faq_json'], true) : [];
$scoreResult = calculate_seo_score([
    'meta_title' => $seo['meta_title'] ?? '', 'meta_description' => $seo['meta_description'] ?? '',
    'focus_keyword' => $seo['focus_keyword'] ?? '', 'content' => $entityName . ' ' . ($seo['entity_description'] ?? ''),
    'canonical_url' => $seo['canonical_url'] ?? '', 'og_image' => $seo['og_image'] ?? '', 'schema_type' => $seo['schema_type'] ?? '',
]);

$dashRole = 'admin'; $pageTitle = 'SEO: ' . $entityName; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>SEO — <?= clean($entityName) ?></h1></div>
<div class="dash-two-col">
<form method="post" class="dash-form-card">
  <?= csrf_field() ?>
  <h3>SEO Score: <span id="seo-score-label"><?= $scoreResult['score'] ?></span>/100</h3>
  <div class="seo-score-bar"><div class="seo-score-fill" style="width:<?= $scoreResult['score'] ?>%;background:<?= $scoreResult['score'] >= 70 ? '#3ccf6c' : '#ff7a1a' ?>"></div></div>
  <ul class="seo-tips">
    <?php foreach ($scoreResult['tips'] as [$level, $tip]): ?>
      <li class="tip-<?= $level ?>"><i class="fa-solid fa-<?= $level === 'ok' ? 'check' : ($level === 'warn' ? 'triangle-exclamation' : 'xmark') ?>"></i> <?= clean($tip) ?></li>
    <?php endforeach; ?>
  </ul>
  <h3>Meta Tags</h3>
  <label>Meta Title</label><input type="text" name="meta_title" value="<?= clean($seo['meta_title'] ?? '') ?>">
  <label>Meta Description</label><textarea name="meta_description" rows="2"><?= clean($seo['meta_description'] ?? '') ?></textarea>
  <div class="form-row">
    <div><label>Focus Keyword</label><input type="text" name="focus_keyword" value="<?= clean($seo['focus_keyword'] ?? '') ?>"></div>
    <div><label>Secondary Keywords</label><input type="text" name="keywords" value="<?= clean($seo['keywords'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div><label>Canonical URL</label><input type="text" name="canonical_url" value="<?= clean($seo['canonical_url'] ?? '') ?>"></div>
    <div><label>Robots</label>
      <select name="robots">
        <?php foreach (['index,follow','noindex,follow','index,nofollow','noindex,nofollow'] as $r): ?>
          <option value="<?= $r ?>" <?= ($seo['robots'] ?? 'index,follow') === $r ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <h3>Open Graph / Twitter</h3>
  <div class="form-row">
    <div><label>OG Title</label><input type="text" name="og_title" value="<?= clean($seo['og_title'] ?? '') ?>"></div>
    <div><label>OG Image URL</label><input type="text" name="og_image" value="<?= clean($seo['og_image'] ?? '') ?>"></div>
  </div>
  <label>OG Description</label><textarea name="og_description" rows="2"><?= clean($seo['og_description'] ?? '') ?></textarea>
  <div class="form-row">
    <div><label>Twitter Title</label><input type="text" name="twitter_title" value="<?= clean($seo['twitter_title'] ?? '') ?>"></div>
    <div><label>Twitter Image URL</label><input type="text" name="twitter_image" value="<?= clean($seo['twitter_image'] ?? '') ?>"></div>
  </div>
  <label>Twitter Description</label><textarea name="twitter_description" rows="2"><?= clean($seo['twitter_description'] ?? '') ?></textarea>
  <label>Schema Type</label>
  <select name="schema_type">
    <?php foreach (['WebSite','Product','LocalBusiness','Organization','BreadcrumbList'] as $s): ?>
      <option value="<?= $s ?>" <?= ($seo['schema_type'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>

  <h3>GEO — Generative Engine Optimization</h3>
  <label>Entity / Business Description</label>
  <textarea name="entity_description" rows="3"><?= clean($seo['entity_description'] ?? '') ?></textarea>
  <label>Key Facts (one per line)</label>
  <textarea name="key_facts" rows="3" placeholder="Founded in 2020&#10;Ships nationwide&#10;500+ five-star reviews"><?= clean($seo['key_facts'] ?? '') ?></textarea>

  <h3>AEO — FAQ (Answer Engine Optimization)</h3>
  <div id="faq-rows">
    <?php $faqs = $faqs ?: [['question' => '', 'answer' => '']]; foreach ($faqs as $f): ?>
      <div class="form-row faq-row">
        <input type="text" name="faq_question[]" placeholder="Question" value="<?= clean($f['question']) ?>">
        <input type="text" name="faq_answer[]" placeholder="Answer" value="<?= clean($f['answer']) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-sm btn-outline" onclick="addFaqRow()">+ Add FAQ</button>
  <br><br>
  <button type="submit" class="btn btn-primary">Save SEO</button>
</form>
</div>
<script>
function addFaqRow() {
  const div = document.createElement('div'); div.className = 'form-row faq-row';
  div.innerHTML = '<input type="text" name="faq_question[]" placeholder="Question"><input type="text" name="faq_answer[]" placeholder="Answer">';
  document.getElementById('faq-rows').appendChild(div);
}
</script>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
