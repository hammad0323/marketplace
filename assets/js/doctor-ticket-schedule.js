(function ($) {
    'use strict';

    $('#save-ticket-schedule-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Saving…');
        var rows = [];
        $('#ticket-schedule-days [data-day]').each(function () {
            var $day = $(this);
            if (!$day.find('.ticket-day-enabled').is(':checked')) return;
            rows.push({
                day_of_week: parseInt($day.data('day'), 10),
                start_time: $day.find('.ticket-day-start').val(),
                end_time: $day.find('.ticket-day-end').val()
            });
        });
        $.post('/ajax/doctor-ticket-schedule-save.php', {
            csrf_token: window.APP.csrfToken,
            schedule: JSON.stringify(rows)
        }, null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Hours');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () {
            $btn.prop('disabled', false).text('Save Hours');
            showToast('error', 'Network error', 'Please try again.');
        });
    });
})(jQuery);
