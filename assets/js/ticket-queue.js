(function ($) {
    'use strict';
    var $tbody = $('#ticket-queue-tbody');
    if (!$tbody.length) return;

    var doctorId = window.TICKET_QUEUE_DOCTOR_ID;
    var pollTimer = null;

    function currentDate() {
        return $('#queue-date-input').val();
    }

    var STATUS_LABELS = { waiting: 'Waiting', serving: 'Serving', completed: 'Completed', no_show: 'No-show', cancelled: 'Cancelled' };
    var STATUS_CLASSES = { waiting: 'pending', serving: 'active', completed: 'verified', no_show: 'rejected', cancelled: 'suspended' };

    function renderRows(tickets) {
        if (!tickets.length) {
            $tbody.html('<tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px;">No tickets for this date yet.</td></tr>');
            return;
        }
        var html = '';
        tickets.forEach(function (t) {
            html += '<tr data-ticket-id="' + t.id + '">'
                + '<td style="font-weight:700;">' + t.number + '</td>'
                + '<td>' + $('<div>').text(t.name).html() + '</td>'
                + '<td>' + (t.phone ? $('<div>').text(t.phone).html() : '<span style="color:var(--color-text-muted);">—</span>') + '</td>'
                + '<td><span class="status-pill status-' + (STATUS_CLASSES[t.status] || 'pending') + '">' + (STATUS_LABELS[t.status] || t.status) + '</span></td>'
                + '<td style="text-transform:capitalize;color:var(--color-text-muted);font-size:13px;">' + t.added_by + '</td>'
                + '<td style="white-space:nowrap;">';
            if (t.status === 'waiting' || t.status === 'serving') {
                html += '<button type="button" class="btn btn-outline btn-sm btn-ticket-status" data-status="no_show">No-show</button> '
                    + '<button type="button" class="btn btn-danger btn-sm btn-ticket-status" data-status="cancelled">Cancel</button>';
            } else {
                html += '<span style="color:var(--color-text-muted);font-size:12px;">—</span>';
            }
            html += '</td></tr>';
        });
        $tbody.html(html);
    }

    function refresh() {
        $.getJSON('/ajax/doctor-ticket-list.php', { date: currentDate() }, function (res) {
            if (!res.success) return;
            $('#now-serving-number').text(res.current_serving);
            $('#now-serving-name').text(res.now_serving_name || (res.current_serving > 0 ? '' : 'No tickets called yet'));
            $('#last-number-display').text(res.last_number);
            renderRows(res.tickets);
        });
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(refresh, 12000);
    }

    refresh();
    startPolling();

    $('#queue-date-input').on('change', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('date', currentDate());
        window.location.href = url.toString();
    });

    $('#call-next-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $.post('/ajax/doctor-ticket-advance.php', { csrf_token: window.APP.csrfToken, date: currentDate() }, null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    showToast('success', 'Next', res.message);
                    refresh();
                } else {
                    showToast('error', 'Could not advance', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $tbody.on('click', '.btn-ticket-status', function () {
        var ticketId = $(this).closest('tr').data('ticket-id');
        var status = $(this).data('status');
        $.post('/ajax/doctor-ticket-status.php', { csrf_token: window.APP.csrfToken, ticket_id: ticketId, status: status }, null, 'json')
            .done(function (res) {
                if (res.success) refresh();
                else showToast('error', 'Could not update', res.message);
            });
    });

    var $walkinModal = $('#walkin-modal');
    $('#add-walkin-btn').on('click', function () {
        $walkinModal.addClass('open');
        document.body.style.overflow = 'hidden';
    });
    $walkinModal.on('click', '[data-modal-close]', function () {
        $walkinModal.removeClass('open');
        document.body.style.overflow = '';
    });
    $walkinModal.on('click', function (e) { if (e.target === this) $(this).removeClass('open'); });

    $('#walkin-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.form-group.error').removeClass('error');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Adding…');
        $.post('/ajax/doctor-ticket-add-walkin.php', $form.serialize() + '&date=' + encodeURIComponent(currentDate()), null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false).text('Add to Queue');
                if (res.success) {
                    showToast('success', 'Added', res.message);
                    $walkinModal.removeClass('open');
                    document.body.style.overflow = '';
                    $form[0].reset();
                    refresh();
                } else {
                    if (res.errors) {
                        Object.keys(res.errors).forEach(function (f) {
                            $form.find('[data-field="' + f + '"]').addClass('error').find('.form-error').text(res.errors[f]);
                        });
                    }
                    showToast('error', 'Could not add', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Add to Queue');
                showToast('error', 'Network error', 'Please try again.');
            });
    });
})(jQuery);
