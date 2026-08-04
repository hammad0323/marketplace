<?php
mp_require_admin();
mp_verify_csrf();

$request = mp_find_category_request((int) $id);
if ($request) {
    mp_set_category_request_enabled($request['id'], !$request['is_enabled']);
}

mp_redirect('/admin/category-requests');
