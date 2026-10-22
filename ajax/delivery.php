<?php
/** Delivery estimate for a city (product page). */
require __DIR__ . '/_init.php';
$city = mb_substr(input('city'), 0, 100);
$q = shipping_quote($city, '', (float) input_float('subtotal', 0), 'standard', max(0, min(60, input_int('lead'))));
$cod = payment_method_get('cod');
$codOk = $cod && payment_method_state($cod) === 'ready' && $q['cod_available'];
json_response([
    'ok' => true,
    'message' => 'Delivery to ' . ($city ?: 'your city') . ': ' . ($q['fee'] > 0 ? money($q['fee']) : 'Free') . ' · arrives ' . $q['estimate']
        . ($q['fee'] > 0 && $q['free_threshold'] ? ' · free over ' . money($q['free_threshold']) : '') . ($codOk ? ' · Cash on Delivery available' : ' · Cash on Delivery not available'),
]);
