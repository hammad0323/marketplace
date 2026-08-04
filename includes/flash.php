<?php
$success = mp_flash('success');
$error = mp_flash('error');
?>
<?php if ($success): ?>
    <div class="flash flash-success"><?= mp_e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error"><?= mp_e($error) ?></div>
<?php endif; ?>
