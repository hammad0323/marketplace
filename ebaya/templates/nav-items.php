<?php if (!defined('EBAYA')) { http_response_code(403); exit; } ?>
<ul class="nav-list">
  <?php foreach ($nav as $n): ?>
    <li class="nav-item-eb<?= $n['children'] ? ' has-dropdown' : '' ?><?= $isActive($n['url']) ? ' is-active' : '' ?>">
      <a href="<?= e(url($n['url'])) ?>"<?= $n['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?><?= $n['text_color'] ? ' style="color:' . e($n['text_color']) . '"' : '' ?><?= $n['children'] ? ' aria-haspopup="true"' : '' ?>><?= e($n['label']) ?></a>
      <?php if ($n['children']): ?>
        <div class="nav-dropdown" role="menu">
          <ul>
            <?php foreach ($n['children'] as $c): ?>
              <li><a role="menuitem" href="<?= e(url($c['url'])) ?>"><?= e($c['label']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
