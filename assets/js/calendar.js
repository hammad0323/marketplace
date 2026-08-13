/* Wanderly — click-to-toggle availability calendar (provider dashboard). */
(function ($) {
  'use strict';
  var $grid = $('#calendar-grid');
  if (!$grid.length) return;

  var serviceId = $grid.data('service');
  var csrf = $grid.data('csrf');

  $grid.on('click', '.cal-day:not([disabled])', function () {
    var $day = $(this);
    var date = $day.data('date');
    $day.css('transform', 'scale(0.9)');

    $.post(window.appUrl('/ajax/toggle-availability.php'), { service_id: serviceId, date: date, csrf_token: csrf })
      .done(function (res) {
        if (res.ok) {
          if (res.status === 'blocked') {
            $day.css({ background: '#FEE2E2', color: '#991B1B' });
          } else {
            $day.css({ background: '#fff', color: 'var(--ink)' });
          }
          $day.css('transform', 'scale(1)');
        } else {
          showToast(res.error || 'Could not update that date.', 'danger');
          $day.css('transform', 'scale(1)');
        }
      })
      .fail(function () {
        showToast('Could not update that date.', 'danger');
        $day.css('transform', 'scale(1)');
      });
  });
})(jQuery);
