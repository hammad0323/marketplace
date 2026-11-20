<?php
/**
 * Design Studio: artwork uploads, background removal, saving designs, adding them to the bag
 * and quote requests. Prices are always recalculated here from the saved design.
 */
require __DIR__ . '/_init.php';

if (!design_enabled()) {
    json_response(['ok' => false, 'message' => 'The Design Studio is currently closed.'], 404);
}
ajax_require_post();
$owner = design_owner_key();
if (!rate_limit('designer:' . client_ip(), 90, 60)) {
    json_response(['ok' => false, 'message' => 'Too many requests. Please slow down.'], 429);
}

/** Validates and saves the posted design; replies with the errors when it is invalid. */
function designer_save_posted(string $owner): array
{
    $v = design_validate(input_int('product_id'), input('color', '', 'post'), (string) ($_POST['elements'] ?? ''), $owner);
    if (!$v['ok']) {
        json_response(['ok' => false, 'message' => $v['errors'][0], 'errors' => $v['errors']], 422);
    }
    return design_save($v, $owner, $_POST['preview_front'] ?? null, $_POST['preview_back'] ?? null);
}

/** {size: qty} from the posted sizes[] map, limited to the blank's sizes. */
function designer_sizes(): array
{
    $out = [];
    foreach ((array) ($_POST['sizes'] ?? []) as $size => $qty) {
        if (is_scalar($qty) && preg_match('/^[A-Z0-9]{1,5}$/', (string) $size) && (int) $qty > 0) {
            $out[(string) $size] = min(UC_CART_MAX_QTY, (int) $qty);
        }
    }
    return $out;
}

try {
    switch (input('action', '', 'post')) {
        case 'upload':
            if (!rate_limit('designer-upload:' . $owner, 40, 3600)) {
                json_response(['ok' => false, 'message' => 'Upload limit reached. Please try again later.'], 429);
            }
            $source = null;
            if (input('source', '', 'post') !== '') {
                $source = design_asset(input('source', '', 'post'), $owner);
                if (!$source) {
                    json_response(['ok' => false, 'message' => 'Original image not found.'], 404);
                }
            }
            $path = upload_image('file', 3000);
            if (!$path) {
                json_response(['ok' => false, 'message' => 'Choose an image to upload.'], 422);
            }
            json_response(['ok' => true, 'asset' => design_asset_save($path, $owner, $source !== null, $source ? (int) $source['id'] : null)]);
            // no break: json_response exits
        case 'remove_bg':
            $asset = design_asset(input('asset', '', 'post'), $owner);
            if (!$asset) {
                json_response(['ok' => false, 'message' => 'Image not found.'], 404);
            }
            if (!rate_limit('designer-ai:' . $owner, (int) setting('design_ai_daily_limit', 15), 86400)) {
                json_response(['ok' => false, 'message' => 'Daily limit for automatic background removal reached. Use Quick remove.'], 429);
            }
            json_response(['ok' => true, 'asset' => design_remove_bg_ai($asset, $owner)]);
            // no break
        case 'price':
            $v = design_validate(input_int('product_id'), input('color', '', 'post'), (string) ($_POST['elements'] ?? ''), $owner);
            json_response(['ok' => $v['ok'], 'errors' => $v['errors'], 'price' => $v['ok'] ? design_price($v['elements']) : null]);
            // no break
        case 'add':
            if (design_mode() !== 'auto') {
                json_response(['ok' => false, 'message' => 'Please request a quote for your design.'], 422);
            }
            $design = designer_save_posted($owner);
            $res = cart_add_design($design, designer_sizes());
            $res['count'] = cart_count();
            $res['print_price'] = (float) $design['print_price'];
            json_response($res, $res['ok'] ? 200 : 422);
            // no break
        case 'request':
            if (!rate_limit('designer-request:' . client_ip(), 6, 3600)) {
                json_response(['ok' => false, 'message' => 'Too many requests. Please try again later.'], 429);
            }
            if (input('website', '', 'post') !== '') { // honeypot
                json_response(['ok' => false, 'message' => 'Request could not be sent.'], 422);
            }
            $name = mb_substr(input('name', '', 'post'), 0, 120);
            $phone = mb_substr(preg_replace('/[^0-9+ \-]/', '', input('phone', '', 'post')), 0, 30);
            $email = input('email', '', 'post');
            $city = mb_substr(input('city', '', 'post'), 0, 100);
            $errors = [];
            if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
            if (strlen(preg_replace('/\D/', '', $phone)) < 10) $errors[] = 'Please enter a valid phone number.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
            if ($city === '') $errors[] = 'Please enter your city.';
            $sizes = designer_sizes();
            if (!$sizes) $errors[] = 'Choose at least one size and quantity.';
            if ($errors) {
                json_response(['ok' => false, 'message' => $errors[0], 'errors' => $errors], 422);
            }
            $design = designer_save_posted($owner);
            $ref = design_request_reference();
            $id = db_insert('design_requests', [
                'reference' => $ref, 'access_token' => random_token(20), 'design_id' => (int) $design['id'], 'customer_id' => customer_id(),
                'name' => $name, 'phone' => $phone, 'email' => $email ?: null, 'city' => $city, 'sizes' => json_encode($sizes), 'qty' => array_sum($sizes),
                'notes' => mb_substr(input('notes', '', 'post'), 0, 1000) ?: null, 'estimate_print' => (float) $design['print_price'], 'ip_address' => client_ip(),
            ]);
            db_exec("UPDATE designs SET status = 'requested' WHERE id = ?", [(int) $design['id']]);
            $_SESSION['my_designs'][] = $ref;
            $r = db_row('SELECT * FROM design_requests WHERE id = ?', [$id]);
            if ($to = setting('design_notify_email', setting('store_email'))) {
                send_mail($to, 'New design request ' . $ref, mail_layout('New design request', '<p>' . e($name) . ' (' . e($phone) . ', ' . e($city) . ') sent a design for ' . (int) $r['qty'] . ' piece(s).</p><p><a href="' . e(url('admin/designs/view.php?id=' . $id)) . '">Open in admin</a></p>'));
            }
            mail_design_quote($r, 'We received your design', 'Thank you! Our team will check your design and confirm the final price shortly. You can follow it using the link below.');
            json_response(['ok' => true, 'reference' => $ref, 'redirect' => design_request_link($r) . '&new=1']);
            // no break
        case 'quote_add':
            $r = design_request_for_viewer(input('reference', '', 'post'), input('t', '', 'post'));
            if (!$r || $r['status'] !== 'quoted') {
                json_response(['ok' => false, 'message' => 'This quote is not available to order.'], 404);
            }
            $design = db_row('SELECT * FROM designs WHERE id = ?', [(int) $r['design_id']]);
            $design['spec_data'] = json_decode((string) $design['spec'], true) ?: [];
            $res = cart_add_design($design, json_decode((string) $r['sizes'], true) ?: [], $r);
            $res['count'] = cart_count();
            json_response($res, $res['ok'] ? 200 : 422);
    }
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
json_response(['ok' => false, 'message' => 'Unknown action.'], 400);
