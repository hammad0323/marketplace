<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
if (!(int) db_value('SELECT COUNT(*) FROM content_library')) pm_seed_defaults();
$items = db_all('SELECT * FROM content_library ORDER BY category, name');
$pageTitle = 'Content Library';
$activeNav = 'library';
$extraHead = proposal_head_assets();
include __DIR__ . '/layouts/header.php';
$cats = array_unique(array_map(fn($i) => $i['category'] ?: 'General', $items));
?>
<div class="page-head">
  <div><h1>Content Library</h1><p>Reusable sections you can insert into any proposal from the editor (Library tab).</p></div>
  <div class="actions"><input class="form-control" id="libQ" placeholder="Search sections…" style="width:240px"></div>
</div>
<div class="alert alert-light border small"><i class="bi bi-lightbulb text-warning"></i> To add a section: open any proposal in the editor, select a block (or a page) and click <b>Save to library</b>. To change a saved section’s content, insert it, edit it, and save it again.</div>
<div class="mb-3 d-flex gap-2 flex-wrap"><button class="btn btn-sm btn-brand cat-f" data-c="">All</button><?php foreach ($cats as $c): ?><button class="btn btn-sm btn-light border cat-f" data-c="<?= e($c) ?>"><?= e($c) ?></button><?php endforeach; ?></div>
<div class="row g-3" id="libGrid">
  <?php if (!$items): ?><div class="col-12"><div class="card-x empty"><i class="bi bi-bookmark"></i>No saved sections yet.</div></div><?php endif; ?>
  <?php foreach ($items as $it): $blocks = jdecode($it['blocks']); ?>
    <div class="col-xxl-3 col-lg-4 col-md-6 lib-col" data-c="<?= e($it['category'] ?: 'General') ?>" data-n="<?= e(mb_strtolower($it['name'])) ?>">
      <div class="tpl-card" style="cursor:default">
        <div class="tpl-thumb" style="height:230px"><?= blocks_thumb($blocks, (int) (company()['default_template'] ?? 1)) ?></div>
        <div class="tpl-info">
          <div class="d-flex align-items-start gap-2">
            <div class="flex-fill"><h6><?= e($it['name']) ?></h6><p><span class="chip"><?= e($it['category'] ?: 'General') ?></span> · <?= count($blocks) ?> block<?= count($blocks) === 1 ? '' : 's' ?></p></div>
            <div class="dropdown"><button class="btn btn-sm btn-light border" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
              <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item lib-edit" href="#" data-id="<?= (int) $it['id'] ?>" data-name="<?= e($it['name']) ?>" data-cat="<?= e($it['category']) ?>"><i class="bi bi-pencil me-2"></i>Rename / category</a></li>
                <li><a class="dropdown-item lib-act" href="#" data-a="library_duplicate" data-id="<?= (int) $it['id'] ?>"><i class="bi bi-copy me-2"></i>Duplicate</a></li>
                <li><a class="dropdown-item text-danger lib-act" href="#" data-a="library_delete" data-id="<?= (int) $it['id'] ?>"><i class="bi bi-trash3 me-2"></i>Delete</a></li>
              </ul></div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php
$extraScripts = <<<'JS'
<script>
$(function () {
  function filter() {
    var q = $('#libQ').val().toLowerCase(), c = $('.cat-f.btn-brand').data('c');
    $('.lib-col').each(function () { $(this).toggle((!c || $(this).data('c') === c) && $(this).data('n').indexOf(q) > -1); });
  }
  $('#libQ').on('input', filter);
  $('.cat-f').on('click', function () { $('.cat-f').removeClass('btn-brand').addClass('btn-light border'); $(this).addClass('btn-brand').removeClass('btn-light border'); filter(); });
  $('.lib-act').on('click', function (e) {
    e.preventDefault(); var a = $(this).data('a'), id = $(this).data('id');
    var go = function () { PM.post('ajax/manage.php', {action: a, id: id}).done(function () { location.reload(); }).fail(PM.error); };
    if (a === 'library_delete') PM.confirm({title: 'Delete this section?', confirmButtonColor: '#dc2626', confirmButtonText: 'Delete'}).then(function (r) { if (r.isConfirmed) go(); }); else go();
  });
  $('.lib-edit').on('click', function (e) {
    e.preventDefault(); var $t = $(this);
    Swal.fire({title: 'Edit section', html: '<input id="lN" class="form-control mb-2" placeholder="Name"><input id="lC" class="form-control" placeholder="Category">',
      didOpen: function () { $('#lN').val($t.data('name')); $('#lC').val($t.data('cat')); }, showCancelButton: true, confirmButtonColor: '#015957',
      preConfirm: function () { return {n: $('#lN').val(), c: $('#lC').val()}; }}).then(function (r) {
      if (!r.value) return;
      PM.post('ajax/manage.php', {action: 'library_update', id: $t.data('id'), name: r.value.n, category: r.value.c}).done(function () { location.reload(); }).fail(PM.error);
    });
  });
});
</script>
JS;
include __DIR__ . '/layouts/footer.php';
