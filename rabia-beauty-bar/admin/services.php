<?php
require __DIR__ . '/inc/bootstrap.php';

$categories = q('SELECT * FROM service_categories ORDER BY sort_order, name')->fetchAll();
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? q('SELECT * FROM services WHERE id = ?', [$editId])->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('services.php');
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        q('DELETE FROM services WHERE id = ?', [$id]);
        flash('success', 'Service deleted.');
        redirect('services.php');
    }
    if ($action === 'toggle') {
        q('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('services.php');
    }

    $data = [
        (int) ($_POST['category_id'] ?? 0),
        post('name'),
        post('description'),
        post('price') === '' ? null : max(0, (int) preg_replace('/\D/', '', post('price'))),
        isset($_POST['price_from']) ? 1 : 0,
        post('duration'),
        isset($_POST['is_featured']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        (int) ($_POST['sort_order'] ?? 0),
    ];
    if ($data[1] === '' || !$data[0]) {
        flash('error', 'Service name and category are required.');
        redirect('services.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        q('UPDATE services SET category_id=?, name=?, description=?, price=?, price_from=?, duration=?, is_featured=?, is_active=?, sort_order=? WHERE id=?', [...$data, $id]);
        flash('success', 'Service updated.');
    } else {
        q('INSERT INTO services (category_id, name, description, price, price_from, duration, is_featured, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?)', $data);
        flash('success', 'Service added.');
    }
    redirect('services.php');
}

$filterCat = (int) ($_GET['cat'] ?? 0);
$services = q('SELECT s.*, c.name AS category FROM services s JOIN service_categories c ON c.id = s.category_id' .
              ($filterCat ? ' WHERE s.category_id = ?' : '') . ' ORDER BY c.sort_order, s.sort_order, s.name',
              $filterCat ? [$filterCat] : [])->fetchAll();

$adminTitle = 'Services';
$adminPage  = 'services';
require __DIR__ . '/inc/header.php';
$f = $edit ?: ['id' => 0, 'category_id' => $filterCat, 'name' => '', 'description' => '', 'price' => '', 'price_from' => 0, 'duration' => '', 'is_featured' => 0, 'is_active' => 1, 'sort_order' => 0];
?>
<div class="grid-side">
    <section class="panel">
        <div class="panel-head">
            <h2>All services (<?= count($services) ?>)</h2>
            <form method="get"><select name="cat" onchange="this.form.submit()"><option value="">All categories</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $filterCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></form>
        </div>
        <div class="table-wrap"><table>
            <tr><th>Service</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr>
            <?php foreach ($services as $s): ?>
                <tr class="<?= $s['is_active'] ? '' : 'dim' ?>">
                    <td><b><?= e($s['name']) ?></b><?= $s['is_featured'] ? ' <span class="status status-confirmed">Popular</span>' : '' ?><br><small><?= e($s['description']) ?></small></td>
                    <td><?= e($s['category']) ?></td>
                    <td><?= ($s['price_from'] && $s['price'] ? 'From ' : '') . money($s['price'], '—') ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="switch <?= $s['is_active'] ? 'on' : '' ?>" title="Show / hide on website"></button></form>
                    </td>
                    <td class="actions">
                        <a href="services.php?edit=<?= $s['id'] ?>" class="icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete “<?= e($s['name']) ?>”?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </section>

    <section class="panel sticky-panel" id="form">
        <h2><?= $edit ? 'Edit service' : 'Add a new service' ?></h2>
        <?php if (!$categories): ?><p class="muted">Create a <a href="categories.php">category</a> first.</p><?php else: ?>
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <label>Category *<select name="category_id" required>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $f['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></label>
            <label>Service name *<input name="name" required value="<?= e($f['name']) ?>"></label>
            <label>Short description<input name="description" maxlength="255" value="<?= e($f['description']) ?>"></label>
            <div class="row">
                <label>Price (Rs.)<input name="price" inputmode="numeric" placeholder="Leave empty = On consultation" value="<?= e((string) $f['price']) ?>"></label>
                <label>Duration<input name="duration" placeholder="e.g. 45 min" value="<?= e($f['duration']) ?>"></label>
            </div>
            <label>Sort order<input name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>"></label>
            <label class="check"><input type="checkbox" name="price_from" <?= $f['price_from'] ? 'checked' : '' ?>> Show as “From Rs. …”</label>
            <label class="check"><input type="checkbox" name="is_featured" <?= $f['is_featured'] ? 'checked' : '' ?>> Mark as popular</label>
            <label class="check"><input type="checkbox" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>> Visible on website</label>
            <button class="btn btn-primary btn-block"><?= $edit ? 'Save changes' : 'Add service' ?></button>
            <?php if ($edit): ?><a href="services.php" class="btn btn-light btn-block">Cancel</a><?php endif; ?>
        </form>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
