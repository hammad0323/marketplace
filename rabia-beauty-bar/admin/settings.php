<?php
require __DIR__ . '/inc/bootstrap.php';

$fields = [
    'Business' => [
        'site_name'  => ['Business name', 'text'],
        'tagline'    => ['Tagline', 'text'],
        'phone'      => ['Phone (displayed)', 'text'],
        'whatsapp'   => ['WhatsApp number (international, e.g. 923048398890)', 'text'],
        'email'      => ['Email', 'text'],
        'address'    => ['Address', 'text'],
        'hours'      => ['Opening hours (displayed)', 'text'],
        'map_query'  => ['Google Maps search text', 'text'],
        'instagram'  => ['Instagram URL', 'text'],
        'facebook'   => ['Facebook URL', 'text'],
    ],
    'Home page' => [
        'hero_title'   => ['Hero headline', 'text'],
        'hero_text'    => ['Hero text', 'textarea'],
        'about_text'   => ['About us text', 'textarea'],
        'rating'       => ['Google rating (e.g. 4.8)', 'text'],
        'review_count' => ['Number of Google reviews', 'text'],
        'followers'    => ['Instagram followers', 'text'],
    ],
    'Booking' => [
        'open_time'     => ['First slot (24h, e.g. 11:00)', 'time'],
        'close_time'    => ['Closing time (24h, e.g. 20:00)', 'time'],
        'slot_minutes'  => ['Minutes between slots', 'number'],
        'slot_capacity' => ['Clients per time slot', 'number'],
        'closed_day'    => ['Weekly closed day', 'day'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('settings.php');
    if (($_POST['action'] ?? '') === 'password') {
        $hash = q('SELECT password_hash FROM admins WHERE id = ?', [$_SESSION['admin_id']])->fetchColumn();
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $hash)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('error', 'New passwords do not match.');
        } else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            flash('success', 'Password changed.');
        }
        redirect('settings.php#password');
    }

    $st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($fields as $group) {
        foreach ($group as $key => $_) {
            if (isset($_POST[$key])) {
                $st->execute([$key, trim((string) $_POST[$key])]);
            }
        }
    }
    flash('success', 'Settings saved.');
    redirect('settings.php');
}

$adminTitle = 'Settings';
$adminPage  = 'settings';
require __DIR__ . '/inc/header.php';
$days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>
<form method="post" class="form">
    <?= csrf_field() ?>
    <?php foreach ($fields as $group => $items): ?>
        <section class="panel">
            <h2><?= $group ?></h2>
            <div class="settings-grid">
                <?php foreach ($items as $key => [$label, $type]): $val = setting($key); ?>
                    <label class="<?= $type === 'textarea' ? 'full' : '' ?>"><?= e($label) ?>
                        <?php if ($type === 'textarea'): ?>
                            <textarea name="<?= $key ?>" rows="4"><?= e($val) ?></textarea>
                        <?php elseif ($type === 'day'): ?>
                            <select name="<?= $key ?>"><?php foreach ($days as $d): ?><option value="<?= $d ?>" <?= $val === $d ? 'selected' : '' ?>><?= $d ?: 'Open every day' ?></option><?php endforeach; ?></select>
                        <?php else: ?>
                            <input type="<?= $type ?>" name="<?= $key ?>" value="<?= e($val) ?>" <?= $type === 'number' ? 'min="1"' : '' ?>>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <button class="btn btn-primary">Save settings</button>
</form>

<section class="panel" id="password" style="margin-top:30px">
    <h2>Change admin password</h2>
    <form method="post" class="form settings-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>New password (min 8 characters)<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
        <label>Confirm new password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
        <div><button class="btn btn-primary">Update password</button></div>
    </form>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
