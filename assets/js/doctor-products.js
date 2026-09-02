(function ($) {
    'use strict';

    var SAVE_URL = window.PRODUCT_SAVE_URL || '/ajax/doctor-product-save.php';
    var DELETE_URL = window.PRODUCT_DELETE_URL || '/ajax/doctor-product-delete.php';
    var ORDER_UPDATE_URL = window.ORDER_UPDATE_URL || '/ajax/doctor-order-update.php';

    var $modal = $('#product-modal');
    var $form = $('#product-form');

    function openModal(title) {
        $('#product-modal-title').text(title);
        $modal.addClass('open');
    }
    function closeModal() {
        $modal.removeClass('open');
        $form[0].reset();
        $('#product-id').val('');
        toggleTypeFields();
    }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    function toggleTypeFields() {
        var type = $form.find('input[name="type"]:checked').val();
        $('#stock-field').toggle(type === 'product');
        $('#duration-field').toggle(type === 'service');
    }
    $form.on('change', 'input[name="type"]', toggleTypeFields);

    $('#add-product-btn').on('click', function () {
        $form[0].reset();
        $('#product-id').val('');
        toggleTypeFields();
        openModal('Add Product / Service');
    });

    $(document).on('click', '.btn-edit-product', function () {
        var d = $(this).data();
        $form[0].reset();
        $('#product-id').val(d.id);
        $form.find('input[name="type"][value="' + d.type + '"]').prop('checked', true);
        $form.find('[name="name"]').val(d.name);
        $form.find('[name="category_id"]').val(d.category || '');
        $form.find('[name="description"]').val(d.description);
        $form.find('[name="price"]').val(d.price);
        $form.find('[name="stock"]').val(d.stock !== 'null' ? d.stock : '');
        $form.find('[name="duration_label"]').val(d.duration);
        $form.find('[name="is_active"]').prop('checked', String(d.active) === '1');
        $('#product-meta-title').val(d.metaTitle);
        $('#product-meta-description').val(d.metaDescription);
        toggleTypeFields();
        openModal('Edit Product / Service');
    });

    $(document).on('click', '.btn-delete-product', function () {
        if (!confirm('Remove this listing? This cannot be undone.')) return;
        var id = $(this).data('id');
        var $card = $(this).closest('[data-product-id]');
        $.post(DELETE_URL, { csrf_token: window.APP.csrfToken, id: id }, null, 'json')
            .done(function (res) {
                if (res.success) { $card.fadeOut(200, function () { $(this).remove(); }); showToast('success', 'Removed', res.message); }
                else showToast('error', 'Could not remove', res.message);
            });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData($form[0]);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.ajax({ url: SAVE_URL, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                $btn.prop('disabled', false).text('Save Listing');
                if (res.success) {
                    showToast('success', 'Saved', res.message);
                    closeModal();
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    showToast('error', 'Could not save', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Save Listing');
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $(document).on('click', '.btn-order-action', function () {
        var $btn = $(this);
        var action = $btn.data('action');
        var $row = $btn.closest('[data-order-id]');
        var id = $row.data('order-id');
        $btn.closest('div').find('button').prop('disabled', true);
        $.post(ORDER_UPDATE_URL, { csrf_token: window.APP.csrfToken, id: id, status: action }, null, 'json')
            .done(function (res) {
                if (res.success) {
                    showToast('success', 'Updated', res.message);
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    showToast('error', 'Could not update', res.message);
                    $btn.closest('div').find('button').prop('disabled', false);
                }
            }).fail(function () {
                showToast('error', 'Network error', 'Please try again.');
                $btn.closest('div').find('button').prop('disabled', false);
            });
    });
})(jQuery);
