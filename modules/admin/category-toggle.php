<?php
require_admin();
verify_csrf();

$request = find_category_request((int) $id);
if ($request) {
    set_category_request_enabled($request['id'], !$request['is_enabled']);
}

redirect('/admin/category-requests');
