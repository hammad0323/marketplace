/* Ebaya admin helpers */
(function ($) {
  'use strict';
  $.ajaxSetup({ headers: { 'X-CSRF-Token': EBA.csrf, 'X-Requested-With': 'XMLHttpRequest' } });
  // Colour pickers synced with hex text inputs
  $(document).on('input', 'input[type=color][data-sync]', function () { $($(this).data('sync')).val(this.value.toUpperCase()); });
  $(document).on('input', '.input-group input[pattern]', function () {
    if (/^#[0-9a-f]{6}$/i.test(this.value)) $(this).siblings('input[type=color]').val(this.value);
  });
  // Image previews
  $(document).on('change', 'input[type=file]', function () {
    var f = this.files && this.files[0], $img = $('[data-preview-for="' + this.id + '"]');
    if (f && $img.length && /^image\//.test(f.type)) { var r = new FileReader(); r.onload = function (e) { $img.attr('src', e.target.result); }; r.readAsDataURL(f); }
  });
  // Confirm dangerous actions
  $(document).on('submit', 'form[data-confirm]', function (e) {
    var f = this;
    if (f.dataset.ok) return;
    e.preventDefault();
    Swal.fire({ title: f.dataset.confirm, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, continue', confirmButtonColor: '#354638' })
      .then(function (r) { if (r.isConfirmed) { f.dataset.ok = 1; f.submit(); } });
  });
  // Slug from name
  $(document).on('input', '[data-slug-source]', function () {
    var $t = $($(this).data('slug-source'));
    if ($t.data('touched')) return;
    $t.val(this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
  });
  $(document).on('input', '[data-slug-target]', function () { $(this).data('touched', true); });
  // Sortable lists → hidden order inputs / AJAX
  if (window.Sortable) {
    document.querySelectorAll('[data-sortable]').forEach(function (el) {
      Sortable.create(el, { handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost', onEnd: function () {
        var ids = [].map.call(el.querySelectorAll(':scope > [data-id]'), function (x) { return x.getAttribute('data-id'); });
        var url = el.getAttribute('data-sortable');
        if (url) $.post(url, { csrf_token: EBA.csrf, action: 'reorder', ids: ids }).done(function () {
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Order saved', showConfirmButton: false, timer: 1500 });
        });
        $(el).find('input[data-order]').each(function (i) { this.value = i; });
      } });
    });
  }
  // Tabs remember last
  var key = 'eba-tab-' + location.pathname;
  try { var t = sessionStorage.getItem(key); if (t && document.querySelector('[data-bs-target="' + t + '"]')) bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="' + t + '"]')).show(); } catch (e) {}
  $(document).on('shown.bs.tab', '[data-bs-toggle=tab]', function () { try { sessionStorage.setItem(key, $(this).data('bs-target')); } catch (e) {} });
  // Select all checkboxes
  $(document).on('change', '[data-check-all]', function () { $($(this).data('check-all')).prop('checked', this.checked); });
})(jQuery);
