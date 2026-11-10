<?php
/**
 * /monthly-rashan/ - Build Your Monthly Rashan (grocery list builder).
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$customer = current_customer();

// All rashan items, grouped by their primary category (in category order)
$products = db_all(product_select_sql() . " WHERE p.status = 'active' AND p.deleted_at IS NULL AND p.is_rashan_item = 1 ORDER BY p.name");
$varRows = $products ? db_all('SELECT v.id, v.product_id, v.name, v.price, v.sale_price, COALESCE(i.quantity, 0) AS stock FROM product_variations v
    LEFT JOIN inventory i ON i.product_id = v.product_id AND i.variation_id = v.id WHERE v.is_active = 1 AND v.product_id IN (' . in_placeholders(array_column($products, 'id')) . ') ORDER BY v.sort_order, v.id',
    array_column($products, 'id')) : [];
$vars = [];
foreach ($varRows as $v) $vars[(int) $v['product_id']][] = $v;

$groups = [];
$data = [];
foreach ($products as $p) {
    $cid = (int) $p['primary_category_id'];
    $groups[$cid][] = $p;
    $opts = [];
    if ($p['has_variations']) {
        foreach ($vars[(int) $p['id']] ?? [] as $v) {
            $opts[] = ['v' => (int) $v['id'], 'name' => $v['name'], 'price' => effective_price($v['price'], $v['sale_price']), 'stock' => $p['track_stock'] ? (int) $v['stock'] : 9999];
        }
        if (!$opts) continue;
    } else {
        $opts[] = ['v' => 0, 'name' => trim(($p['unit_value'] ? rtrim(rtrim((string) $p['unit_value'], '0'), '.') . ' ' : '') . (units_list()[$p['unit']] ?? $p['unit'])),
            'price' => effective_price($p['price'], $p['sale_price']), 'stock' => $p['track_stock'] ? (int) $p['stock'] : 9999];
    }
    $data[(int) $p['id']] = ['id' => (int) $p['id'], 'name' => $p['name'], 'img' => product_thumb_url($p), 'opts' => $opts];
}
$cats = categories_all();
uksort($groups, function ($a, $b) use ($cats) {
    return [($cats[$a]['sort_order'] ?? 999), $a] <=> [($cats[$b]['sort_order'] ?? 999), $b];
});

seo_apply(seo_get('static', 0, 'monthly-rashan'), [
    'title' => setting('rashan_title', 'Build Your Monthly Rashan') . ' - Grocery List with Budget',
    'description' => setting('rashan_intro'),
    'canonical' => abs_url('monthly-rashan/'),
]);
page_set(['breadcrumbs' => [['Home', url('')], ['Monthly Rashan', null]], 'scripts' => ['js/rashan.js'], 'body_class' => 'page-rashan']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="rashan-hero">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <h1><i class="bi bi-basket2-fill me-2"></i><?= e(setting('rashan_title', 'Build Your Monthly Rashan')) ?></h1>
        <p class="mb-3 opacity-90"><?= e(setting('rashan_intro')) ?></p>
        <div class="rashan-steps"><span><b>1</b>Set your budget</span><span><b>2</b>Add items &amp; quantities</span><span><b>3</b>Save your list</span><span><b>4</b>Order for delivery or pickup</span></div>
      </div>
      <div class="col-lg-4">
        <label for="rBudget" class="form-label fw-semibold mb-1">My monthly budget (<?= e(setting('currency_symbol', 'Rs.')) ?>)</label>
        <input type="number" id="rBudget" class="form-control form-control-lg" min="0" step="500" inputmode="numeric" value="<?= (int) setting('rashan_default_budget', 30000) ?>">
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-lg-8">
      <div class="input-group my-2">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="search" id="rSearch" class="form-control form-control-lg" placeholder="Search grocery items… (atta, oil, dal, chai)" aria-label="Search grocery items">
      </div>
      <nav class="rashan-cats" aria-label="Grocery categories">
        <a href="#" class="active" data-cat="all">All</a>
        <a href="#" data-cat="selected"><i class="bi bi-check2-circle me-1"></i>My list (<span class="js-sel-count">0</span>)</a>
        <?php foreach ($groups as $cid => $items): ?><a href="#rg<?= $cid ?>" data-cat="<?= $cid ?>"><?= e($cats[$cid]['name'] ?? 'Other') ?></a><?php endforeach; ?>
      </nav>

      <?php if (!$groups): ?><div class="empty-state mt-3"><i class="bi bi-basket"></i><p>No grocery items are available for the rashan builder yet.</p></div><?php endif; ?>
      <?php foreach ($groups as $cid => $items): ?>
      <section class="rashan-group" id="rg<?= $cid ?>" data-cat="<?= $cid ?>">
        <h2><?= e($cats[$cid]['name'] ?? 'Other') ?><?php if (!empty($cats[$cid]['name_urdu'])): ?> <small class="text-muted fw-normal" lang="ur"><?= e($cats[$cid]['name_urdu']) ?></small><?php endif; ?></h2>
        <?php foreach ($items as $p): if (!isset($data[(int) $p['id']])) continue; $d = $data[(int) $p['id']]; ?>
        <div class="rashan-item" data-id="<?= (int) $p['id'] ?>" data-search="<?= e(mb_strtolower($p['name'] . ' ' . $p['name_urdu'] . ' ' . ($p['brand_name'] ?? '') . ' ' . ($cats[$cid]['name'] ?? ''))) ?>">
          <img src="<?= e($d['img']) ?>" alt="" loading="lazy" width="56" height="56">
          <div class="ri-info">
            <div class="ri-name"><?= e($p['name']) ?></div>
            <?php if ($p['name_urdu']): ?><div class="ri-urdu" lang="ur"><?= e($p['name_urdu']) ?></div><?php endif; ?>
            <div class="ri-price"><span class="js-unit-price"><?= money($d['opts'][0]['price']) ?></span> <small class="text-muted fw-normal">/ <span class="js-unit-name"><?= e($d['opts'][0]['name']) ?></span></small></div>
            <div class="small text-danger js-ri-problem"></div>
          </div>
          <div class="ri-controls">
            <?php if (count($d['opts']) > 1): ?>
            <select class="form-select js-ri-var" aria-label="Choose size for <?= e($p['name']) ?>">
              <?php foreach ($d['opts'] as $o): ?><option value="<?= $o['v'] ?>" <?= $o['stock'] <= 0 ? 'disabled' : '' ?>><?= e($o['name']) ?> – <?= money($o['price']) ?><?= $o['stock'] <= 0 ? ' (sold out)' : '' ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-brand btn-sm ri-add js-ri-add"<?= array_sum(array_column($d['opts'], 'stock')) <= 0 ? ' disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i><?= array_sum(array_column($d['opts'], 'stock')) <= 0 ? 'Sold out' : 'Add' ?></button>
            <div class="qty-box sm js-ri-qty d-none">
              <button type="button" class="js-ri-minus" aria-label="Decrease">−</button>
              <input type="number" min="0" max="999" value="1" aria-label="Quantity" class="js-ri-input">
              <button type="button" class="js-ri-plus" aria-label="Increase">+</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </section>
      <?php endforeach; ?>
      <div class="empty-state d-none js-no-match"><i class="bi bi-search"></i><p>No grocery items match your search.</p></div>
    </div>

    <div class="col-lg-4">
      <aside class="rashan-summary" id="rashanSummary" aria-label="Rashan summary">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h5 fw-bold mb-0">My Rashan List</h2>
          <?php if ($customer): ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button"><i class="bi bi-folder2-open me-1"></i>My lists</button>
            <ul class="dropdown-menu dropdown-menu-end js-lists-menu" style="min-width:280px"><li><span class="dropdown-item-text small text-muted">Loading…</span></li></ul>
          </div>
          <?php endif; ?>
        </div>
        <input type="text" id="rName" class="form-control form-control-sm mb-2" maxlength="120" placeholder="List name e.g. Rashan - <?= date('F Y') ?>" aria-label="List name">
        <div class="budget-meter mb-2">
          <div class="progress" role="progressbar" aria-label="Budget used"><div class="progress-bar bg-success js-budget-bar" style="width:0%"></div></div>
        </div>
        <div class="budget-figures">
          <div><span>Monthly Budget</span><strong class="js-budget">Rs. 0</strong></div>
          <div><span>Selected Rashan</span><strong class="js-total">Rs. 0</strong></div>
          <div class="remaining fw-bold"><span class="js-remaining-label">Remaining</span><span class="js-remaining">Rs. 0</span></div>
          <div class="small text-muted"><span><span class="js-sel-count">0</span> items · <span class="js-qty-total">0</span> units</span></div>
        </div>
        <div class="selected-list my-2 js-selected-list"><p class="small text-muted mb-0">Your selected items will appear here.</p></div>
        <textarea id="rNotes" class="form-control form-control-sm mb-2" rows="2" maxlength="2000" placeholder="Notes (e.g. pack atta separately, deliver on 1st)" aria-label="Notes"></textarea>
        <div class="d-grid gap-2">
          <button type="button" class="btn btn-warning fw-semibold js-r-order"><i class="bi bi-bag-check me-1"></i>Order this Rashan</button>
          <button type="button" class="btn btn-brand js-r-cart"><i class="bi bi-cart-plus me-1"></i>Add all to cart</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-brand flex-fill js-r-save"><i class="bi bi-save me-1"></i>Save</button>
            <button type="button" class="btn btn-outline-secondary flex-fill js-r-saveas" title="Save as a new list"><i class="bi bi-files me-1"></i>Save as new</button>
            <button type="button" class="btn btn-outline-danger js-r-clear" aria-label="Clear list"><i class="bi bi-trash"></i></button>
          </div>
        </div>
        <p class="small text-muted mt-2 mb-0"><?php if (!$customer): ?><a href="<?= url('login/') ?>?next=<?= urlencode(url('monthly-rashan/')) ?>">Log in</a> to save lists for next month. <?php endif; ?>Choose delivery or store pickup at checkout.</p>
      </aside>
    </div>
  </div>
</div>
<div class="rashan-mini">
  <div class="flex-grow-1">
    <div class="d-flex justify-content-between small"><span><span class="js-sel-count">0</span> items · <strong class="js-total">Rs. 0</strong></span><span class="js-remaining-mini"></span></div>
    <div class="progress mt-1"><div class="progress-bar bg-warning js-budget-bar" style="width:0%"></div></div>
  </div>
  <button type="button" class="btn btn-warning btn-sm fw-semibold" onclick="document.getElementById('rashanSummary').scrollIntoView({behavior:'smooth'})">View list</button>
</div>
<script>window.RASHAN = <?= json_encode(['products' => $data, 'loggedIn' => (bool) $customer, 'defaultBudget' => (float) setting('rashan_default_budget', 30000), 'openList' => input_int('list')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php require FS_ROOT . '/partials/footer.php';
