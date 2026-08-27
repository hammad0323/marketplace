<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_super_admin();
csrf_require();

$id = post_int('id');
$name = post('name');
$icon = post('icon', 'bi-tools');
$sortOrder = post_int('sort_order');
if (!$name) json_response(['success' => false, 'message' => 'Name is required.'], 422);
$slug = slugify($name);

if ($id) {
    db_exec("UPDATE tool_categories SET name=?, slug=?, icon=?, sort_order=? WHERE id=?", [$name, $slug, $icon, $sortOrder, $id]);
} else {
    $id = db_exec("INSERT INTO tool_categories (name, slug, icon, sort_order) VALUES (?,?,?,?)", [$name, $slug, $icon, $sortOrder]);
}
log_activity(null, current_user_id(), $id ? 'update' : 'create', 'tool_category', $id, "Saved category $name");
json_response(['success' => true]);
