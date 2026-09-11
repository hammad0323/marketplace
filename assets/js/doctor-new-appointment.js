/**
 * "New Appointment" modal on doctor/appointments.php — lets a doctor book an
 * appointment on behalf of a patient (search existing, or add a new one on
 * the fly), reusing the same month calendar + slot-grid pattern as the
 * public booking widget so the doctor only ever picks a slot within their
 * own posted availability.
 */
(function ($) {
    'use strict';

    var $modal = $('#new-appointment-modal');
    if (!$modal.length) return;
    var $form = $('#new-appointment-form');

    var selectedDate = null, selectedType = 'online', selectedSlot = null;
    var calendar = null;

    function loadSlots(date) {
        var $slots = $('#na-slot-grid').html('<div class="skeleton" style="height:44px;grid-column:1/-1;"></div>');
        selectedSlot = null;
        $('#na-submit-btn').prop('disabled', true);
        $.getJSON('/ajax/check-availability.php', { doctor_id: window.NA_DOCTOR_ID, date: date, type: selectedType }, function (res) {
            $slots.empty();
            if (!res.success) { $slots.html('<p style="color:var(--color-text-muted);grid-column:1/-1;">' + res.message + '</p>'); return; }
            if (res.blocked) { $slots.html('<p style="color:var(--color-danger);grid-column:1/-1;">' + (res.message || 'Marked unavailable.') + '</p>'); return; }
            if (!res.slots.length) { $slots.html('<p style="color:var(--color-text-muted);grid-column:1/-1;">No slots this day. Try another date.</p>'); return; }
            res.slots.forEach(function (s) {
                var $btn = $('<button type="button" class="slot-btn"></button>').text(s.label).attr('data-start', s.start).attr('data-end', s.end);
                if (!s.available) $btn.prop('disabled', true);
                $slots.append($btn);
            });
        }).fail(function () { $slots.html('<p style="color:var(--color-danger);grid-column:1/-1;">Could not load availability.</p>'); });
    }

    function openModal() {
        $form[0].reset();
        $('#na-patient-id').val('0');
        $('#na-selected-patient').hide();
        $('#na-patient-search-wrap').show();
        $('#na-new-patient-fields').hide();
        $('#na-toggle-new-patient').text('+ Add a new patient instead');
        $('#na-patient-results').hide().empty();
        $('.na-consult-type-toggle button').removeClass('btn-primary').addClass('btn-outline');
        $('.na-consult-type-toggle button[data-type="online"]').removeClass('btn-outline').addClass('btn-primary');
        selectedType = 'online';
        selectedDate = null;
        selectedSlot = null;
        $('#na-slot-grid').empty();
        $('#na-submit-btn').prop('disabled', true);
        $form.find('.form-group.error').removeClass('error');
        $form.find('.form-error').text('');

        $modal.addClass('open');
        document.body.style.overflow = 'hidden';
        if (!calendar) {
            calendar = window.buildMonthCalendar($('#na-calendar'), {
                onSelect: function (iso) { selectedDate = iso; loadSlots(iso); }
            });
        }
        calendar.selectToday();
    }
    function closeModal() {
        $modal.removeClass('open');
        document.body.style.overflow = '';
    }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });
    $('#new-appointment-btn').on('click', openModal);

    $modal.on('click', '.slot-btn:not(:disabled)', function () {
        $('.slot-btn').removeClass('selected');
        $(this).addClass('selected');
        selectedSlot = { start: $(this).data('start'), end: $(this).data('end') };
        $('#na-submit-btn').prop('disabled', false);
    });

    $modal.on('click', '.na-consult-type-toggle button', function () {
        $('.na-consult-type-toggle button').removeClass('btn-primary').addClass('btn-outline');
        $(this).removeClass('btn-outline').addClass('btn-primary');
        selectedType = $(this).data('type');
        if (selectedDate) loadSlots(selectedDate);
    });

    // ---- Patient search -----------------------------------------------------
    var searchTimer = null;
    $modal.on('input', '#na-patient-search', function () {
        var q = $(this).val();
        clearTimeout(searchTimer);
        if (q.length < 2) { $('#na-patient-results').hide().empty(); return; }
        searchTimer = setTimeout(function () {
            $.getJSON('/ajax/doctor-patient-search.php', { q: q }, function (res) {
                var $results = $('#na-patient-results').empty();
                if (!res.patients.length) {
                    $results.append('<div style="padding:10px 14px;color:var(--color-text-muted);font-size:13px;">No patients found.</div>').show();
                    return;
                }
                res.patients.forEach(function (p) {
                    $('<div class="na-patient-result" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--color-border);"></div>')
                        .html('<strong>' + $('<div>').text(p.full_name).html() + '</strong><br><span style="font-size:12px;color:var(--color-text-muted);">'
                            + $('<div>').text(p.email || '').html() + (p.phone ? ' · ' + $('<div>').text(p.phone).html() : '') + '</span>')
                        .attr('data-id', p.id).attr('data-name', p.full_name)
                        .appendTo($results);
                });
                $results.show();
            });
        }, 300);
    });
    $modal.on('click', '.na-patient-result', function () {
        $('#na-patient-id').val($(this).data('id'));
        $('#na-selected-patient-name').text($(this).data('name'));
        $('#na-selected-patient').css('display', 'flex');
        $('#na-patient-search-wrap').hide();
        $('#na-patient-results').hide().empty();
        $('#na-patient-search').val('');
    });
    $modal.on('click', '#na-clear-patient', function () {
        $('#na-patient-id').val('0');
        $('#na-selected-patient').hide();
        $('#na-patient-search-wrap').show();
    });
    $modal.on('click', '#na-toggle-new-patient', function () {
        var showing = $('#na-new-patient-fields').is(':visible');
        $('#na-new-patient-fields').toggle(!showing);
        $(this).text(showing ? '+ Add a new patient instead' : '– Cancel new patient');
        if (!showing) {
            $('#na-patient-id').val('0');
            $('#na-selected-patient').hide();
            $('#na-patient-search-wrap').hide();
        } else {
            $('#na-patient-search-wrap').show();
        }
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        if (!selectedDate || !selectedSlot) {
            showToast('warning', 'Pick a time', 'Please select a date and time slot.');
            return;
        }
        $form.find('.form-group.error').removeClass('error');
        $form.find('.form-error').text('');
        var $btn = $('#na-submit-btn').prop('disabled', true).text('Scheduling…');
        $.post('/ajax/doctor-book-appointment.php', {
            csrf_token: window.APP.csrfToken,
            patient_id: $('#na-patient-id').val(),
            new_patient_name: $('#na-new-name').val(),
            new_patient_contact: $('#na-new-contact').val(),
            date: selectedDate,
            start_time: selectedSlot.start,
            end_time: selectedSlot.end,
            consultation_type: selectedType,
            reason: $('#na-reason').val()
        }, null, 'json').done(function (res) {
            $btn.text('Schedule Appointment');
            if (res.success) {
                showToast('success', 'Scheduled', res.message);
                closeModal();
                setTimeout(function () { window.location.reload(); }, 700);
            } else {
                $btn.prop('disabled', false);
                if (res.errors) {
                    Object.keys(res.errors).forEach(function (f) {
                        var $g = $form.find('[data-field="' + f + '"]');
                        $g.addClass('error');
                        $g.find('.form-error').text(res.errors[f]);
                    });
                }
                showToast('error', 'Could not schedule', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Schedule Appointment');
            showToast('error', 'Network error', 'Please try again.');
        });
    });
})(jQuery);
