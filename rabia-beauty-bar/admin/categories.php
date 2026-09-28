<?php
require __DIR__ . '/inc/bootstrap.php';

$icons = ['fa-scissors', 'fa-wand-magic-sparkles', 'fa-gem', 'fa-hand-sparkles', 'fa-spa', 'fa-feather-pointed', 'fa-eye', 'fa-hands', 'fa-crown', 'fa-spray-can-sparkles', 'fa-heart', 'fa-star', 'fa-leaf', 'fa-palette', 'fa-person-dress', 'fa-droplet'];
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? q('SELECT * FROM service_categories WHERE id = ?', [$editId])->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('categories.php');
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $old = q('SELECT image FROM service_categories WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM service_categories WHERE id = ?', [$id]);   // services cascade
        delete_upload($old ?: null);
        flash('success', 'Category and its services deleted.');
        redirect('categories.php');
    }

    $name = post('name');
    if ($name === '') {
        flash('error', 'Category name is required.');
        redirect('categories.php' . ($id ? '?edit=' . $id : ''));
    }
    $slug = slugify(post('slug') ?: $name);
    if (q('SELECT id FROM service_categories WHERE slug = ? AND id <> ?', [$slug, $id])->fetch()) {
        $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }
    $icon = in_array(post('icon'), $icons, true) ? post('icon') : 'fa-spa';
    try {
        $image = upload_image('image');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect('categories.php' . ($id ? '?edit=' . $id : ''));
    }
    $data = [$name, $slug, $icon, post('description'), (int) ($_POST['sort_order'] ?? 0), isset($_POST['is_active']) ? 1 : 0];

    if ($id) {
        q('UPDATE service_categories SET name=?, slug=?, icon=?, description=?, sort_order=?, is_active=? WHERE id=?', [...$data, $id]);
        if ($image) {
            delete_upload(q('SELECT image FROM service_categories WHERE id = ?', [$id])->fetchColumn() ?: null);
            q('UPDATE service_categories SET image = ? WHERE id = ?', [$image, $id]);
        }
        flash('success', 'Category updated.');
    } else {
        q('INSERT INTO service_categories (name, slug, icon, description, sort_order, is_active, image) VALUES (?,?,?,?,?,?,?)', [...$data, $image]);
        flash('success', 'Category added.');
    }
    redirect('categories.php');
}

$cats = q('SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS n FROM service_categories c ORDER BY sort_order, name')->fetchAll();
$f = $edit ?: ['id' => 0, 'name' => '', 'slug' => '', 'icon' => 'fa-spa', 'description' => '', 'image' => '', 'sort_order' => count($cats) + 1, 'is_active' => 1];

$adminTitle = 'Service Categories';
$adminPage  = 'categories';
require __DIR__ . '/inc/header.php';
?>
<div class="grid-side">
    <section class="panel">
        <h2>Categories</h2>
        <div class="table-wrap"><table>
            <tr><th></th><th>Name</th><th>Services</th><th>Order</th><th>Visible</th><th></th></tr>
            <?php foreach ($cats as $c): ?>
                <tr class="<?= $c['is_active'] ? '' : 'dim' ?>">
                    <td><span class="cat-icon"><i class="fa-solid <?= e($c['icon']) ?>"></i></span></td>
                    <td><b><?= e($c['name']) ?></b><br><small><?= e($c['description']) ?></small></td>
                    <td><a href="services.php?cat=<?= $c['id'] ?>"><?= (int) $c['n'] ?></a></td>
                    <td><?= (int) $c['sort_order'] ?></td>
                    <td><?= $c['is_active'] ? 'Yes' : 'No' ?></td>
                    <td class="actions">
                        <a href="categories.php?edit=<?= $c['id'] ?>" class="icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete “<?= e($c['name']) ?>” AND all <?= (int) $c['n'] ?> of its services?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </section>

    <section class="panel sticky-panel">
        <h2><?= $edit ? 'Edit category' : 'Add a category' ?></h2>
        <form method="post" enctype="multipart/form-data" class="form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <label>Name *<input name="name" required value="<?= e($f['name']) ?>"></label>
            <label>Short description<input name="description" maxlength="255" value="<?= e($f['description']) ?>"></label>
            <label>Icon</label>
            <div class="icon-pick">
                <?php foreach ($icons as $ic): ?>
                    <label><input type="radio" name="icon" value="<?= $ic ?>" <?= $f['icon'] === $ic ? 'checked' : '' ?>><span><i class="fa-solid <?= $ic ?>"></i></span></label>
                <?php endforeach; ?>
            </div>
            <label>Image (optional)<input type="file" name="image" accept="image/*" data-preview="catPrev"></label>
            <img id="catPrev" class="preview" src="<?= $f['image'] ? img($f['image']) : '' ?>" style="<?= $f['image'] ? '' : 'display:none' ?>" alt="">
            <div class="row">
                <label>Sort order<input name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>"></label>
                <label>URL slug<input name="slug" value="<?= e($f['slug']) ?>" placeholder="auto"></label>
            </div>
            <label class="check"><input type="checkbox" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>> Visible on website</label>
            <button class="btn btn-primary btn-block"><?= $edit ? 'Save changes' : 'Add category' ?></button>
            <?php if ($edit): ?><a href="categories.php" class="btn btn-light btn-block">Cancel</a><?php endif; ?>
        </form>
    </section>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
