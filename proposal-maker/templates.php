<?php
/**
 * DESIGN TEMPLATES — gallery of the 10 designs.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
$default = (int) (company()['default_template'] ?? 1);
$pageTitle = 'Design Templates';
$activeNav = 'templates';
$extraHead = proposal_head_assets() . '<style>.tpl-thumb.big{height:272px}.tpl-thumb.big .mini{transform:scale(.215)}.tpl-thumb.big .mini-wrap{width:171px;height:241px}</style>';
include __DIR__ . '/layouts/header.php';
?>
<div class="page-head">
  <div><h1>Design Templates</h1><p>10 professionally designed layouts — each with its own cover page and matching internal pages. Colours are fully customisable per proposal.</p></div>
</div>
<div class="row g-3">
  <?php foreach (pm_templates() as $id => $t): ?>
    <div class="col-xl-4 col-md-6">
      <div class="tpl-card <?= $id === $default ? 'selected' : '' ?>" style="cursor:default">
        <div class="tpl-thumb big"><span class="tpl-check"><i class="bi bi-star-fill"></i></span><?= template_thumb($id) ?></div>
        <div class="tpl-info">
          <div class="d-flex align-items-start gap-2">
            <div class="flex-fill"><h6><span class="text-muted"><?= sprintf('%02d', $id) ?></span> <?= e($t['name']) ?></h6><p><?= e($t['desc']) ?></p>
              <div class="small mt-2"><span class="chip"><i class="bi bi-fonts"></i> <?= e($t['fonts']['heading']) ?> / <?= e($t['fonts']['body']) ?></span></div>
              <div class="small text-muted mt-1">Best for: <?= e($t['suitable']) ?></div></div>
          </div>
          <div class="d-flex gap-1 my-2"><?php foreach (['primary', 'secondary', 'accent', 'heading', 'background', 'card'] as $k): ?><span style="flex:1;height:18px;border-radius:5px;border:1px solid rgba(0,0,0,.08);background:<?= e($t['colors'][$k]) ?>"></span><?php endforeach; ?></div>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-brand btn-sm flex-fill" href="<?= url('proposal-create.php?template=' . $id) ?>"><i class="bi bi-plus-lg"></i> Use template</a>
            <button class="btn btn-light border btn-sm set-def" data-id="<?= $id ?>" <?= $id === $default ? 'disabled' : '' ?>><?= $id === $default ? 'Default' : 'Set default' ?></button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php
$extraScripts = "<script>$('.set-def').on('click', function(){ PM.post('ajax/manage.php', {action:'set_default_template', id: $(this).data('id')}).done(function(){ location.reload(); }); });</script>";
include __DIR__ . '/layouts/footer.php';
