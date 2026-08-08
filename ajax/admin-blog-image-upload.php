<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

[$ok, $result] = handle_upload('image', 'blog', ['jpg', 'jpeg', 'png', 'webp', 'gif'], 5 * 1024 * 1024);
if (!$ok) {
    json_response(false, [], $result);
}

json_response(true, ['url' => '/uploads/' . $result]);
