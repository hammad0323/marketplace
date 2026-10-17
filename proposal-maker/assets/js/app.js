/* Proposal Maker — shared admin JS */
(function ($) {
    'use strict';

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': PM.csrf, 'X-Requested-With': 'XMLHttpRequest' } });

    PM.url = function (path) { return PM.base + '/' + String(path).replace(/^\//, ''); };

    PM.toast = function (msg, icon) {
        Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: msg, showConfirmButton: false, timer: 2200, timerProgressBar: true });
    };

    PM.error = function (xhr, fallback) {
        var msg = fallback || 'Something went wrong.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        else if (typeof xhr === 'string') msg = xhr;
        Swal.fire({ icon: 'error', title: 'Oops', text: msg, confirmButtonColor: '#015957' });
    };

    PM.confirm = function (opts) {
        return Swal.fire($.extend({
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, continue',
            confirmButtonColor: '#015957', cancelButtonColor: '#94a3b8', reverseButtons: true
        }, opts));
    };

    PM.post = function (path, data) {
        return $.ajax({ url: PM.url(path), method: 'POST', data: data, dataType: 'json' });
    };

    PM.copy = function (text) {
        var done = function () { PM.toast('Link copied to clipboard'); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(); });
        } else { fallback(); }
        function fallback() {
            var t = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo('body');
            t[0].select(); document.execCommand('copy'); t.remove(); done();
        }
    };

    /* ---------- proposal row actions (dashboard + list + detail) ---------- */
    $(document).on('click', '[data-act]', function (e) {
        var $b = $(this), act = $b.data('act'), id = $b.data('id');
        if (act === 'duplicate') {
            e.preventDefault();
            PM.confirm({ title: 'Duplicate proposal?', text: 'A copy will be created as a new draft.', icon: 'question' }).then(function (r) {
                if (!r.isConfirmed) return;
                PM.post('ajax/proposals.php', { action: 'duplicate', id: id }).done(function (res) {
                    PM.toast('Proposal duplicated');
                    setTimeout(function () { location.href = PM.url('editor.php?id=' + res.id); }, 600);
                }).fail(PM.error);
            });
        } else if (act === 'delete') {
            e.preventDefault();
            PM.confirm({ title: 'Delete proposal?', text: 'This permanently removes the proposal and its history.', confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' }).then(function (r) {
                if (!r.isConfirmed) return;
                PM.post('ajax/proposals.php', { action: 'delete', id: id }).done(function () {
                    PM.toast('Proposal deleted');
                    var $row = $b.closest('tr');
                    if ($row.length) $row.fadeOut(250, function () { $row.remove(); });
                    else setTimeout(function () { location.href = PM.url('proposals.php'); }, 600);
                }).fail(PM.error);
            });
        } else if (act === 'share') {
            e.preventDefault();
            PM.share($b.data('link'), $b.data('id'), $b.data('email'), $b.data('title'));
        } else if (act === 'copy') {
            e.preventDefault();
            PM.copy($b.data('link'));
        }
    });

    PM.share = function (link, id, email, title) {
        Swal.fire({
            title: 'Share proposal',
            html: '<p class="text-muted mb-2" style="font-size:13px">Anyone with this link can view the proposal.</p>' +
                '<div class="share-box"><input class="form-control" readonly value="' + $('<div>').text(link).html() + '" id="shareLink"></div>' +
                '<div class="d-grid gap-2 mt-3">' +
                '<button class="btn btn-brand" id="shCopy"><i class="bi bi-clipboard"></i> Copy public link</button>' +
                '<a class="btn btn-light border" href="' + PM.url('proposal.php?id=' + id + '#send') + '"><i class="bi bi-envelope"></i> Send by email</a>' +
                '<a class="btn btn-light border" target="_blank" href="mailto:' + (email || '') + '?subject=' + encodeURIComponent(title || 'Proposal') + '&body=' + encodeURIComponent('Please review our proposal:\n\n' + link) + '"><i class="bi bi-box-arrow-up-right"></i> Open email client</a>' +
                '<a class="btn btn-light border" href="' + PM.url('pdf.php?id=' + id) + '"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>' +
                '<button class="btn btn-light border" id="shSent"><i class="bi bi-send-check"></i> Mark as sent</button>' +
                '</div>',
            showConfirmButton: false, showCloseButton: true,
            didOpen: function () {
                $('#shCopy').on('click', function () { PM.copy(link); });
                $('#shSent').on('click', function () {
                    PM.post('ajax/proposals.php', { action: 'status', id: id, status: 'sent' }).done(function () { PM.toast('Marked as sent'); setTimeout(function () { location.reload(); }, 700); }).fail(PM.error);
                });
            }
        });
    };

    $(function () {
        $('[data-bs-toggle="tooltip"]').each(function () { new bootstrap.Tooltip(this); });
        // color pickers synced with hex input
        $(document).on('input', '.color-field input[type=color]', function () { $(this).siblings('input[type=text]').val(this.value.toUpperCase()).trigger('change'); });
        $(document).on('change keyup', '.color-field input[type=text]', function () {
            var v = this.value.trim(); if (/^#?[0-9a-f]{6}$/i.test(v)) { if (v[0] !== '#') v = '#' + v; $(this).siblings('input[type=color]').val(v.toLowerCase()); }
        });
    });
})(jQuery);
