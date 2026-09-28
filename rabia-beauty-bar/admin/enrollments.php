<?php
require __DIR__ . '/inc/bootstrap.php';

$statuses = ['new', 'contacted', 'enrolled', 'cancelled'];
$back = 'enrollments.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard($back);
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM enrollments WHERE id = ?', [$id]);
        flash('success', 'Enrollment deleted.');
    } elseif (in_array($_POST['status'] ?? '', $statuses, true)) {
        q('UPDATE enrollments SET status = ? WHERE id = ?', [$_POST['status'], $id]);
        flash('success', 'Enrollment updated.');
    }
    redirect($back);
}

$where = [];
$params = [];
if (in_array($_GET['status'] ?? '', $statuses, true)) { $where[] = 'status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['course'])) { $where[] = 'course_id = ?'; $params[] = (int) $_GET['course']; }
$rows = q('SELECT * FROM enrollments' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300', $params)->fetchAll();
$courseList = q('SELECT id, title FROM courses ORDER BY title')->fetchAll();

$adminTitle = 'Course Enrollments';
$adminPage  = 'enrollments';
require __DIR__ . '/inc/header.php';
?>
<form class="filters" method="get">
    <select name="course"><option value="">All courses</option>
        <?php foreach ($courseList as $c): ?><option value="<?= $c['id'] ?>" <?= (int) ($_GET['course'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
    </select>
    <select name="status"><option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm">Filter</button>
    <a href="enrollments.php" class="btn btn-sm btn-light">Reset</a>
</form>
<section class="panel">
    <?php if (!$rows): ?><p class="muted">No enrollment requests found.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Received</th><th>Student</th><th>Course</th><th>Message</th><th>Status</th><th></th></tr>
        <?php foreach ($rows as $r): $wa = 'https://wa.me/' . preg_replace(['/\D/', '/^0/'], ['', '92'], $r['phone']); ?>
            <tr>
                <td><?= e(date('j M Y', strtotime($r['created_at']))) ?><br><small><?= e(date('g:i A', strtotime($r['created_at']))) ?></small></td>
                <td><?= e($r['name']) ?><br><small><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i></a></small>
                    <?php if ($r['email']): ?><br><small><?= e($r['email']) ?></small><?php endif; ?>
                    <?php if ($r['city']): ?><br><small><?= e($r['city']) ?></small><?php endif; ?></td>
                <td><?= e($r['course_title']) ?></td>
                <td class="notes"><?= nl2br(e($r['message'])) ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <select name="status" onchange="this.form.submit()" class="st-<?= e($r['status']) ?>">
                            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="post" data-confirm="Delete this enrollment request?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="delete">
                        <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
