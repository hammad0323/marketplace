/**
 * Shared add/edit/delete logic for the medicine-info form, used by both
 * admin/medicines.php and doctor/medicines.php. Each page sets
 * window.MEDICINE_SAVE_URL / window.MEDICINE_DELETE_URL before this loads.
 */
(function ($) {
    'use strict';

    var $modal = $('#medicine-modal');
    var $form = $('#medicine-form');
    var $editorHost = $('[data-rich-editor]')[0];
    var updateScore = null;

    function setEditorContent(html) {
        if ($editorHost && $editorHost.richEditorSetContent) {
            $editorHost.richEditorSetContent(html || '<p></p>');
        } else {
            $('#medicine-content').val(html || '');
        }
        if (updateScore) updateScore();
    }

    function openModal(title) {
        $('#medicine-modal-title').text(title);
        $modal.addClass('open');
        if (updateScore) updateScore();
    }
    function closeModal() { $modal.removeClass('open'); }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    if (window.initSeoScore) {
        updateScore = window.initSeoScore({
            name: '#medicine-name', keyword: '#medicine-keyword',
            metaTitle: '#medicine-meta-title', metaDescription: '#medicine-meta-description',
            getContent: function () { return $('#medicine-content').val() + ' ' + $('#medicine-uses').val(); },
            watchExtra: '#medicine-uses',
            bar: '#medicine-seo-bar', label: '#medicine-seo-label',
        });
        $('#medicine-content').on('change', updateScore);
    }

    $('#add-medicine-btn').on('click', function () {
        $form[0].reset();
        $('#medicine-id').val('0');
        setEditorContent('<p></p>');
        openModal('Add Medicine');
    });

    $(document).on('click', '.btn-edit-medicine', function () {
        var $row = $(this).closest('[data-medicine-id]');
        var id = $row.data('medicine-id');
        var d = $row.data();
        $form[0].reset();
        $('#medicine-id').val(id);
        $('#medicine-name').val(d.name);
        $('#medicine-generic-name').val(d.genericName);
        $('#medicine-category').val(d.category);
        $('#medicine-composition').val(d.composition);
        $('#medicine-dosage').val(d.dosage);
        $('#medicine-side-effects').val(d.sideEffects);
        $('#medicine-uses').val(d.uses);
        $('#medicine-precautions').val(d.precautions);
        $('#medicine-keyword').val(d.focusKeyword);
        $('#medicine-meta-title').val(d.metaTitle);
        $('#medicine-meta-description').val(d.metaDescription);
        $('#medicine-status').val(d.status);
        setEditorContent((window.MEDICINE_CONTENT || {})[id]);
        openModal('Edit Medicine');
    });

    $(document).on('click', '.btn-delete-medicine', function () {
        if (!confirm('Delete this medicine entry? This cannot be undone.')) return;
        var $row = $(this).closest('[data-medicine-id]');
        $.post(window.MEDICINE_DELETE_URL, { csrf_token: window.APP.csrfToken, id: $row.data('medicine-id') }, null, 'json')
            .done(function (res) {
                if (res.success) { $row.fadeOut(200, function () { $(this).remove(); }); showToast('success', 'Deleted', res.message); }
                else showToast('error', 'Could not delete', res.message);
            });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData($form[0]);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.ajax({ url: window.MEDICINE_SAVE_URL, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                $btn.prop('disabled', false).text('Save Medicine');
                if (res.success) {
                    showToast('success', 'Saved', res.message);
                    closeModal();
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    showToast('error', 'Could not save', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Save Medicine');
                showToast('error', 'Network error', 'Please try again.');
            });
    });
})(jQuery);
