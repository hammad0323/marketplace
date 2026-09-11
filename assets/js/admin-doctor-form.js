/**
 * Add/Edit Doctor modal on admin/doctors.php — full profile + weekly
 * availability, mirroring what a doctor can set for themselves. Edit mode
 * fetches the doctor's current data via ajax/admin-doctor-get.php since the
 * page doesn't preload every doctor's full profile/schedule up front.
 */
(function ($) {
    'use strict';

    var $modal = $('#doctor-modal');
    var $form = $('#doctor-form');

    function openModal(title) {
        $('#doctor-modal-title').text(title);
        $modal.addClass('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        $modal.removeClass('open');
        document.body.style.overflow = '';
    }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    function clearErrors() {
        $form.find('.form-group.error').removeClass('error');
        $form.find('.form-error').text('');
    }

    function resetAvailability() {
        $('#admin-availability-days .avail-enabled').prop('checked', false);
    }
    function setAvailabilitySchedule(schedule) {
        resetAvailability();
        (schedule || []).forEach(function (row) {
            var $block = $('#admin-availability-days .availability-day-card[data-day="' + row.day_of_week + '"] .avail-block[data-type="' + row.consultation_type + '"]');
            $block.find('.avail-enabled').prop('checked', true);
            $block.find('.avail-start').val(row.start_time);
            $block.find('.avail-end').val(row.end_time);
            $block.find('.avail-duration').val(row.slot_duration_mins);
        });
    }

    $('#add-doctor-btn').on('click', function () {
        $form[0].reset();
        clearErrors();
        $('#doctor-id').val('0');
        $('#doctor-password-label').text('Password');
        $('#doctor-password').attr('placeholder', 'Leave blank to auto-generate');
        openModal('Add Doctor');
    });

    $(document).on('click', '.btn-edit-doctor', function () {
        var id = $(this).closest('[data-doctor-id]').data('doctor-id');
        $.getJSON('/ajax/admin-doctor-get.php', { id: id }).done(function (res) {
            if (!res.success) { showToast('error', 'Could not load', res.message); return; }
            $form[0].reset();
            clearErrors();
            var d = res.doctor;
            $('#doctor-id').val(id);
            $('#doctor-full-name').val(d.full_name);
            $('#doctor-email').val(d.email);
            $('#doctor-phone').val(d.phone);
            $('#doctor-password').val('').attr('placeholder', 'Leave blank to keep current password');
            $('#doctor-password-label').text('Reset Password');
            $('#doctor-qualification').val(d.qualification);
            $('#doctor-registration-number').val(d.registration_number);
            $('#doctor-experience-years').val(d.experience_years);
            $('#doctor-bio').val(d.bio);
            $('#doctor-fee-online').val(d.consultation_fee_online);
            $('#doctor-fee-physical').val(d.consultation_fee_physical);
            $('#doctor-free-consultation').prop('checked', !!parseInt(d.free_consultation, 10));
            $('#doctor-clinic-name').val(d.clinic_name);
            $('#doctor-clinic-address').val(d.clinic_address);
            $('#doctor-clinic-city').val(d.clinic_city);
            $('#doctor-clinic-state').val(d.clinic_state);
            $('#doctor-clinic-country').val(d.clinic_country);
            $('#doctor-meta-title').val(d.meta_title);
            $('#doctor-meta-description').val(d.meta_description);

            $('#doctor-specializations input[type="checkbox"]').prop('checked', false);
            (res.specialization_ids || []).forEach(function (sid) {
                $('#doctor-specializations input[value="' + sid + '"]').prop('checked', true);
            });

            setAvailabilitySchedule(res.schedule);
            openModal('Edit Doctor');
        }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        clearErrors();
        var schedule = window.collectAvailabilitySchedule('#admin-availability-days');
        var formData = new FormData($form[0]);
        formData.append('schedule', JSON.stringify(schedule));
        var $btn = $('#doctor-form-submit').prop('disabled', true).text('Saving…');
        $.ajax({ url: '/ajax/admin-doctor-save.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                $btn.prop('disabled', false).text('Save Doctor');
                if (res.success) {
                    showToast('success', 'Saved', res.message);
                    closeModal();
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    if (res.errors) {
                        Object.keys(res.errors).forEach(function (f) {
                            var $g = $form.find('[data-field="' + f + '"]');
                            $g.addClass('error');
                            $g.find('.form-error').text(res.errors[f]);
                        });
                    }
                    showToast('error', 'Could not save', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Save Doctor');
                showToast('error', 'Network error', 'Please try again.');
            });
    });
})(jQuery);
