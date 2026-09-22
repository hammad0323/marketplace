(function ($) {
    'use strict';
    var $widget = $('#ticket-widget');
    if (!$widget.length) return;

    var doctorId = $widget.data('doctor-id');
    var selectedDate = null;
    var dayIsOpen = false;

    window.buildMonthCalendar($('#ticket-calendar'), {
        onSelect: function (iso, title) {
            selectedDate = iso;
            checkDay(iso, title);
        }
    });

    function checkDay(date, title) {
        var $status = $('#ticket-day-status').html('<div class="skeleton" style="height:44px;"></div>');
        $('#ticket-form').hide();
        dayIsOpen = false;
        $.getJSON('/ajax/check-ticket-day.php', { doctor_id: doctorId, date: date }, function (res) {
            if (!res.success) {
                $status.html('<p style="color:var(--color-text-muted);font-size:13px;">' + res.message + '</p>');
                return;
            }
            if (!res.open) {
                $status.html('<p style="color:var(--color-danger);font-size:13px;">' + res.message + '</p>');
                return;
            }
            dayIsOpen = true;
            var html = '<div class="card" style="padding:12px;font-size:13px;">'
                + '<strong>' + title + '</strong><br>'
                + '<span style="color:var(--color-text-muted);">Queue hours: ' + res.start_time + ' – ' + res.end_time + '</span>';
            if (res.last_number > 0) {
                html += '<br><span style="color:var(--color-text-muted);">' + res.last_number + ' ticket' + (res.last_number === 1 ? '' : 's') + ' booked so far'
                    + (res.current_serving > 0 ? ' — now serving #' + res.current_serving : '') + '</span>';
            } else {
                html += '<br><span style="color:var(--color-primary);font-weight:600;">You\'d be ticket #1</span>';
            }
            html += '</div>';
            $status.html(html);
            $('#ticket-form').show();
        }).fail(function () {
            $status.html('<p style="color:var(--color-danger);font-size:13px;">Could not check this date. Please try again.</p>');
        });
    }

    function submitTicket() {
        var $btn = $('#confirm-ticket-btn').prop('disabled', true).text('Booking…');
        $.post('/ajax/book-ticket.php', {
            csrf_token: window.APP.csrfToken,
            doctor_id: doctorId,
            date: selectedDate,
            notes: $('#ticket-notes').val()
        }, null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Get My Ticket');
            if (res.success) {
                showToast('success', 'Ticket booked!', res.message);
                setTimeout(function () { window.location.href = '/patient/tickets'; }, 1200);
            } else {
                showToast('error', 'Could not book', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Get My Ticket');
            showToast('error', 'Network error', 'Please try again.');
        });
    }

    $('#ticket-form').on('submit', function (e) {
        e.preventDefault();
        if (!selectedDate || !dayIsOpen) {
            showToast('warning', 'Pick a date', 'Please select an open date first.');
            return;
        }
        if (!window.APP.loggedIn) {
            if (typeof window.openGuestModal === 'function') {
                openGuestModal(function () { submitTicket(); });
            } else {
                openAuthModal('login');
            }
            return;
        }
        submitTicket();
    });
})(jQuery);
