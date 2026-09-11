(function ($) {
    'use strict';

    $('#save-availability-btn').on('click', function () {
        var schedule = window.collectAvailabilitySchedule('#availability-days');
        var $btn = $(this).prop('disabled', true).text('Saving…');
        $.post('/ajax/doctor-availability-save.php', { csrf_token: window.APP.csrfToken, schedule: JSON.stringify(schedule) }, null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false).text('Save Schedule');
                if (res.success) showToast('success', 'Saved', res.message);
                else showToast('error', 'Could not save', res.message);
            }).fail(function () {
                $btn.prop('disabled', false).text('Save Schedule');
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $('#block-date-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        $.post('/ajax/doctor-blocked-date.php', $form.serialize() + '&op=add', null, 'json').done(function (res) {
            if (res.success) {
                showToast('success', 'Blocked', res.message);
                $('#no-blocked-msg').remove();
                $('#blocked-dates-list').append(
                    '<div class="card" style="padding:14px 18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;" data-block-id="' + res.id + '">' +
                    '<div><strong>' + res.date + '</strong>' + (res.reason ? ' — <span style="color:var(--color-text-muted);">' + res.reason + '</span>' : '') + '</div>' +
                    '<button type="button" class="btn-icon btn-remove-block" style="width:32px;height:32px;"><i class="ri-close-line"></i></button></div>'
                );
                $form[0].reset();
            } else {
                showToast('error', 'Could not block date', res.message);
            }
        }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });

    $(document).on('click', '.btn-remove-block', function () {
        var $card = $(this).closest('[data-block-id]');
        var id = $card.data('block-id');
        $.post('/ajax/doctor-blocked-date.php', { csrf_token: window.APP.csrfToken, op: 'remove', id: id }, null, 'json').done(function (res) {
            if (res.success) { $card.fadeOut(200, function () { $(this).remove(); }); }
        });
    });
})(jQuery);
