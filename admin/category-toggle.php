<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();
mp_verify_csrf();

$request = mp_find_category_request((int) ($_GET['id'] ?? 0));
if ($request) {
    mp_set_category_request_enabled($request['id'], !$request['is_enabled']);
}

mp_redirect('category-requests.php');
