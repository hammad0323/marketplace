(function ($) {
    'use strict';
    var $widget = $('#booking-widget');
    if (!$widget.length) return;

    var doctorId = $widget.data('doctor-id');
    var selectedDate = null, selectedType = 'online', selectedSlot = null;

    function buildCalendar() {
        var $cal = $('#booking-calendar').empty();
        var today = new Date();
        for (var i = 0; i < 21; i++) {
            var d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + i);
            var iso = d.toISOString().slice(0, 10);
            var $day = $('<div class="calendar-day has-slots"></div>')
                .text(d.getDate())
                .attr('data-date', iso)
                .attr('title', d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }));
            if (i === 0) $day.addClass('today');
            $cal.append($day);
        }
    }

    function loadSlots(date) {
        var $slots = $('#slot-grid').html('<div class="skeleton" style="height:44px;grid-column:1/-1;"></div>');
        $.getJSON('/ajax/check-availability.php', { doctor_id: doctorId, date: date, type: selectedType }, function (res) {
            $slots.empty();
            selectedSlot = null;
            $('#confirm-booking-btn').prop('disabled', true);
            if (!res.success) {
                $slots.html('<p style="color:var(--color-text-muted);grid-column:1/-1;">' + res.message + '</p>');
                return;
            }
            if (res.blocked) {
                $slots.html('<p style="color:var(--color-danger);grid-column:1/-1;">' + (res.message || 'Doctor unavailable this day.') + '</p>');
                return;
            }
            if (!res.slots.length) {
                $slots.html('<p style="color:var(--color-text-muted);grid-column:1/-1;">No slots available this day. Try another date.</p>');
                return;
            }
            res.slots.forEach(function (s) {
                var $btn = $('<button type="button" class="slot-btn"></button>').text(s.label)
                    .attr('data-start', s.start).attr('data-end', s.end);
                if (!s.available) $btn.prop('disabled', true);
                $slots.append($btn);
            });
        }).fail(function () {
            $slots.html('<p style="color:var(--color-danger);grid-column:1/-1;">Could not load availability. Please try again.</p>');
        });
    }

    buildCalendar();

    $(document).on('click', '#booking-calendar .calendar-day', function () {
        $('#booking-calendar .calendar-day').removeClass('selected');
        $(this).addClass('selected');
        selectedDate = $(this).data('date');
        $('#selected-date-label').text($(this).attr('title'));
        loadSlots(selectedDate);
    });

    $(document).on('click', '.consult-type-toggle button', function () {
        $('.consult-type-toggle button').removeClass('btn-primary').addClass('btn-outline');
        $(this).removeClass('btn-outline').addClass('btn-primary');
        selectedType = $(this).data('type');
        if (selectedDate) loadSlots(selectedDate);
    });

    $(document).on('click', '.slot-btn:not(:disabled)', function () {
        $('.slot-btn').removeClass('selected');
        $(this).addClass('selected');
        selectedSlot = { start: $(this).data('start'), end: $(this).data('end') };
        $('#confirm-booking-btn').prop('disabled', false);
    });

    $('#booking-form').on('submit', function (e) {
        e.preventDefault();
        if (!window.APP.loggedIn) {
            openAuthModal('login');
            return;
        }
        if (!selectedDate || !selectedSlot) {
            showToast('warning', 'Pick a time', 'Please select a date and time slot first.');
            return;
        }
        var $btn = $('#confirm-booking-btn').prop('disabled', true).text('Booking…');
        $.post('/ajax/book-appointment.php', {
            csrf_token: window.APP.csrfToken,
            doctor_id: doctorId,
            date: selectedDate,
            start_time: selectedSlot.start,
            end_time: selectedSlot.end,
            consultation_type: selectedType,
            reason: $('#booking-reason').val()
        }, null, 'json').done(function (res) {
            if (res.success) {
                showToast('success', 'Booked!', res.message);
                setTimeout(function () { window.location.href = res.redirect || '/patient/appointments'; }, 900);
            } else {
                showToast('error', 'Could not book', res.message);
                $btn.prop('disabled', false).text('Confirm Booking');
                if (selectedDate) loadSlots(selectedDate);
            }
        }).fail(function () {
            showToast('error', 'Network error', 'Please try again.');
            $btn.prop('disabled', false).text('Confirm Booking');
        });
    });
})(jQuery);
