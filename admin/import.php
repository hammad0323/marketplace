<?php
/**
 * Bulk product import (CSV or Excel .xlsx) with validation preview,
 * bulk price/stock updates, and product export.
 */
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/product-lib.php';
admin_require('import');

const IMPORT_COLUMNS = ['sku', 'name', 'name_urdu', 'category', 'brand', 'price', 'sale_price', 'cost_price', 'stock', 'unit', 'unit_value', 'barcode', 'isbn',
    'short_description', 'description', 'keywords', 'tags', 'low_stock_threshold', 'is_featured', 'is_rashan_item', 'status', 'image_url'];

/** Read an .xlsx first sheet into rows (no external library). */
function xlsx_rows(string $file): array
{
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new RuntimeException('Could not open the Excel file.');
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string) $si->t;
            else foreach ($si->r as $r) $t .= (string) $r->t;
            $shared[] = $t;
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) throw new RuntimeException('The Excel file has no first worksheet.');
    $sx = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = 0;
            foreach (str_split(preg_replace('/\d+/', '', $ref)) as $ch) $col = $col * 26 + (ord($ch) - 64);
            $type = (string) $c['t'];
            $val = $type === 's' ? ($shared[(int) $c->v] ?? '') : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
            $r[$col - 1] = $val;
        }
        if ($r) {
            $max = max(array_keys($r));
            $full = [];
            for ($i = 0; $i <= $max; $i++) $full[] = $r[$i] ?? '';
            $rows[] = $full;
        }
    }
    return $rows;
}

function import_read(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed.');
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('File is larger than 10 MB.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'xlsx') return xlsx_rows($file['tmp_name']);
    if (!in_array($ext, ['csv', 'txt'], true)) throw new RuntimeException('Please upload a .csv or .xlsx file.');
    $rows = [];
    $fh = fopen($file['tmp_name'], 'r');
    $first = fgets($fh);
    $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $delim)) !== false) $rows[] = $r;
    fclose($fh);
    if ($rows) $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]);
    return $rows;
}

/** Map rows to associative arrays using the header row. */
function import_assoc(array $rows): array
{
    if (!$rows) return [];
    $head = array_map(function ($h) { return strtolower(trim(str_replace([' ', '-'], '_', (string) $h))); }, array_shift($rows));
    $out = [];
    foreach ($rows as $i => $r) {
        if (!array_filter($r, function ($x) { return trim((string) $x) !== ''; })) continue;
        $a = [];
        foreach ($head as $k => $h) if ($h !== '') $a[$h] = trim((string) ($r[$k] ?? ''));
        $a['_line'] = $i + 2;
        $out[] = $a;
    }
    return $out;
}

/** Resolve a category by "Books > School Books > Class 5" path or name. Creates missing ones if allowed. */
function import_category(string $path, bool $create): ?int
{
    if ($path === '') return null;
    $parts = array_map('trim', preg_split('/\s*[>\/|]\s*/', $path));
    $parent = null;
    foreach ($parts as $name) {
        if ($name === '') continue;
        $found = null;
        foreach (category_children($parent, false) as $c) if (mb_strtolower($c['name']) === mb_strtolower($name)) { $found = (int) $c['id']; break; }
        if (!$found && count($parts) === 1) {
            foreach (categories_all() as $c) if (mb_strtolower($c['name']) === mb_strtolower($name)) { $found = (int) $c['id']; break; }
        }
        if (!$found) {
            if (!$create) return null;
            $found = db_insert('INSERT INTO categories (parent_id, name, slug) VALUES (?, ?, ?)', [$parent, mb_substr($name, 0, 150), unique_slug('categories', $name)]);
            categories_all(true);
        }
        $parent = $found;
    }
    return $parent;
}

function import_validate(array $r, string $mode): array
{
    $errs = [];
    if ($mode === 'update') {
        if (($r['sku'] ?? '') === '') $errs[] = 'SKU is required';
        elseif (!db_val('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL', [$r['sku']]) && !db_val('SELECT id FROM product_variations WHERE sku = ?', [$r['sku']])) $errs[] = 'SKU not found';
        foreach (['price', 'sale_price', 'cost_price', 'stock'] as $n) if (($r[$n] ?? '') !== '' && !is_numeric(str_replace(',', '', $r[$n]))) $errs[] = "$n must be a number";
        return $errs;
    }
    if (($r['name'] ?? '') === '') $errs[] = 'name is required';
    if (($r['price'] ?? '') === '' || !is_numeric(str_replace(',', '', $r['price']))) $errs[] = 'price must be a number';
    foreach (['sale_price', 'cost_price', 'stock', 'low_stock_threshold'] as $n) if (($r[$n] ?? '') !== '' && !is_numeric(str_replace(',', '', $r[$n]))) $errs[] = "$n must be a number";
    if (($r['sale_price'] ?? '') !== '' && (float) $r['sale_price'] >= (float) ($r['price'] ?? 0)) $errs[] = 'sale_price must be lower than price';
    if (($r['unit'] ?? '') !== '' && !array_key_exists(strtolower($r['unit']), units_list())) $errs[] = 'unknown unit "' . $r['unit'] . '"';
    if (($r['category'] ?? '') === '') $errs[] = 'category is required';
    return $errs;
}

$boolv = function ($v) { return in_array(strtolower(trim((string) $v)), ['1', 'yes', 'y', 'true'], true) ? 1 : 0; };

// ---------- Downloads ----------
if (input('download') === 'template') {
    csv_download('fahim-products-template.csv', IMPORT_COLUMNS, [
        ['FS-9001', 'Mathematics Text Book Class 5', '', 'Books > School Books > Class 5', 'Sindh Text Book Board', '300', '', '240', '25', 'book', '', '', '9789690000000', 'Latest syllabus', '', 'maths math', 'class 5, maths', '5', '0', '0', 'active', ''],
        ['FS-9002', 'Basmati Rice 5kg', 'باسمتی چاول', 'Groceries > Flour & Rice', 'Guard', '2500', '2400', '2100', '30', 'kg', '5', '8961234567890', '', '', '', 'chawal rice', 'rice', '5', '1', '1', 'active', ''],
    ]);
}
if (input('download') === 'export') {
    $rows = db_all("SELECT p.*, b.name AS brand FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.deleted_at IS NULL ORDER BY p.id");
    $data = [];
    foreach ($rows as $p) {
        $path = $p['primary_category_id'] ? implode(' > ', array_column(category_path((int) $p['primary_category_id']), 'name')) : '';
        $img = db_val('SELECT image FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order LIMIT 1', [$p['id']]);
        $data[] = [$p['sku'], $p['name'], $p['name_urdu'], $path, $p['brand'], $p['price'], $p['sale_price'], $p['cost_price'], $p['has_variations'] ? '' : stock_get((int) $p['id'], 0),
            $p['unit'], $p['unit_value'], $p['barcode'], $p['isbn'], $p['short_description'], $p['description'], $p['keywords'], implode(', ', db_col('SELECT tag FROM product_tags WHERE product_id = ?', [$p['id']])),
            $p['low_stock_threshold'], $p['is_featured'], $p['is_rashan_item'], $p['status'], $img ? abs_url(upload_url($img)) : ''];
        foreach (product_variations((int) $p['id'], false) as $v) {
            $data[] = [$v['sku'], $p['name'] . ' - ' . $v['name'], '', $path, $p['brand'], $v['price'], $v['sale_price'], $v['cost_price'], $v['stock'], $p['unit'], '', $v['barcode'], '', '(variation)', '', '', '', '', '', '', $v['is_active'] ? 'active' : 'inactive', ''];
        }
    }
    csv_download('fahim-products-' . date('Y-m-d') . '.csv', IMPORT_COLUMNS, $data);
}

// ---------- Import ----------
$preview = null;
$result = null;
if (is_post()) {
    csrf_check();
    $mode = input('mode') === 'update' ? 'update' : 'create';
    $createCats = input('create_categories') === '1';
    if (input('step') === 'preview') {
        try {
            $files = upload_files_list('file');
            if (!$files) throw new RuntimeException('Please choose a file.');
            $rows = import_assoc(import_read($files[0]));
            if (!$rows) throw new RuntimeException('The file has no data rows.');
            if (count($rows) > 5000) throw new RuntimeException('Please import at most 5000 rows at a time.');
            $seen = [];
            foreach ($rows as &$r) {
                $r['_errors'] = import_validate($r, $mode);
                $sku = $r['sku'] ?? '';
                if ($sku !== '') {
                    if (isset($seen[$sku])) $r['_errors'][] = 'duplicate SKU in file (line ' . $seen[$sku] . ')';
                    $seen[$sku] = $r['_line'];
                    $r['_exists'] = (bool) db_val('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL', [$sku]);
                }
                if ($mode === 'create' && !$createCats && ($r['category'] ?? '') !== '' && !import_category($r['category'], false)) $r['_errors'][] = 'category not found (enable "create missing categories")';
                if ($mode === 'create' && ($r['barcode'] ?? '') !== '' && empty($r['_exists']) && product_code_conflict('', $r['barcode'])) $r['_errors'][] = 'barcode already used';
            }
            unset($r);
            $token = bin2hex(random_bytes(8));
            $_SESSION['import'][$token] = ['rows' => $rows, 'mode' => $mode, 'create_cats' => $createCats, 'update_existing' => input('update_existing') === '1'];
            $preview = ['token' => $token, 'rows' => $rows, 'mode' => $mode];
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect('admin/import.php');
        }
    } elseif (input('step') === 'import') {
        $job = $_SESSION['import'][input('token')] ?? null;
        unset($_SESSION['import']);
        if (!$job) { flash('danger', 'Import session expired. Please upload again.'); redirect('admin/import.php'); }
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $num = function ($v) { return $v === '' || $v === null ? null : (float) str_replace(',', '', (string) $v); };
        foreach ($job['rows'] as $r) {
            if ($r['_errors']) { $result['skipped']++; continue; }
            try {
                if ($job['mode'] === 'update') {
                    $pid = (int) db_val('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL', [$r['sku']]);
                    $var = $pid ? null : db_row('SELECT id, product_id FROM product_variations WHERE sku = ?', [$r['sku']]);
                    $table = $pid ? 'products' : 'product_variations';
                    $tid = $pid ?: (int) $var['id'];
                    $sets = [];
                    foreach (['price', 'sale_price', 'cost_price'] as $col) if (($r[$col] ?? '') !== '') $sets[$col] = $num($r[$col]) ?: null;
                    if (isset($sets['price'])) $sets['price'] = (float) $sets['price'];
                    if ($sets) db_update_row($table, $sets, 'id = ?', [$tid]);
                    if (($r['stock'] ?? '') !== '') stock_set($pid ?: (int) $var['product_id'], $pid ? 0 : $tid, (int) $num($r['stock']), 'Bulk import update', 'import');
                    $result['updated']++;
                    continue;
                }
                $existing = ($r['sku'] ?? '') !== '' ? (int) db_val('SELECT id FROM products WHERE sku = ? AND deleted_at IS NULL', [$r['sku']]) : 0;
                if ($existing && !$job['update_existing']) { $result['skipped']++; continue; }
                $cat = import_category($r['category'] ?? '', $job['create_cats']);
                $brand = null;
                if (($r['brand'] ?? '') !== '') {
                    $brand = (int) db_val('SELECT id FROM brands WHERE name = ? AND deleted_at IS NULL', [$r['brand']]) ?: db_insert('INSERT INTO brands (name, slug) VALUES (?, ?)', [mb_substr($r['brand'], 0, 120), unique_slug('brands', $r['brand'])]);
                }
                $d = ['name' => $r['name'], 'name_urdu' => $r['name_urdu'] ?? '', 'sku' => $r['sku'] ?? '', 'barcode' => $r['barcode'] ?? '', 'isbn' => $r['isbn'] ?? '',
                    'brand_id' => $brand, 'category_ids' => $cat ? [$cat] : [], 'primary_category_id' => $cat, 'price' => $num($r['price']), 'sale_price' => $num($r['sale_price'] ?? ''),
                    'cost_price' => $num($r['cost_price'] ?? '') ?? 0, 'unit' => strtolower($r['unit'] ?? '') ?: 'piece', 'unit_value' => $r['unit_value'] ?? '',
                    'short_description' => $r['short_description'] ?? '', 'description' => $r['description'] ?? '', 'keywords' => $r['keywords'] ?? '', 'tags' => $r['tags'] ?? '',
                    'low_stock_threshold' => ($r['low_stock_threshold'] ?? '') !== '' ? (int) $r['low_stock_threshold'] : 5, 'track_stock' => 1,
                    'is_featured' => $boolv($r['is_featured'] ?? 0), 'is_rashan_item' => $boolv($r['is_rashan_item'] ?? 0),
                    'status' => in_array(strtolower($r['status'] ?? ''), ['active', 'inactive'], true) ? strtolower($r['status']) : 'active'];
                if ($existing) {
                    $keep = db_row('SELECT slug, is_bestseller, min_order_qty, max_order_qty, weight_grams FROM products WHERE id = ?', [$existing]);
                    $d = array_merge($keep, $d);
                }
                $pid = product_save_core($existing, $d);
                if (is_string($pid)) throw new RuntimeException($pid);
                if (($r['stock'] ?? '') !== '') stock_set($pid, 0, (int) $num($r['stock']), $existing ? 'Bulk import update' : 'Opening stock (import)', 'import');
                else stock_ensure_row($pid, 0);
                // Optional remote image (http/https only)
                $imgHost = parse_url($r['image_url'] ?? '', PHP_URL_HOST);
                $imgIp = $imgHost ? gethostbyname($imgHost) : '';
                $publicHost = $imgIp && filter_var($imgIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($publicHost && preg_match('#^https?://#i', $r['image_url']) && !db_val('SELECT id FROM product_images WHERE product_id = ?', [$pid])) {
                    $tmp = tempnam(sys_get_temp_dir(), 'fsimg');
                    $ctx = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 0]]);
                    $bin = @file_get_contents($r['image_url'], false, $ctx, 0, MAX_UPLOAD_MB * 1024 * 1024 + 1);
                    if ($bin !== false && strlen($bin) <= MAX_UPLOAD_MB * 1024 * 1024) {
                        file_put_contents($tmp, $bin);
                        $up = upload_image(['name' => 'remote', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bin)], 'products', 1200, 400, true);
                        if ($up['ok']) db_insert('INSERT INTO product_images (product_id, image, thumb, alt_text, is_primary) VALUES (?, ?, ?, ?, 1)', [$pid, $up['path'], $up['thumb'], $r['name']]);
                    }
                    @unlink($tmp);
                }
                product_rebuild_search($pid);
                $existing ? $result['updated']++ : $result['created']++;
            } catch (Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = 'Line ' . $r['_line'] . ': ' . $e->getMessage();
            }
        }
        catalog_changed();
    }
}

admin_header('Bulk Import / Export', 'import');
?>
<?php if ($result): ?>
<div class="alert alert-success"><strong>Import finished.</strong> Created: <?= $result['created'] ?> · Updated: <?= $result['updated'] ?> · Skipped: <?= $result['skipped'] ?></div>
<?php if ($result['errors']): ?><div class="alert alert-warning small"><?= implode('<br>', array_map('e', array_slice($result['errors'], 0, 50))) ?></div><?php endif; ?>
<?php endif; ?>

<?php if ($preview): $bad = count(array_filter($preview['rows'], function ($r) { return $r['_errors']; })); ?>
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Preview: <?= count($preview['rows']) ?> rows · <span class="text-success"><?= count($preview['rows']) - $bad ?> valid</span> · <span class="text-danger"><?= $bad ?> with errors (will be skipped)</span></span>
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="step" value="import"><input type="hidden" name="token" value="<?= e($preview['token']) ?>">
      <a href="<?= aurl('import.php') ?>" class="btn btn-light btn-sm">Cancel</a><button class="btn btn-brand btn-sm"<?= count($preview['rows']) === $bad ? ' disabled' : '' ?>>Import <?= count($preview['rows']) - $bad ?> valid rows</button></form>
  </div>
  <div class="table-responsive" style="max-height:60vh"><table class="table table-sm small mb-0">
    <thead><tr><th>Line</th><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Result</th></tr></thead><tbody>
    <?php foreach ($preview['rows'] as $r): ?>
    <tr class="<?= $r['_errors'] ? 'table-danger' : '' ?>"><td><?= (int) $r['_line'] ?></td><td><?= e($r['sku'] ?? '') ?></td><td><?= e($r['name'] ?? '') ?></td><td><?= e($r['category'] ?? '') ?></td><td><?= e($r['price'] ?? '') ?></td><td><?= e($r['stock'] ?? '') ?></td>
      <td><?= $r['_errors'] ? '<span class="text-danger">' . e(implode('; ', $r['_errors'])) . '</span>' : (!empty($r['_exists']) ? '<span class="text-primary">' . ($preview['mode'] === 'update' ? 'will update' : 'existing SKU') . '</span>' : '<span class="text-success">' . ($preview['mode'] === 'update' ? 'will update' : 'new') . '</span>') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" enctype="multipart/form-data" class="card">
      <div class="card-header">Import products</div>
      <div class="card-body">
        <?= csrf_field() ?><input type="hidden" name="step" value="preview">
        <div class="mb-3">
          <label class="form-label">What do you want to do?</label>
          <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="create" id="mC" checked><label class="form-check-label" for="mC"><strong>Add new products</strong> (full product rows)</label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="update" id="mU"><label class="form-check-label" for="mU"><strong>Bulk update prices &amp; stock</strong> (only <code>sku</code> + any of <code>price, sale_price, cost_price, stock</code>)</label></div>
        </div>
        <div class="mb-3"><label class="form-label" for="impFile">CSV or Excel (.xlsx) file</label><input type="file" name="file" id="impFile" class="form-control" accept=".csv,.xlsx,text/csv" required></div>
        <?= af_check('create_categories', 'Create missing categories automatically (use "Books > School Books > Class 5")', true) ?>
        <?= af_check('update_existing', 'If a SKU already exists, update that product instead of skipping', false) ?>
        <button class="btn btn-brand mt-2"><i class="bi bi-eye me-1"></i>Validate &amp; preview</button>
      </div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">Templates &amp; export</div><div class="card-body">
      <a href="?download=template" class="btn btn-outline-brand w-100 mb-2"><i class="bi bi-download me-1"></i>Download CSV template</a>
      <a href="?download=export" class="btn btn-outline-secondary w-100"><i class="bi bi-file-earmark-arrow-down me-1"></i>Export all products (CSV)</a>
      <p class="small text-muted mt-3 mb-0">Tip: edit the export in Excel and re-import it with <em>"update existing"</em> ticked to bulk edit products. Excel files must have the header row in the first sheet.</p>
    </div></div>
    <div class="card"><div class="card-header">Columns</div><div class="card-body small">
      <p><code><?= implode('</code>, <code>', IMPORT_COLUMNS) ?></code></p>
      <ul class="mb-0"><li><strong>Required:</strong> name, price, category</li><li><strong>unit:</strong> <?= e(implode(', ', array_keys(units_list()))) ?></li><li><strong>is_featured / is_rashan_item:</strong> 1 or 0</li><li><strong>image_url:</strong> optional public image link</li><li>SKU is generated when empty. Duplicate SKUs/barcodes are reported.</li></ul>
    </div></div>
  </div>
</div>
<?php admin_footer();
