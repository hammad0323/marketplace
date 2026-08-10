/* Wanderly — live animated price preview on the service booking widget. */
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
      $.get('/ajax/booking-price-preview.php?' + params)
        .done(function (res) {
          if (!res || !res.ok) { $preview.hide(); return; }
          animateValue($('#pv-base'), '$' + res.base.toFixed(2));
          animateValue($('#pv-fee'), '$' + res.fee.toFixed(2));
          animateValue($('#pv-tax'), '$' + res.tax.toFixed(2));
          animateValue($('#pv-total'), '$' + res.total.toFixed(2));
          $preview.slideDown(150);
        })
        .fail(function () { $preview.hide(); });
    }, 300);
  }

  $form.on('input change', 'input, select', updatePreview);
})(jQuery);
