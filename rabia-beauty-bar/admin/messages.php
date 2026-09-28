<?php
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('messages.php');
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM messages WHERE id = ?', [$id]);
        flash('success', 'Message deleted.');
    } else {
        q('UPDATE messages SET is_read = 1 - is_read WHERE id = ?', [$id]);
    }
    redirect('messages.php');
}
$rows = q('SELECT * FROM messages ORDER BY is_read, id DESC LIMIT 300')->fetchAll();

$adminTitle = 'Messages';
$adminPage  = 'messages';
require __DIR__ . '/inc/header.php';
?>
<?php if (!$rows): ?><section class="panel"><p class="muted">No messages yet.</p></section><?php endif; ?>
<?php foreach ($rows as $m): ?>
    <section class="panel message <?= $m['is_read'] ? 'read' : 'unread' ?>">
        <div class="panel-head">
            <div>
                <h2><?= e($m['subject'] ?: 'No subject') ?></h2>
                <small><?= e($m['name']) ?>
                    <?php if ($m['phone']): ?> · <a href="tel:<?= e($m['phone']) ?>"><?= e($m['phone']) ?></a><?php endif; ?>
                    <?php if ($m['email']): ?> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?php endif; ?>
                    · <?= e(date('j M Y, g:i A', strtotime($m['created_at']))) ?></small>
            </div>
            <div class="actions">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button class="btn btn-sm btn-light"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button></form>
                <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="delete">
                    <button class="icon-btn danger" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
            </div>
        </div>
        <p><?= nl2br(e($m['message'])) ?></p>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/inc/footer.php'; ?>
