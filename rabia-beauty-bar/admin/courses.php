<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('courses.php');
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $old = q('SELECT image FROM courses WHERE id = ?', [$id])->fetchColumn();
        q('DELETE FROM courses WHERE id = ?', [$id]);
        delete_upload($old ?: null);
        flash('success', 'Course deleted. Past enrollment requests are kept.');
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        q('UPDATE courses SET is_active = 1 - is_active WHERE id = ?', [$id]);
    }
    redirect('courses.php');
}

$courses = q('SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS n FROM courses c ORDER BY sort_order, id DESC')->fetchAll();

$adminTitle = 'Courses';
$adminPage  = 'courses';
require __DIR__ . '/inc/header.php';
?>
<div class="panel-head top-actions">
    <p class="muted">Courses appear on the home page (when “featured”) and on the Academy page.</p>
    <a href="course-form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add course</a>
</div>
<div class="card-grid">
    <?php if (!$courses): ?><section class="panel"><p class="muted">No courses yet — add your first one.</p></section><?php endif; ?>
    <?php foreach ($courses as $c): ?>
        <article class="panel course-tile <?= $c['is_active'] ? '' : 'dim' ?>">
            <img src="<?= img($c['image']) ?>" alt="">
            <div class="tile-body">
                <h3><?= e($c['title']) ?></h3>
                <p class="muted"><?= e($c['duration']) ?><?= $c['schedule'] ? ' · ' . e($c['schedule']) : '' ?></p>
                <p><b><?= money($c['fee'], 'No fee set') ?></b> · <a href="enrollments.php?course=<?= $c['id'] ?>"><?= (int) $c['n'] ?> enrollment(s)</a></p>
                <div class="actions">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="toggle">
                        <button class="switch <?= $c['is_active'] ? 'on' : '' ?>" title="Show / hide on website"></button></form>
                    <a href="../course.php?slug=<?= e($c['slug']) ?>" target="_blank" class="icon-btn" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a href="course-form.php?id=<?= $c['id'] ?>" class="icon-btn" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <form method="post" data-confirm="Delete the course “<?= e($c['title']) ?>”?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="action" value="delete">
                        <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
