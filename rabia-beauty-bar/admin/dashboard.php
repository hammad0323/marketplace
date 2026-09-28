<?php
require __DIR__ . '/inc/bootstrap.php';

$today = date('Y-m-d');
$stats = [
    ['Today\'s bookings', (int) q("SELECT COUNT(*) FROM bookings WHERE booking_date=? AND status IN ('pending','confirmed')", [$today])->fetchColumn(), 'fa-calendar-day', 'bookings.php?date=' . $today],
    ['Pending bookings',  (int) q("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(), 'fa-hourglass-half', 'bookings.php?status=pending'],
    ['New enrollments',   (int) q("SELECT COUNT(*) FROM enrollments WHERE status='new'")->fetchColumn(), 'fa-user-graduate', 'enrollments.php?status=new'],
    ['Unread messages',   (int) q('SELECT COUNT(*) FROM messages WHERE is_read=0')->fetchColumn(), 'fa-envelope', 'messages.php'],
    ['Active services',   (int) q('SELECT COUNT(*) FROM services WHERE is_active=1')->fetchColumn(), 'fa-scissors', 'services.php'],
    ['Active courses',    (int) q('SELECT COUNT(*) FROM courses WHERE is_active=1')->fetchColumn(), 'fa-graduation-cap', 'courses.php'],
];
$upcoming = q("SELECT * FROM bookings WHERE booking_date >= ? AND status IN ('pending','confirmed')
               ORDER BY booking_date, booking_time LIMIT 10", [$today])->fetchAll();
$recentEnroll = q('SELECT * FROM enrollments ORDER BY id DESC LIMIT 5')->fetchAll();
$defaultPw = password_verify('admin123', (string) q('SELECT password_hash FROM admins WHERE id=?', [$_SESSION['admin_id']])->fetchColumn());

$adminTitle = 'Dashboard';
$adminPage  = 'dashboard';
require __DIR__ . '/inc/header.php';
?>
<?php if ($defaultPw): ?>
    <div class="alert alert-warn"><i class="fa-solid fa-triangle-exclamation"></i> You're still using the default password. <a href="settings.php#password">Change it now</a>.</div>
<?php endif; ?>

<div class="stat-grid">
    <?php foreach ($stats as [$label, $n, $icon, $href]): ?>
        <a href="<?= $href ?>" class="stat-card"><i class="fa-solid <?= $icon ?>"></i><div><strong><?= $n ?></strong><span><?= $label ?></span></div></a>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head"><h2>Upcoming appointments</h2><a href="bookings.php" class="btn btn-sm btn-light">All bookings</a></div>
        <?php if (!$upcoming): ?><p class="muted">No upcoming appointments.</p><?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>When</th><th>Client</th><th>Service</th><th>Status</th></tr>
            <?php foreach ($upcoming as $b): ?>
                <tr>
                    <td><?= e(date('D j M', strtotime($b['booking_date']))) ?><br><small><?= e(slot_label(substr($b['booking_time'], 0, 5))) ?></small></td>
                    <td><?= e($b['name']) ?><br><small><?= e($b['phone']) ?></small></td>
                    <td><?= e($b['service_name']) ?></td>
                    <td><?= status_badge($b['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Latest enrollments</h2><a href="enrollments.php" class="btn btn-sm btn-light">All</a></div>
        <?php if (!$recentEnroll): ?><p class="muted">No enrollment requests yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Student</th><th>Course</th><th>Status</th></tr>
            <?php foreach ($recentEnroll as $en): ?>
                <tr><td><?= e($en['name']) ?><br><small><?= e($en['phone']) ?></small></td><td><?= e($en['course_title']) ?></td><td><?= status_badge($en['status']) ?></td></tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
