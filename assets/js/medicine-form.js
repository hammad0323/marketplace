/**
 * Shared add/edit/delete logic for the medicine-info form, used by both
 * admin/medicines.php and doctor/medicines.php. Each page sets
 * window.MEDICINE_SAVE_URL / window.MEDICINE_DELETE_URL, and provides
 * window.MEDICINE_RICH_FIELDS[id] = {content,uses,dosage,side_effects,precautions}
 * and window.MEDICINE_FAQS[id] = [{question,answer}, ...] before this loads.
 */
(function ($) {
    'use strict';

    var $modal = $('#medicine-modal');
    var $form = $('#medicine-form');
    var RICH_FIELDS = ['content', 'uses', 'dosage', 'side_effects', 'precautions'];
    var updateScore = null;
    var faqRowId = 0;

    function fieldSelector(field) {
        return '#medicine-' + field.replace(/_/g, '-');
    }

    function setEditorContent(field, html) {
        var target = fieldSelector(field);
        var host = document.querySelector('[data-rich-editor][data-target="' + target + '"]');
        if (host && host.richEditorSetContent) {
            host.richEditorSetContent(html || '<p></p>');
        } else {
            $(target).val(html || '');
        }
    }

    function setAllEditors(data) {
        RICH_FIELDS.forEach(function (field) {
            setEditorContent(field, (data || {})[field]);
        });
        if (updateScore) updateScore();
    }

    // ---- FAQ rows ---------------------------------------------------------------
    function addFaqRow(question, answer) {
        var id = 'faq-row-' + (faqRowId++);
        var $row = $(
            '<div class="card" style="padding:14px;margin-bottom:10px;" id="' + id + '">'
            + '<div style="display:flex;gap:10px;align-items:flex-start;">'
            + '<div style="flex:1;">'
            + '<input type="text" class="form-control" name="faq_question[]" placeholder="Question" style="margin-bottom:8px;">'
            + '<textarea class="form-control" name="faq_answer[]" rows="2" placeholder="Answer"></textarea>'
            + '</div>'
            + '<button type="button" class="btn-icon btn-remove-faq-row" style="width:32px;height:32px;flex-shrink:0;"><i class="ri-delete-bin-line"></i></button>'
            + '</div></div>'
        );
        $row.find('input[name="faq_question[]"]').val(question || '');
        $row.find('textarea[name="faq_answer[]"]').val(answer || '');
        $('#medicine-faq-list').append($row);
    }
    function clearFaqRows() {
        $('#medicine-faq-list').empty();
    }
    function setFaqRows(faqs) {
        clearFaqRows();
        (faqs || []).forEach(function (f) { addFaqRow(f.question, f.answer); });
    }
    $('#add-faq-row-btn').on('click', function () { addFaqRow('', ''); });
    $(document).on('click', '.btn-remove-faq-row', function () { $(this).closest('.card').remove(); });

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
        setAllEditors({});
        setFaqRows([]);
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
        $('#medicine-keyword').val(d.focusKeyword);
        $('#medicine-meta-title').val(d.metaTitle);
        $('#medicine-meta-description').val(d.metaDescription);
        $('#medicine-status').val(d.status);
        setAllEditors((window.MEDICINE_RICH_FIELDS || {})[id]);
        setFaqRows((window.MEDICINE_FAQS || {})[id]);
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
