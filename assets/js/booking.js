/* Toursity — live animated price preview on the service booking widget. */
(function ($) {
  'use strict';
  var $widget = $('#booking-widget');
  if (!$widget.length) return;

  var serviceId = $widget.data('service-id');
  var $form = $('#booking-form');
  var $preview = $('#price-preview');
  var debounceTimer = null;

  // Default check-out to +1 day after check-in for night/day pricing.
  $('#bk-date-from').on('change', function () {
    var $to = $('#bk-date-to');
    if ($to.length) {
      var next = new Date(this.value);
      next.setDate(next.getDate() + 1);
      var minStr = next.toISOString().slice(0, 10);
      $to.attr('min', minStr);
      if (!$to.val() || $to.val() < minStr) $to.val(minStr);
    }
    updatePreview();
  });

  function animateValue($el, newText) {
    $el.fadeOut(120, function () {
      $(this).text(newText).fadeIn(120);
    });
  }

  function updatePreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () {
      var params = $form.serialize() + '&service_id=' + serviceId;
      $.get(window.appUrl('/ajax/booking-price-preview.php') + '?' + params)
        .done(function (res) {
          if (!res || !res.ok) { $preview.hide(); return; }
          var cur = res.currency_symbol || '$';
          animateValue($('#pv-base'), cur + res.base.toFixed(2));
          animateValue($('#pv-fee'), cur + res.fee.toFixed(2));
          animateValue($('#pv-tax'), cur + res.tax.toFixed(2));
          animateValue($('#pv-total'), cur + res.total.toFixed(2));
          $preview.slideDown(150);
        })
        .fail(function () { $preview.hide(); });
    }, 300);
  }

  $form.on('input change', 'input, select', updatePreview);

  // ---- Add to Trip picker --------------------------------------------
  var $tripBtn = $('#add-to-trip-btn');
  var $tripPicker = $('#trip-picker');
  var csrfToken = $('meta[name="csrf-token"]').attr('content');

  $tripBtn.on('click', function (e) {
    e.stopPropagation();
    if (!csrfToken) {
      window.location.href = window.appUrl('/customer/login.php') + '?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
      return;
    }
    if ($tripPicker.hasClass('open')) {
      $tripPicker.removeClass('open');
      return;
    }
    $tripPicker.html('<div style="padding:16px;text-align:center;color:var(--ink-mute);font-size:13px;">Loading…</div>').addClass('open');
    $.get(window.appUrl('/ajax/add-to-trip.php')).done(function (res) {
      if (!res.ok) { $tripPicker.removeClass('open'); return; }
      var html = '<div style="padding:10px 12px;border-bottom:1px solid var(--border);font-weight:700;font-size:13px;">Add to which trip?</div>';
      if (res.trips.length) {
        res.trips.forEach(function (t) {
          html += '<a href="#" class="trip-pick" data-trip-id="' + t.id + '" style="display:block;padding:10px 12px;font-size:13.5px;">' + t.trip_name + '</a>';
        });
      } else {
        html += '<div style="padding:12px;font-size:13px;color:var(--ink-mute);">No trips yet.</div>';
      }
      html += '<a href="' + window.appUrl('/pages/trip-planner.php') + '" style="display:block;padding:10px 12px;font-size:13px;color:var(--purple-600);font-weight:600;border-top:1px solid var(--border);"><i class="bi bi-plus-lg"></i> Create a new trip</a>';
      $tripPicker.html(html);
    });
  });

  $(document).on('click', '.trip-pick', function (e) {
    e.preventDefault();
    var tripId = $(this).data('trip-id');
    $.post(window.appUrl('/ajax/add-to-trip.php'), { trip_id: tripId, service_id: serviceId, csrf_token: csrfToken })
      .done(function (res) {
        showToast(res.ok ? res.message : 'Could not add to trip.', res.ok ? 'success' : 'danger');
        $tripPicker.removeClass('open');
      });
  });
  $(document).on('click', function () { $tripPicker.removeClass('open'); });
})(jQuery);
