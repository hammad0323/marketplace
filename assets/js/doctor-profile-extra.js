(function ($) {
    'use strict';

    $('#privacy-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/doctor-privacy-update.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Privacy Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () {
            $btn.prop('disabled', false).text('Save Privacy Settings');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

    $('#chat-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/doctor-chat-settings.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Messaging Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () {
            $btn.prop('disabled', false).text('Save Messaging Settings');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

    // ---- SEO meta title/description auto-fill --------------------------------
    // Live-suggests text from name/designation/qualification/specialization
    // as those fields change. "Dirty" tracking (not just "is it empty")
    // matters here: an emptiness check alone would freeze after the very
    // first keystroke in Full Name, since that already makes the title
    // non-empty. Instead each SEO field only stops updating once the doctor
    // actually types into *that* field themselves — programmatic .val()
    // writes don't fire 'input', so this cleanly tells the two apart.
    var $seoTitle = $('#doctor-meta-title-field');
    if ($seoTitle.length && window.buildDoctorSeoText) {
        var $seoDesc = $('#doctor-meta-description-field');
        var titleDirty = $seoTitle.val().trim() !== '';
        var descDirty = $seoDesc.val().trim() !== '';
        $seoTitle.on('input', function () { titleDirty = true; });
        $seoDesc.on('input', function () { descDirty = true; });

        var refreshDoctorSeo = function () {
            var specNames = $('#doctor-specializations-field input:checked').map(function () {
                return $(this).closest('label').text().trim();
            }).get().join(', ');
            var seo = window.buildDoctorSeoText(
                $('#doctor-full-name-field').val(),
                $('#doctor-designation-field').val(),
                $('#doctor-qualification-field').val(),
                specNames,
                window.APP.siteName
            );
            if (!titleDirty) $seoTitle.val(seo.title);
            if (!descDirty) $seoDesc.val(seo.description);
        };
        $('#doctor-full-name-field, #doctor-designation-field, #doctor-qualification-field').on('input', refreshDoctorSeo);
        $('#doctor-specializations-field').on('change', 'input[type="checkbox"]', refreshDoctorSeo);
        refreshDoctorSeo();
    }

})(jQuery);
