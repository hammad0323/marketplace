<?php
/**
 * Generic create / edit / delete / reorder screen for every content type
 * defined in entities.php — e.g. manage.php?e=packages
 */
require __DIR__ . '/inc.php';
require_admin();

$key = $_GET['e'] ?? '';
$ents = entities();
if (!isset($ents[$key])) {
    redirect('admin/');
}
$ent = $ents[$key];
$table = $key;
$fields = $ent['fields'];
$titleField = $ent['title'];
$hasSort = isset($fields['sort_order']);
$orderBy = $ent['order'] ?? ($hasSort ? 'sort_order, id' : 'id DESC');
$action = $_GET['action'] ?? 'list';
$self = 'manage.php?e=' . $key;

require_csrf();

/* ---------------- POST actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';

    if ($do === 'reorder' && $hasSort) {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $offset = (int) ($_POST['offset'] ?? 0);
        foreach ($ids as $i => $id) {
            q("UPDATE `$table` SET sort_order = ? WHERE id = ?", [$offset + $i + 1, $id]);
        }
        header('Content-Type: application/json');
        exit('{"ok":true}');
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'delete' && $id) {
        q("DELETE FROM `$table` WHERE id = ?", [$id]);
        flash('success', $ent['singular'] . ' deleted.');
        redirect('admin/' . $self);
    }

    if ($do === 'toggle' && $id && isset($fields['is_active'])) {
        q("UPDATE `$table` SET is_active = 1 - is_active WHERE id = ?", [$id]);
        redirect('admin/' . $self . (isset($_POST['back']) ? '&' . $_POST['back'] : ''));
    }

    if ($do === 'duplicate' && $id) {
        $src = row("SELECT * FROM `$table` WHERE id = ?", [$id]);
        if ($src) {
            $cols = array_keys($fields);
            $vals = [];
            foreach ($cols as $c) {
                $vals[] = $src[$c] ?? null;
            }
            $ti = array_search($titleField, $cols, true);
            $vals[$ti] .= ' (copy)';
            if (isset($ent['slug'])) {
                $vals[array_search('slug', $cols, true)] = unique_slug($table, slugify($vals[$ti]), 0);
            }
            if (isset($fields['is_active'])) {
                $vals[array_search('is_active', $cols, true)] = 0;
            }
            q("INSERT INTO `$table` (`" . implode('`,`', $cols) . '`) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')', $vals);
            flash('success', 'Duplicated as a hidden draft — edit it and make it visible when ready.');
            redirect('admin/' . $self . '&action=edit&id=' . db()->lastInsertId());
        }
    }

    if ($do === 'save') {
        $existing = $id ? row("SELECT * FROM `$table` WHERE id = ?", [$id]) : null;
        $data = [];
        $errors = [];
        foreach ($fields as $name => $f) {
            $raw = $_POST[$name] ?? null;
            switch ($f['type']) {
                case 'checkbox':
                    $data[$name] = isset($_POST[$name]) ? 1 : 0;
                    break;
                case 'number':
                    $v = trim((string) $raw);
                    $data[$name] = $v === '' ? (int) ($f['default'] ?? 0) : (int) $v;
                    if (isset($f['min'])) { $data[$name] = max($f['min'], $data[$name]); }
                    if (isset($f['max'])) { $data[$name] = min($f['max'], $data[$name]); }
                    break;
                case 'price':
                    $v = trim(str_replace(',', '', (string) $raw));
                    $data[$name] = $v === '' ? ($f['default'] ?? null) : round((float) $v, 2);
                    break;
                case 'select':
                    $data[$name] = ($raw === '' || $raw === null) ? null : (int) $raw;
                    break;
                case 'date':
                    $data[$name] = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $raw) ? $raw : null;
                    break;
                case 'image':
                    try {
                        $up = upload_image($name);
                    } catch (RuntimeException $ex) {
                        $errors[] = $f['label'] . ': ' . $ex->getMessage();
                        $up = null;
                    }
                    if ($up) {
                        $data[$name] = $up;
                    } elseif (!empty($_POST[$name . '__remove'])) {
                        $data[$name] = null;
                    } else {
                        $data[$name] = $existing[$name] ?? null;
                    }
                    break;
                default:
                    $data[$name] = trim((string) $raw);
                    if ($data[$name] === '' && $name !== 'slug') {
                        $data[$name] = null;
                    }
            }
            if (!empty($f['required']) && ($data[$name] === null || $data[$name] === '')) {
                $errors[] = $f['label'] . ' is required.';
            }
        }
        if (isset($ent['slug'])) {
            $base = slugify($data['slug'] !== '' && $data['slug'] !== null ? $data['slug'] : (string) $data[$ent['slug']]);
            $data['slug'] = unique_slug($table, $base, $id);
        }

        if ($errors) {
            $_SESSION['form_old'] = $data;
            foreach ($errors as $er) {
                flash('error', $er);
            }
            redirect('admin/' . $self . '&action=edit' . ($id ? '&id=' . $id : ''));
        }

        $cols = array_keys($data);
        if ($id) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
            q("UPDATE `$table` SET $set WHERE id = ?", array_merge(array_values($data), [$id]));
        } else {
            q("INSERT INTO `$table` (`" . implode('`,`', $cols) . '`) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')', array_values($data));
            $id = (int) db()->lastInsertId();
        }
        flash('success', $ent['singular'] . ' saved.');
        redirect('admin/' . $self . (isset($_POST['stay']) ? '&action=edit&id=' . $id : ''));
    }
}

function unique_slug(string $table, string $base, int $id): string
{
    $slug = $base;
    $n = 2;
    while (val("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $id])) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/* ---------------- Rendering helpers ---------------- */
function render_field(string $name, array $f, $value): void
{
    $id = 'f_' . $name;
    $req = !empty($f['required']) ? ' required' : '';
    echo '<div class="field field-' . e($f['type']) . '">';
    if ($f['type'] !== 'checkbox') {
        echo '<label for="' . e($id) . '">' . e($f['label']) . (!empty($f['required']) ? ' <em>*</em>' : '') . '</label>';
    }
    switch ($f['type']) {
        case 'textarea':
            echo '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="4"' . $req . '>' . e($value) . '</textarea>';
            break;
        case 'lines':
            echo '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="8" class="mono"' . $req . '>' . e($value) . '</textarea>';
            break;
        case 'rich':
            echo '<div class="rte" data-rte>'
                . '<div class="rte-bar">'
                . '<button type="button" data-cmd="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>'
                . '<button type="button" data-cmd="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>'
                . '<button type="button" data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>'
                . '<button type="button" data-block="h2" title="Heading">H2</button>'
                . '<button type="button" data-block="h3" title="Sub-heading">H3</button>'
                . '<button type="button" data-block="p" title="Paragraph"><i class="fa-solid fa-paragraph"></i></button>'
                . '<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>'
                . '<button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>'
                . '<button type="button" data-block="blockquote" title="Quote"><i class="fa-solid fa-quote-left"></i></button>'
                . '<button type="button" data-link title="Link"><i class="fa-solid fa-link"></i></button>'
                . '<button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>'
                . '<button type="button" data-source title="Edit HTML" class="right"><i class="fa-solid fa-code"></i> HTML</button>'
                . '</div>'
                . '<div class="rte-area" contenteditable="true">' . rich((string) $value) . '</div>'
                . '<textarea name="' . e($name) . '" id="' . e($id) . '" class="rte-source mono" rows="12">' . e($value) . '</textarea>'
                . '</div>';
            break;
        case 'image':
            echo '<div class="img-field">';
            echo '<div class="img-preview">' . ($value ? '<img src="' . e(media($value)) . '" alt="">' : '<i class="fa-regular fa-image"></i>') . '</div>';
            echo '<div><input type="file" id="' . e($id) . '" name="' . e($name) . '" accept="image/*">';
            if ($value) {
                echo '<label class="check small"><input type="checkbox" name="' . e($name) . '__remove" value="1"> Remove current image</label>';
            }
            echo '</div></div>';
            break;
        case 'checkbox':
            echo '<label class="switch"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '><span></span> ' . e($f['label']) . '</label>';
            break;
        case 'select':
            $opts = entity_options($f['source']);
            echo '<select id="' . e($id) . '" name="' . e($name) . '"' . $req . '><option value="">— None —</option>';
            foreach ($opts as $k => $l) {
                echo '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($l) . '</option>';
            }
            echo '</select>';
            break;
        case 'icon':
            echo '<div class="icon-field"><span class="icon-preview"><i class="' . e($value) . '"></i></span>'
                . '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="fa-solid fa-star" data-icon-input>'
                . '<button type="button" class="btn btn-light" data-icon-toggle>Choose</button></div>'
                . '<div class="icon-grid" hidden>';
            foreach (icon_choices() as $ic) {
                echo '<button type="button" data-icon="' . e($ic) . '" title="' . e($ic) . '"><i class="' . e($ic) . '"></i></button>';
            }
            echo '</div><small class="help">Any <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener">Font Awesome 6 free icon</a> class works.</small>';
            break;
        case 'number':
            echo '<input type="number" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"'
                . (isset($f['min']) ? ' min="' . (int) $f['min'] . '"' : '') . (isset($f['max']) ? ' max="' . (int) $f['max'] . '"' : '') . $req . '>';
            break;
        case 'price':
            echo '<div class="input-prefix"><span>' . e(setting('currency_symbol', '$')) . '</span><input type="number" step="0.01" min="0" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value === null ? '' : rtrim(rtrim((string) $value, '0'), '.')) . '"' . $req . '></div>';
            break;
        case 'date':
            echo '<input type="date" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '">';
            break;
        case 'url':
            echo '<input type="url" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '" placeholder="https://">';
            break;
        case 'email':
            echo '<input type="email" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '">';
            break;
        default:
            echo '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . $req . '>';
    }
    if (!empty($f['help']) && $f['type'] !== 'icon') {
        echo '<small class="help">' . e($f['help']) . '</small>';
    }
    echo '</div>';
}

function cell(array $f, $value, array $row): string
{
    switch ($f['type']) {
        case 'icon':
            return '<span class="cell-icon"><i class="' . e($value) . '"></i></span>';
        case 'image':
            return $value ? '<img class="cell-img" src="' . e(media($value)) . '" alt="">' : '<span class="cell-img none"><i class="fa-regular fa-image"></i></span>';
        case 'checkbox':
            return $value ? '<span class="badge badge-won"><i class="fa-solid fa-star"></i> Yes</span>' : '<span class="muted">—</span>';
        case 'select':
            static $cache = [];
            $cache[$f['source']] ??= entity_options($f['source']);
            return e($cache[$f['source']][$value] ?? '—');
        case 'price':
            if ($value === null) { return '<span class="muted">—</span>'; }
            $out = (float) $value > 0 ? e(money($value)) : 'Custom';
            return '<strong>' . $out . '</strong>' . (!empty($row['price_suffix']) ? ' <small class="muted">' . e($row['price_suffix']) . '</small>' : '');
        case 'date':
            return e(nice_date($value));
        default:
            return e(excerpt((string) $value, 80));
    }
}

/* ---------------- Edit form ---------------- */
if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    $item = $id ? row("SELECT * FROM `$table` WHERE id = ?", [$id]) : null;
    if ($id && !$item) {
        redirect('admin/' . $self);
    }
    if (!empty($_SESSION['form_old'])) {
        $item = array_merge($item ?? [], $_SESSION['form_old']);
        unset($_SESSION['form_old']);
    }
    if (!$id && !empty($_GET['category_id']) && isset($fields['category_id'])) {
        $item['category_id'] = (int) $_GET['category_id'];
    }
    admin_header(($id ? 'Edit ' : 'New ') . $ent['singular'], $key);

    $main = $side = [];
    foreach ($fields as $name => $f) {
        if (in_array($f['type'], ['checkbox', 'image', 'select', 'icon', 'date'], true) || in_array($name, ['sort_order', 'slug', 'price', 'old_price', 'price_suffix', 'delivery_time', 'badge', 'cta_text', 'rating', 'value', 'suffix'], true)) {
            $side[$name] = $f;
        } else {
            $main[$name] = $f;
        }
    }
    $publicUrl = null;
    if ($item && isset($item['slug'])) {
        $publicUrl = ['services' => 'service.php', 'portfolio' => 'project.php', 'posts' => 'post.php', 'pages' => 'page.php'][$key] ?? null;
        $publicUrl = $publicUrl ? url($publicUrl . '?slug=' . $item['slug']) : null;
    }
    ?>
    <div class="page-actions">
      <a href="<?= e($self) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Back to <?= e($ent['label']) ?></a>
      <?php if ($publicUrl): ?><a href="<?= e($publicUrl) ?>" class="btn btn-light" target="_blank"><i class="fa-solid fa-eye"></i> View on site</a><?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="edit-form" action="<?= e($self) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="edit-grid">
        <div class="card">
          <?php foreach ($main as $name => $f) { render_field($name, $f, $item[$name] ?? ($f['default'] ?? '')); } ?>
        </div>
        <div>
          <div class="card sticky">
            <?php foreach ($side as $name => $f) { render_field($name, $f, $item[$name] ?? ($f['default'] ?? '')); } ?>
            <div class="form-buttons">
              <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button>
              <button class="btn btn-light" type="submit" name="stay" value="1">Save &amp; keep editing</button>
            </div>
          </div>
        </div>
      </div>
    </form>
    <?php
    admin_footer();
    exit;
}

/* ---------------- List ---------------- */
$search = trim((string) ($_GET['q'] ?? ''));
$filterField = $ent['filter'] ?? null;
$filterVal = $filterField ? ($_GET['f'] ?? '') : '';
$where = [];
$params = [];
if ($search !== '') {
    $where[] = "`$titleField` LIKE ?";
    $params[] = '%' . $search . '%';
}
if ($filterField && $filterVal !== '') {
    $where[] = "`$filterField` = ?";
    $params[] = (int) $filterVal;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$perPage = 30;
$pageNo = max(1, (int) ($_GET['p'] ?? 1));
$total = (int) val("SELECT COUNT(*) FROM `$table` $whereSql", $params);
$pages = max(1, (int) ceil($total / $perPage));
$offset = ($pageNo - 1) * $perPage;
$items = rows("SELECT * FROM `$table` $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);
$canDrag = $hasSort && $search === '' && ($ent['drag'] ?? !isset($ent['order']));
$backQs = http_build_query(array_filter(['q' => $search, 'f' => $filterVal, 'p' => $pageNo > 1 ? $pageNo : null]));

admin_header($ent['label'], $key);
?>
<div class="page-actions">
  <form class="search" method="get">
    <input type="hidden" name="e" value="<?= e($key) ?>">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search <?= e(strtolower($ent['label'])) ?>…">
    <?php if ($filterField): ?>
      <select name="f" onchange="this.form.submit()">
        <option value="">All <?= e(strtolower($ent['label'])) ?></option>
        <?php foreach (entity_options($fields[$filterField]['source']) as $k => $l): ?>
          <option value="<?= e($k) ?>" <?= (string) $k === (string) $filterVal ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
  </form>
  <a href="<?= e($self . '&action=edit' . ($filterField && $filterVal !== '' ? '&' . $filterField . '=' . (int) $filterVal : '')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add <?= e($ent['singular']) ?></a>
</div>

<div class="card flush">
  <?php if (!$items): ?>
    <p class="empty"><?= $search !== '' ? 'Nothing matches your search.' : 'Nothing here yet — add your first ' . e(strtolower($ent['singular'])) . '.' ?></p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <?php if ($canDrag): ?><th class="w-drag"></th><?php endif; ?>
        <?php foreach ($ent['list'] as $col => $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
        <?php if (isset($fields['is_active'])): ?><th>Visible</th><?php endif; ?>
        <th class="right">Actions</th>
      </tr>
    </thead>
    <tbody <?= $canDrag ? 'data-sortable data-offset="' . $offset . '" data-endpoint="' . e($self) . '"' : '' ?>>
      <?php foreach ($items as $it): ?>
        <tr data-id="<?= (int) $it['id'] ?>">
          <?php if ($canDrag): ?><td class="w-drag"><span class="drag" title="Drag to reorder"><i class="fa-solid fa-grip-vertical"></i></span></td><?php endif; ?>
          <?php foreach ($ent['list'] as $col => $label): ?>
            <td><?php if ($col === $titleField): ?><a class="title-link" href="<?= e($self . '&action=edit&id=' . $it['id']) ?>"><?= e(excerpt((string) $it[$col], 80)) ?></a><?php else: ?><?= cell($fields[$col], $it[$col], $it) ?><?php endif; ?></td>
          <?php endforeach; ?>
          <?php if (isset($fields['is_active'])): ?>
            <td>
              <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><input type="hidden" name="back" value="<?= e($backQs) ?>">
                <button type="submit" class="toggle <?= $it['is_active'] ? 'on' : '' ?>" title="<?= $it['is_active'] ? 'Visible — click to hide' : 'Hidden — click to show' ?>"><span></span></button>
              </form>
            </td>
          <?php endif; ?>
          <td class="right nowrap">
            <a class="icon-btn" href="<?= e($self . '&action=edit&id=' . $it['id']) ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
            <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="duplicate"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="icon-btn" title="Duplicate"><i class="fa-regular fa-copy"></i></button></form>
            <form method="post" action="<?= e($self) ?>" class="inline" data-confirm="Delete this <?= e(strtolower($ent['singular'])) ?>? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php if ($pages > 1): ?>
  <div class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a class="<?= $i === $pageNo ? 'on' : '' ?>" href="<?= e($self . '&' . http_build_query(array_filter(['q' => $search, 'f' => $filterVal, 'p' => $i]))) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php if ($canDrag && $items): ?><p class="hint"><i class="fa-solid fa-grip-vertical"></i> Drag rows to change the order they appear on the website.</p><?php endif; ?>
<?php
admin_footer();
