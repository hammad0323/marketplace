<?php
/**
 * PRE-BUILT PROPOSALS — manage the ready-made service proposals.
 */
require __DIR__ . '/includes/bootstrap.php';
require_login();
if (!(int) db_value('SELECT COUNT(*) FROM service_templates')) pm_seed_defaults();
$items = db_all('SELECT * FROM service_templates ORDER BY is_system DESC, name');
$pageTitle = 'Pre-built Proposals';
$activeNav = 'services';
include __DIR__ . '/layouts/header.php';
?>
<div class="page-head">
  <div><h1>Pre-built Proposals</h1><p>Ready-made proposal content for each service. Pick one when creating a proposal, then customise everything.</p></div>
  <div class="actions"><button class="btn btn-light border" id="restoreSys"><i class="bi bi-arrow-counterclockwise"></i> Restore built-in proposals</button></div>
</div>
<div class="row g-3">
  <?php foreach ($items as $it): $pages = jdecode($it['structure']); ?>
    <div class="col-xl-4 col-md-6">
      <div class="card-x h-100 d-flex flex-column">
        <div class="card-body flex-fill">
          <div class="d-flex gap-3 align-items-start mb-3">
            <div class="svc-card p-0 border-0 bg-transparent" style="cursor:default"><div class="ic m-0"><i class="bi bi-<?= e($it['icon'] ?: 'file-earmark-text') ?>"></i></div></div>
            <div class="flex-fill"><h6 class="mb-1"><?= e($it['name']) ?></h6><div class="small text-muted"><?= e($it['description']) ?></div></div>
            <?php if ($it['is_system']): ?><span class="chip">Built-in</span><?php else: ?><span class="chip" style="background:#e6f2f1;color:#015957">Custom</span><?php endif; ?>
          </div>
          <div class="small fw-semibold text-dark mb-2"><?= count($pages) ?> pages</div>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($pages as $i => $pg): ?><span class="chip"><?= sprintf('%02d', $i + 1) ?> <?= e($pg['title'] ?? '') ?></span><?php endforeach; ?>
          </div>
        </div>
        <div class="card-body border-top d-flex gap-2">
          <a href="<?= url('proposal-create.php?service=' . (int) $it['id']) ?>" class="btn btn-brand btn-sm flex-fill"><i class="bi bi-plus-lg"></i> Use this</a>
          <button class="btn btn-light border btn-sm svc-edit" data-id="<?= (int) $it['id'] ?>" data-name="<?= e($it['name']) ?>" data-service="<?= e($it['service']) ?>" data-desc="<?= e($it['description']) ?>" data-icon="<?= e($it['icon']) ?>"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-light border btn-sm svc-act" data-a="service_duplicate" data-id="<?= (int) $it['id'] ?>" title="Duplicate"><i class="bi bi-copy"></i></button>
          <button class="btn btn-light border btn-sm text-danger svc-act" data-a="service_delete" data-id="<?= (int) $it['id'] ?>" title="Delete"><i class="bi bi-trash3"></i></button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<div class="alert alert-light border small mt-4"><i class="bi bi-lightbulb text-warning"></i> Create your own: build a proposal in the editor, then choose <b>⋯ → Save as pre-built proposal</b>.</div>
<?php
$extraScripts = <<<'JS'
<script>
$(function () {
  $('.svc-act').on('click', function () {
    var a = $(this).data('a'), id = $(this).data('id');
    var go = function () { PM.post('ajax/manage.php', {action: a, id: id}).done(function () { location.reload(); }).fail(PM.error); };
    if (a === 'service_delete') PM.confirm({title: 'Delete this pre-built proposal?', text: 'Existing proposals are not affected.', confirmButtonColor: '#dc2626', confirmButtonText: 'Delete'}).then(function (r) { if (r.isConfirmed) go(); }); else go();
  });
  $('#restoreSys').on('click', function () {
    PM.confirm({title: 'Restore built-in proposals?', text: 'Built-in proposals are reset to their original content. Your custom ones are kept.'}).then(function (r) {
      if (r.isConfirmed) PM.post('ajax/manage.php', {action: 'service_restore'}).done(function () { location.reload(); }).fail(PM.error);
    });
  });
  $('.svc-edit').on('click', function () {
    var $t = $(this);
    Swal.fire({title: 'Edit pre-built proposal', html: '<input id="sN" class="form-control mb-2" placeholder="Name"><input id="sS" class="form-control mb-2" placeholder="Service type"><input id="sD" class="form-control mb-2" placeholder="Description"><input id="sI" class="form-control" placeholder="Bootstrap icon name, e.g. code-slash">',
      didOpen: function () { $('#sN').val($t.data('name')); $('#sS').val($t.data('service')); $('#sD').val($t.data('desc')); $('#sI').val($t.data('icon')); },
      showCancelButton: true, confirmButtonColor: '#015957', preConfirm: function () { return {name: $('#sN').val(), service: $('#sS').val(), description: $('#sD').val(), icon: $('#sI').val()}; }}).then(function (r) {
      if (!r.value) return;
      PM.post('ajax/manage.php', $.extend({action: 'service_update', id: $t.data('id')}, r.value)).done(function () { location.reload(); }).fail(PM.error);
    });
  });
});
</script>
JS;
include __DIR__ . '/layouts/footer.php';
