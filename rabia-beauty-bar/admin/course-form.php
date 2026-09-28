<?php
require __DIR__ . '/inc/bootstrap.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$course = $id ? q('SELECT * FROM courses WHERE id = ?', [$id])->fetch() : null;
if ($id && !$course) {
    redirect('courses.php');
}
$f = $course ?: ['title' => '', 'slug' => '', 'tagline' => '', 'description' => '', 'includes' => '', 'duration' => '', 'schedule' => '',
                 'timing' => '', 'start_date' => '', 'fee' => '', 'seats' => '', 'extras' => '', 'image' => '', 'is_featured' => 1, 'is_active' => 1, 'sort_order' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('course-form.php' . ($id ? '?id=' . $id : ''));
    foreach (['title', 'tagline', 'description', 'includes', 'duration', 'schedule', 'timing', 'start_date', 'extras'] as $k) {
        $f[$k] = post($k);
    }
    $f['fee']         = post('fee') === '' ? null : max(0, (int) preg_replace('/\D/', '', post('fee')));
    $f['seats']       = post('seats') === '' ? null : max(0, (int) post('seats'));
    $f['sort_order']  = (int) ($_POST['sort_order'] ?? 0);
    $f['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $f['is_active']   = isset($_POST['is_active']) ? 1 : 0;
    $f['slug']        = slugify(post('slug') ?: $f['title']);

    if ($f['title'] === '') {
        $errors[] = 'Course title is required.';
    }
    if (q('SELECT id FROM courses WHERE slug = ? AND id <> ?', [$f['slug'], $id])->fetch()) {
        $f['slug'] .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    }
    try {
        $newImage = upload_image('image');
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
        $newImage = null;
    }

    if (!$errors) {
        if ($newImage) {
            delete_upload($course['image'] ?? null);
            $f['image'] = $newImage;
        }
        $vals = [$f['title'], $f['slug'], $f['tagline'], $f['description'], $f['includes'], $f['duration'], $f['schedule'], $f['timing'],
                 $f['start_date'], $f['fee'], $f['seats'], $f['extras'], $f['image'] ?: null, $f['is_featured'], $f['is_active'], $f['sort_order']];
        if ($id) {
            q('UPDATE courses SET title=?, slug=?, tagline=?, description=?, includes=?, duration=?, schedule=?, timing=?, start_date=?, fee=?, seats=?,
               extras=?, image=?, is_featured=?, is_active=?, sort_order=? WHERE id=?', [...$vals, $id]);
            flash('success', 'Course updated.');
        } else {
            q('INSERT INTO courses (title, slug, tagline, description, includes, duration, schedule, timing, start_date, fee, seats, extras, image,
               is_featured, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
            flash('success', 'Course added — it is now live on the website.');
        }
        redirect('courses.php');
    }
}

$adminTitle = $id ? 'Edit course' : 'Add course';
$adminPage  = 'courses';
require __DIR__ . '/inc/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="grid-side form">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <section class="panel">
        <label>Course title *<input name="title" required value="<?= e($f['title']) ?>" placeholder="e.g. Basic to Advance Makeup Course"></label>
        <label>Tagline<input name="tagline" value="<?= e($f['tagline']) ?>" placeholder="e.g. Professional training | Certificate included"></label>
        <label>Description<textarea name="description" rows="6"><?= e($f['description']) ?></textarea></label>
        <label>What's included <small>(one item per line)</small><textarea name="includes" rows="8" placeholder="Skin preparation&#10;Eye makeup techniques&#10;…"><?= e($f['includes']) ?></textarea></label>
        <div class="row">
            <label>Duration<input name="duration" value="<?= e($f['duration']) ?>" placeholder="2 Months"></label>
            <label>Classes / schedule<input name="schedule" value="<?= e($f['schedule']) ?>" placeholder="5 days a week (Mon–Fri)"></label>
        </div>
        <div class="row">
            <label>Timing<input name="timing" value="<?= e($f['timing']) ?>" placeholder="2:00 PM – 6:00 PM"></label>
            <label>Starting from<input name="start_date" value="<?= e($f['start_date']) ?>" placeholder="1st September"></label>
        </div>
    </section>
    <section class="panel sticky-panel">
        <label>Course fee (Rs.)<input name="fee" inputmode="numeric" value="<?= e((string) $f['fee']) ?>" placeholder="70000"></label>
        <label>Seats available<input name="seats" type="number" min="0" value="<?= e((string) $f['seats']) ?>" placeholder="optional"></label>
        <label>Extras<input name="extras" value="<?= e($f['extras']) ?>" placeholder="Certificate included"></label>
        <label>Cover image / flyer<input type="file" name="image" accept="image/*" data-preview="coursePrev"></label>
        <img id="coursePrev" class="preview" src="<?= $f['image'] ? img($f['image']) : '' ?>" style="<?= $f['image'] ? '' : 'display:none' ?>" alt="">
        <div class="row">
            <label>Sort order<input name="sort_order" type="number" value="<?= (int) $f['sort_order'] ?>"></label>
            <label>URL slug<input name="slug" value="<?= e($f['slug']) ?>" placeholder="auto"></label>
        </div>
        <label class="check"><input type="checkbox" name="is_featured" <?= $f['is_featured'] ? 'checked' : '' ?>> Feature on home page</label>
        <label class="check"><input type="checkbox" name="is_active" <?= $f['is_active'] ? 'checked' : '' ?>> Visible on website</label>
        <button class="btn btn-primary btn-block"><?= $id ? 'Save changes' : 'Publish course' ?></button>
        <a href="courses.php" class="btn btn-light btn-block">Cancel</a>
    </section>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
