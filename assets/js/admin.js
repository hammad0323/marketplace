/* Urban Collections — admin interactions */
(function ($) {
    'use strict';
    $.ajaxSetup({ headers: { 'X-CSRF-Token': UCA.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

    // confirm before destructive actions
    $(document).on('submit', 'form[data-confirm]', function (e) {
        var f = this;
        if (f.dataset.ok) { return; }
        e.preventDefault();
        Swal.fire({ text: $(f).data('confirm'), icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: '#111' })
            .then(function (r) { if (r.isConfirmed) { f.dataset.ok = 1; f.submit(); } });
    });

    // colour inputs kept in sync with text fields
    $(document).on('input', '[data-color-sync]', function () { $($(this).data('color-sync')).val(this.value.toUpperCase()); });
    $(document).on('input', '.a-color input[name]', function () { if (/^#[0-9a-f]{6}$/i.test(this.value)) { $(this).siblings('[data-color-sync]').val(this.value); } });

    // image preview on file select
    $(document).on('change', 'input[type=file][data-preview]', function () {
        var f = this.files && this.files[0], $t = $(this).closest('.a-image-field').find('.a-thumb');
        if (f && /^image\//.test(f.type)) { var r = new FileReader(); r.onload = function (e) { $t.html('<img src="' + e.target.result + '" alt="">'); }; r.readAsDataURL(f); }
    });

    // rich text editor
    if ($.fn.summernote) {
        $('textarea[data-editor]').summernote({
            height: 260,
            toolbar: [['style', ['style']], ['font', ['bold', 'italic', 'underline', 'clear']], ['para', ['ul', 'ol', 'paragraph']], ['insert', ['link', 'table', 'hr']], ['view', ['codeview']]],
            styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote']
        });
    }

    // searchable ordered pickers
    $('[data-picker]').each(function () {
        var $p = $(this), name = $p.data('name'), $chosen = $p.find('.a-picker-chosen'), $res = $p.find('.a-picker-results'), $search = $p.find('[data-picker-search]');
        function mark() {
            var ids = $chosen.find('li').map(function () { return String($(this).data('id')); }).get();
            $res.find('button').each(function () { $(this).toggleClass('is-chosen', ids.indexOf(String($(this).data('id'))) > -1); });
        }
        $search.on('focus input', function () {
            var q = this.value.toLowerCase();
            $p.addClass('is-searching');
            $res.find('button').each(function () { $(this).toggle(String($(this).data('label')).toLowerCase().indexOf(q) > -1); });
        });
        $(document).on('click', function (e) { if (!$.contains($p[0], e.target)) { $p.removeClass('is-searching'); } });
        $res.on('click', 'button', function () {
            var id = $(this).data('id'), label = $(this).data('label');
            $chosen.append($('<li>').attr('data-id', id).append($('<input type=hidden>').attr('name', name).val(id), $('<span>').text(label),
                '<button type="button" data-up title="Move up"><i class="bi bi-arrow-up"></i></button><button type="button" data-remove title="Remove"><i class="bi bi-x"></i></button>'));
            mark();
        });
        $chosen.on('click', '[data-remove]', function () { $(this).closest('li').remove(); mark(); });
        $chosen.on('click', '[data-up]', function () { var $li = $(this).closest('li'); $li.prev().before($li); });
        mark();
    });

    // slug auto-fill
    $(document).on('input', '[data-slug-source]', function () {
        var $t = $($(this).data('slug-source'));
        if ($t.data('touched')) { return; }
        $t.val(this.value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
    });
    $(document).on('input', '[data-slug-target]', function () { $(this).data('touched', true); });

    // select-all checkboxes
    $(document).on('change', '[data-check-all]', function () { $($(this).data('check-all')).prop('checked', this.checked); });

    // remember active tab
    var tabKey = 'uca-tab-' + location.pathname;
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) { try { sessionStorage.setItem(tabKey, $(e.target).attr('href')); } catch (x) {} });
    try { var t = sessionStorage.getItem(tabKey); if (t && $('a[href="' + t + '"]').length && !location.hash) { bootstrap.Tab.getOrCreateInstance($('a[href="' + t + '"]')[0]).show(); } } catch (x) {}
    if (location.hash && $('a[data-bs-toggle="tab"][href="' + location.hash + '"]').length) { bootstrap.Tab.getOrCreateInstance($('a[href="' + location.hash + '"]')[0]).show(); }

    // warn about unsaved changes on long forms
    $('form[data-dirty-check]').each(function () {
        var $f = $(this), dirty = false;
        $f.on('change input', function () { dirty = true; }).on('submit', function () { dirty = false; });
        $(window).on('beforeunload', function () { if (dirty) { return 'You have unsaved changes.'; } });
    });
})(jQuery);
