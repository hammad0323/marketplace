/* Beglet admin behaviour */
(function ($) {
  'use strict';
  var A = window.ADMIN || {};
  $.ajaxSetup({ headers: { 'X-CSRF-Token': A.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

  // Confirm destructive actions
  $(document).on('submit', 'form[data-confirm]', function (e) {
    var form = this;
    if (form.dataset.confirmed) return;
    e.preventDefault();
    Swal.fire({ title: form.dataset.confirm, text: form.dataset.confirmText || '', icon: 'warning', showCancelButton: true, confirmButtonColor: '#214E9B', confirmButtonText: 'Yes, continue' })
      .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
  });

  // Colour inputs
  $(document).on('input', '[data-color-for]', function () { $('#' + $(this).data('color-for')).val(this.value.toUpperCase()); });
  $(document).on('input', '.color-input input[type=text]', function () { if (/^#[0-9a-f]{6}$/i.test(this.value)) $(this).siblings('[data-color-for]').val(this.value); });

  // Image preview
  $(document).on('change', 'input[type=file][data-preview]', function () {
    var f = this.files && this.files[0], $p = $(this).siblings('.img-preview');
    if (!f) return;
    if (!$p.length) $p = $('<img class="img-preview mt-2" style="max-width:160px;max-height:110px;border-radius:6px;object-fit:cover">').insertAfter(this);
    $p.attr('src', URL.createObjectURL(f));
  });

  // Slug auto-fill
  $(document).on('input', '[data-slug-source]', function () {
    var $t = $($(this).data('slug-source'));
    if ($t.data('touched')) return;
    $t.val(this.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120));
  });
  $(document).on('input', '[data-slug-target]', function () { $(this).data('touched', true); });

  // Character counters for SEO fields
  $('[data-count]').each(function () {
    var $i = $(this), max = parseInt($i.data('count'), 10), $c = $('<div class="form-text text-end"></div>').insertAfter($i);
    function u() { var n = $i.val().length; $c.text(n + ' / ' + max).toggleClass('text-danger', n > max); }
    $i.on('input', u); u();
  });

  // Sortable lists → POST order
  $('[data-sortable]').each(function () {
    var el = this;
    Sortable.create(el, { handle: '.drag-handle', animation: 180, ghostClass: 'sortable-ghost', onEnd: function () {
      var ids = $(el).children('[data-id]').map(function () { return $(this).data('id'); }).get();
      $.post(A.base + '/admin/ajax', { action: 'reorder', entity: $(el).data('sortable'), ids: ids, csrf_token: A.csrf })
        .done(function (r) { Swal.fire({ toast: true, position: 'bottom-end', timer: 1800, showConfirmButton: false, icon: 'success', title: r.message || 'Order saved' }); })
        .fail(function (x) { Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'Could not save order', 'error'); });
    } });
  });

  // Repeater rows (homepage benefits etc.)
  $(document).on('click', '[data-repeater-add]', function () {
    var $wrap = $($(this).data('repeater-add')), $tpl = $wrap.find('template'), max = parseInt($wrap.data('max'), 10) || 10;
    var count = $wrap.find('.repeater-row').length;
    if (count >= max) { Swal.fire('Limit reached', 'You can add up to ' + max + ' items.', 'info'); return; }
    $wrap.find('.repeater-rows').append($tpl.html().replace(/__i__/g, Date.now()));
  });
  $(document).on('click', '[data-repeater-remove]', function () { $(this).closest('.repeater-row').remove(); });

  // Variant rows
  $(document).on('click', '[data-variant-add]', function () {
    var tpl = $('#variantTpl').html().replace(/__i__/g, 'n' + Date.now());
    $('#variantRows').append(tpl);
  });
  $(document).on('click', '[data-variant-remove]', function () { $(this).closest('tr').remove(); });

  // Bulk select
  $(document).on('change', '[data-check-all]', function () { $($(this).data('check-all')).prop('checked', this.checked); });

  // Generic AJAX toggles (status switches)
  $(document).on('change', '[data-toggle-url]', function () {
    var $c = $(this);
    $.post(A.base + '/admin/ajax', { action: 'toggle', entity: $c.data('entity'), id: $c.data('id'), field: $c.data('field'), value: this.checked ? 1 : 0, csrf_token: A.csrf })
      .done(function (r) { Swal.fire({ toast: true, position: 'bottom-end', timer: 1500, showConfirmButton: false, icon: 'success', title: r.message || 'Saved' }); })
      .fail(function (x) { $c.prop('checked', !$c.prop('checked')); Swal.fire('Error', (x.responseJSON && x.responseJSON.message) || 'Could not save', 'error'); });
  });

  // Simple client-side filter for long checkbox/option lists
  $(document).on('input', '[data-filter-list]', function () {
    var q = this.value.toLowerCase();
    $($(this).data('filter-list')).find('[data-filter-item]').each(function () { $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1); });
  });
})(jQuery);
