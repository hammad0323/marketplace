<?php
require __DIR__ . '/inc/bootstrap.php';

$statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
$back = 'bookings.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard($back);
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM bookings WHERE id = ?', [$id]);
        flash('success', 'Booking deleted.');
    } elseif (in_array($_POST['status'] ?? '', $statuses, true)) {
        q('UPDATE bookings SET status = ?, admin_note = ? WHERE id = ?', [$_POST['status'], post('admin_note'), $id]);
        flash('success', 'Booking updated.');
    }
    redirect($back);
}

$where = [];
$params = [];
if (in_array($_GET['status'] ?? '', $statuses, true)) { $where[] = 'status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) { $where[] = 'booking_date = ?'; $params[] = $_GET['date']; }
if (!empty($_GET['q'])) {
    $where[] = '(name LIKE ? OR phone LIKE ? OR ref LIKE ? OR service_name LIKE ?)';
    array_push($params, ...array_fill(0, 4, '%' . $_GET['q'] . '%'));
}
$bookings = q('SELECT * FROM bookings' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
              ' ORDER BY booking_date DESC, booking_time DESC LIMIT 300', $params)->fetchAll();

$adminTitle = 'Bookings';
$adminPage  = 'bookings';
require __DIR__ . '/inc/header.php';
?>
<form class="filters" method="get">
    <input type="search" name="q" placeholder="Search name, phone, ref…" value="<?= e($_GET['q'] ?? '') ?>">
    <input type="date" name="date" value="<?= e($_GET['date'] ?? '') ?>">
    <select name="status"><option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm">Filter</button>
    <a href="bookings.php" class="btn btn-sm btn-light">Reset</a>
</form>

<section class="panel">
    <?php if (!$bookings): ?><p class="muted">No bookings found.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Ref</th><th>Date &amp; time</th><th>Client</th><th>Service</th><th>Notes</th><th>Status / action</th><th></th></tr>
        <?php foreach ($bookings as $b):
            $wa = 'https://wa.me/' . preg_replace(['/\D/', '/^0/'], ['', '92'], $b['phone']) . '?text=' . rawurlencode("Hi {$b['name']}, this is " . setting('site_name') . " regarding your {$b['service_name']} appointment on " . date('j M', strtotime($b['booking_date'])) . ' at ' . slot_label(substr($b['booking_time'], 0, 5)) . " (Ref {$b['ref']})."); ?>
            <tr>
                <td><b><?= e($b['ref']) ?></b><br><small><?= e(date('j M, g:i A', strtotime($b['created_at']))) ?></small></td>
                <td><?= e(date('D j M Y', strtotime($b['booking_date']))) ?><br><small><?= e(slot_label(substr($b['booking_time'], 0, 5))) ?></small></td>
                <td><?= e($b['name']) ?><br><small><a href="tel:<?= e($b['phone']) ?>"><?= e($b['phone']) ?></a> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i></a></small><?php if ($b['email']): ?><br><small><?= e($b['email']) ?></small><?php endif; ?></td>
                <td><?= e($b['service_name']) ?></td>
                <td class="notes"><?= nl2br(e($b['notes'])) ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <select name="status" class="st-<?= e($b['status']) ?>">
                            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                        <input name="admin_note" placeholder="Internal note" value="<?= e($b['admin_note']) ?>">
                        <button class="btn btn-sm">Save</button>
                    </form>
                </td>
                <td>
                    <form method="post" data-confirm="Delete this booking permanently?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="action" value="delete">
                        <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
