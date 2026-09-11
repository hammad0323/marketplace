/**
 * Appointment calendar on doctor/dashboard.php — marks days that have
 * appointments and, on click, lists which patient(s) and time(s) are
 * booked that day. Reuses the shared month calendar widget (calendar-widget.js).
 */
(function ($) {
    'use strict';

    var $calContainer = $('#dash-calendar');
    if (!$calContainer.length) return;

    var monthAppointments = {};
    var selectedIso = null;
    var selectedLabel = '';
    var calendar;

    function statusPillClass(status) {
        if (status === 'approved') return 'active';
        if (status === 'pending') return 'pending';
        return status;
    }
    function statusLabel(status) {
        return status.charAt(0).toUpperCase() + status.slice(1).replace('_', '-');
    }

    function renderDay(iso, label) {
        $('#dash-day-label').text(label || iso);
        var appts = monthAppointments[iso] || [];
        var $list = $('#dash-day-appointments').empty();
        if (!appts.length) {
            $list.append('<p style="color:var(--color-text-muted);font-size:13.5px;">No appointments this day.</p>');
            return;
        }
        appts.forEach(function (a) {
            var typeIcon = a.consultation_type === 'online' ? 'ri-video-chat-line' : 'ri-hospital-line';
            var $row = $(
                '<div class="card" style="padding:12px 16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;" data-appt-id="' + a.id + '">'
                + '<div><strong style="font-size:13.5px;"></strong>'
                + '<div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;"><i class="' + typeIcon + '"></i> ' + a.time + '</div></div>'
                + '<div style="display:flex;align-items:center;gap:8px;">'
                + '<span class="status-pill status-' + statusPillClass(a.status) + '">' + statusLabel(a.status) + '</span>'
                + (a.status === 'approved' ? '<button type="button" class="btn btn-ghost btn-sm btn-send-reminder" title="Email the patient a reminder"><i class="ri-mail-send-line"></i></button>' : '')
                + '</div></div>'
            );
            $row.find('strong').text(a.patient_name);
            $list.append($row);
        });
    }

    function loadMonth(year, month) {
        $.getJSON('/ajax/doctor-calendar-appointments.php', { year: year, month: month + 1 }, function (res) {
            if (!res.success) return;
            monthAppointments = res.appointments || {};
            calendar.setMarkedDates(res.counts || {});
            if (selectedIso) renderDay(selectedIso, selectedLabel);
        });
    }

    calendar = window.buildMonthCalendar($calContainer, {
        selectPast: true,
        maxMonthsBehind: 12,
        maxMonthsAhead: 6,
        onSelect: function (iso, title) {
            selectedIso = iso;
            selectedLabel = title;
            renderDay(iso, title);
        },
        onMonthChange: function (year, month) { loadMonth(year, month); }
    });

    var today = new Date();
    calendar.selectToday();
    loadMonth(today.getFullYear(), today.getMonth());

    $(document).on('click', '#dash-day-appointments .btn-send-reminder', function () {
        var $btn = $(this).prop('disabled', true);
        var appointmentId = $btn.closest('[data-appt-id]').data('appt-id');
        $.post('/ajax/doctor-send-reminder.php', { csrf_token: window.APP.csrfToken, appointment_id: appointmentId }, null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) showToast('success', 'Sent', res.message);
                else showToast('error', 'Could not send', res.message);
            }).fail(function () {
                $btn.prop('disabled', false);
                showToast('error', 'Network error', 'Please try again.');
            });
    });
})(jQuery);
