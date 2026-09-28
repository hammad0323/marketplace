<?php
require __DIR__ . '/inc/bootstrap.php';

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? q('SELECT * FROM testimonials WHERE id = ?', [$editId])->fetch() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('testimonials.php');
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        q('DELETE FROM testimonials WHERE id = ?', [$id]);
        flash('success', 'Review deleted.');
    } elseif ($action === 'toggle') {
        q('UPDATE testimonials SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } else {
        $data = [post('name'), post('text'), max(1, min(5, (int) ($_POST['rating'] ?? 5))), isset($_POST['is_active']) ? 1 : 0, (int) ($_POST['sort_order'] ?? 0)];
        if ($data[0] === '' || $data[1] === '') {
            flash('error', 'Name and review text are required.');
        } elseif ($id) {
            q('UPDATE testimonials SET name=?, text=?, rating=?, is_active=?, sort_order=? WHERE id=?', [...$data, $id]);
            flash('success', 'Review updated.');
        } else {
            q('INSERT INTO testimonials (name, text, rating, is_active, sort_order) VALUES (?,?,?,?,?)', $data);
            flash('success', 'Review added.');
        }
    }
    redirect('testimonials.php');
}
$rows = q('SELECT * FROM testimonials ORDER BY sort_order, id')->fetchAll();
$f = $edit ?: ['id' => 0, 'name' => '', 'text' => '', 'rating' => 5, 'is_active' => 1, 'sort_order' => count($rows) + 1];

$adminTitle = 'Client Reviews';
$adminPage  = 'testimonials';
require __DIR__ . '/inc/header.php';
?>
<div class="grid-side">
    <section class="panel">
        <h2>Reviews shown on the home page</h2>
        <div class="table-wrap"><table>
            <tr><th>Client</th><th>Review</th><th>Rating</th><th>Visible</th><th></th></tr>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['is_active'] ? '' : 'dim' ?>">
                    <td><b><?= e($r['name']) ?></b></td>
                    <td class="notes"><?= e($r['text']) ?></td>
                    <td class="gold"><?= str_repeat('★', (int) $r['rating']) ?></td>
                    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="switch <?= $r['is_active'] ? 'on' : '' ?>"></button></form></td>
                    <td class="actions">
                        <a href="testimonials.php?edit=<?= $r['id'] ?>" class="icon-btn"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="icon-btn danger"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </section>
    <section class="panel sticky-panel">
        <h2><?= $edit ? 'Edit review' : 'Add a review' ?></h2>
        <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <label>Client name *<input name="name" required value="<?= e($f['name']) ?>"></label>
            <label>Review *<textarea name="text" rows="5" required><?= e($f['text']) ?></textarea></label>
            <div class="row">
                <label>Rating<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int) $f['rating'] === $i ? 'selected' : '' ?>><?= $i ?> ★</option><?php endfor; ?></select></label>
                <label>Sort order<input name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>"></label>
            </div>
            <label class="check"><input type="checkbox" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>> Visible on website</label>
            <button class="btn btn-primary btn-block"><?= $edit ? 'Save changes' : 'Add review' ?></button>
            <?php if ($edit): ?><a href="testimonials.php" class="btn btn-light btn-block">Cancel</a><?php endif; ?>
        </form>
    </section>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
