<?php
$success = flash('success');
$error = flash('error');
?>
<?php if ($success): ?>
    <div class="flash flash-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>
