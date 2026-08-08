(function ($) {
    'use strict';

    var $modal = $('#post-modal');
    var $form = $('#post-form');
    var $editorHost = $('[data-rich-editor]')[0];

    function setEditorContent(html) {
        if ($editorHost && $editorHost.richEditorSetContent) {
            $editorHost.richEditorSetContent(html || '<p></p>');
        } else {
            $('#post-content').val(html || '');
        }
    }

    function openModal(title) {
        $('#post-modal-title').text(title);
        $modal.addClass('open');
    }
    function closeModal() {
        $modal.removeClass('open');
    }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    $('#add-post-btn').on('click', function () {
        $form[0].reset();
        $('#post-id').val('0');
        setEditorContent('<p></p>');
        openModal('New Post');
    });

    $(document).on('click', '.btn-edit-post', function () {
        var $row = $(this).closest('[data-post-id]');
        var id = $row.data('post-id');
        $form[0].reset();
        $('#post-id').val(id);
        $('#post-title').val($row.data('title'));
        $('#post-excerpt').val($row.data('excerpt'));
        $('#post-status').val($row.data('status'));
        $('#post-meta-title').val($row.data('meta-title'));
        $('#post-meta-description').val($row.data('meta-description'));
        setEditorContent((window.BLOG_POST_CONTENT || {})[id]);
        openModal('Edit Post');
    });

    $(document).on('click', '.btn-delete-post', function () {
        if (!confirm('Delete this blog post? This cannot be undone.')) return;
        var $row = $(this).closest('[data-post-id]');
        $.post('/ajax/admin-blog-delete.php', { csrf_token: window.APP.csrfToken, id: $row.data('post-id') }, null, 'json')
            .done(function (res) {
                if (res.success) { $row.fadeOut(200, function () { $(this).remove(); }); showToast('success', 'Deleted', res.message); }
                else showToast('error', 'Could not delete', res.message);
            });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData($form[0]);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.ajax({ url: '/ajax/admin-blog-save.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                $btn.prop('disabled', false).text('Save Post');
                if (res.success) {
                    showToast('success', 'Saved', res.message);
                    closeModal();
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    showToast('error', 'Could not save', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Save Post');
                showToast('error', 'Network error', 'Please try again.');
            });
    });
})(jQuery);
