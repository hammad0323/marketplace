(function ($) {
    'use strict';

    var $qty = $('#checkout-qty');
    var $total = $('#checkout-total');
    if ($qty.length && $total.length) {
        var unitPrice = parseFloat($total.data('unit-price')) || 0;
        $qty.on('input change', function () {
            var qty = Math.max(1, parseInt($qty.val(), 10) || 1);
            $total.text('$' + (unitPrice * qty).toFixed(2));
        });
    }

    $('#checkout-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $('#place-order-btn').prop('disabled', true).text('Placing order…');
        $.post('/ajax/product-request.php', $form.serialize(), null, 'json').done(function (res) {
            if (res.success) {
                $form.hide();
                $('#checkout-success').fadeIn(200);
            } else {
                $btn.prop('disabled', false).text('Place Order');
                showToast('error', 'Could not place order', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Place Order');
            showToast('error', 'Network error', 'Please try again.');
        });
    });
})(jQuery);
