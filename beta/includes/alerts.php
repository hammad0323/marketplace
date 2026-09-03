<?php $flashes = get_flashes(); ?>
<?php if (!empty($flashes)): ?>
  <div class="flash-container">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= clean($f['type']) ?>"><?= clean($f['message']) ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<div id="toast-container" class="toast-container"></div>
