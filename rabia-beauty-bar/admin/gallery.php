<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('gallery.php');
    $action = $_POST['action'] ?? 'upload';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $old = q('SELECT image FROM gallery WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM gallery WHERE id = ?', [$id]);
        delete_upload($old ?: null);
        flash('success', 'Photo removed.');
    } elseif ($action === 'update') {
        q('UPDATE gallery SET caption = ?, sort_order = ? WHERE id = ?', [post('caption'), (int) ($_POST['sort_order'] ?? 0), $id]);
        flash('success', 'Photo updated.');
    } else {
        try {
            $path = upload_image('image');
            if (!$path) {
                throw new RuntimeException('Please choose an image to upload.');
            }
            q('INSERT INTO gallery (image, caption, sort_order) VALUES (?,?,?)', [$path, post('caption'), (int) ($_POST['sort_order'] ?? 0)]);
            flash('success', 'Photo added to the gallery.');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    redirect('gallery.php');
}
$photos = q('SELECT * FROM gallery ORDER BY sort_order, id DESC')->fetchAll();

$adminTitle = 'Gallery';
$adminPage  = 'gallery';
require __DIR__ . '/inc/header.php';
?>
<section class="panel">
    <h2>Upload a photo</h2>
    <form method="post" enctype="multipart/form-data" class="upload-row">
        <?= csrf_field() ?>
        <input type="file" name="image" accept="image/*" required>
        <input name="caption" placeholder="Caption (e.g. Bridal look)">
        <input name="sort_order" type="number" value="0" style="max-width:100px" title="Sort order">
        <button class="btn btn-primary"><i class="fa-solid fa-upload"></i> Upload</button>
    </form>
    <p class="muted">The home page shows the first 8 photos (lowest sort order first).</p>
</section>
<div class="photo-grid">
    <?php foreach ($photos as $p): ?>
        <div class="panel photo">
            <img src="<?= img($p['image']) ?>" alt="">
            <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="update">
                <input name="caption" value="<?= e($p['caption']) ?>" placeholder="Caption">
                <input name="sort_order" type="number" value="<?= (int) $p['sort_order'] ?>" style="max-width:70px">
                <button class="icon-btn" title="Save"><i class="fa-solid fa-check"></i></button>
            </form>
            <form method="post" data-confirm="Remove this photo?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete">
                <button class="btn btn-sm btn-light btn-block"><i class="fa-solid fa-trash"></i> Remove</button></form>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
