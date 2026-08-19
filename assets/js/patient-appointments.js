(function ($) {
    'use strict';

    var $rescheduleModal = $('#reschedule-modal');
    var activeAppt = null, selectedDate = null, selectedSlot = null;

    $(document).on('click', '.btn-cancel', function () {
        if (!confirm('Cancel this appointment?')) return;
        var apptId = $(this).closest('[data-appt-id]').data('appt-id');
        var $card = $(this).closest('[data-appt-id]');
        $.post('/ajax/cancel-appointment.php', { csrf_token: window.APP.csrfToken, appointment_id: apptId }, null, 'json')
            .done(function (res) {
                if (res.success) {
                    showToast('success', 'Cancelled', res.message);
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    showToast('error', 'Could not cancel', res.message);
                }
            }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });

    $(document).on('click', '.btn-reschedule', function () {
        var $card = $(this).closest('[data-appt-id]');
        activeAppt = { id: $card.data('appt-id'), doctorId: $(this).data('doctor-id'), type: $(this).data('type') };
        selectedDate = null; selectedSlot = null;
        $('#confirm-reschedule-btn').prop('disabled', true);
        $('#reschedule-slots').empty();
        $('#reschedule-calendar').removeData('selected-date');
        buildCalendar();
        $rescheduleModal.addClass('open');
        document.body.style.overflow = 'hidden';
    });

    $rescheduleModal.on('click', '[data-modal-close]', closeModal);
    $rescheduleModal.on('click', function (e) { if (e.target === this) closeModal(); });
    function closeModal() {
        $rescheduleModal.removeClass('open');
        document.body.style.overflow = '';
    }

    function loadRescheduleSlots(date, title) {
        selectedDate = date;
        $('#reschedule-date-label').text(title);
        var $slots = $('#reschedule-slots').html('<div class="skeleton" style="height:44px;grid-column:1/-1;"></div>');
        $.getJSON('/ajax/check-availability.php', { doctor_id: activeAppt.doctorId, date: selectedDate, type: activeAppt.type }, function (res) {
            $slots.empty();
            selectedSlot = null;
            $('#confirm-reschedule-btn').prop('disabled', true);
            if (!res.success || res.blocked || !res.slots.length) {
                $slots.html('<p style="color:var(--color-text-muted);grid-column:1/-1;">No slots available this day.</p>');
                return;
            }
            res.slots.forEach(function (s) {
                var $btn = $('<button type="button" class="slot-btn"></button>').text(s.label).attr('data-start', s.start).attr('data-end', s.end);
                if (!s.available) $btn.prop('disabled', true);
                $slots.append($btn);
            });
        });
    }

    function buildCalendar() {
        var calendar = window.buildMonthCalendar($('#reschedule-calendar'), {
            onSelect: loadRescheduleSlots
        });
        calendar.selectToday();
    }

    $(document).on('click', '#reschedule-slots .slot-btn:not(:disabled)', function () {
        $('#reschedule-slots .slot-btn').removeClass('selected');
        $(this).addClass('selected');
        selectedSlot = { start: $(this).data('start'), end: $(this).data('end') };
        $('#confirm-reschedule-btn').prop('disabled', false);
    });

    $('#confirm-reschedule-btn').on('click', function () {
        if (!selectedDate || !selectedSlot) return;
        var $btn = $(this).prop('disabled', true).text('Saving…');
        $.post('/ajax/reschedule-appointment.php', {
            csrf_token: window.APP.csrfToken, appointment_id: activeAppt.id,
            date: selectedDate, start_time: selectedSlot.start, end_time: selectedSlot.end
        }, null, 'json').done(function (res) {
            if (res.success) {
                showToast('success', 'Rescheduled', res.message);
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                showToast('error', 'Could not reschedule', res.message);
                $btn.prop('disabled', false).text('Confirm New Time');
            }
        }).fail(function () {
            showToast('error', 'Network error', 'Please try again.');
            $btn.prop('disabled', false).text('Confirm New Time');
        });
    });
})(jQuery);
