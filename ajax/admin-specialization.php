<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$op = $_POST['op'] ?? 'add';

if ($op === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $inUse = mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM doctors WHERE specialization_id = $id"))['c'];
    if ($inUse > 0) {
        json_response(false, [], "Cannot delete — $inUse doctor(s) use this specialization. Deactivate it instead.");
    }
    $stmt = mysqli_prepare($db, 'DELETE FROM specializations WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(true, [], 'Specialization deleted.');
}

if ($op === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    mysqli_query($db, "UPDATE specializations SET is_active = 1 - is_active WHERE id = $id");
    json_response(true, [], 'Status updated.');
}

$id = (int) ($_POST['id'] ?? 0);
$name = clean($_POST['name'] ?? '');
$icon = clean($_POST['icon'] ?? '') ?: 'ri-stethoscope-line';
$description = clean($_POST['description'] ?? '');

if ($name === '') {
    json_response(false, ['errors' => ['name' => 'Name is required.']], 'Please fix the errors below.');
}

if ($id > 0) {
    $stmt = mysqli_prepare($db, 'UPDATE specializations SET name = ?, icon = ?, description = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'sssi', $name, $icon, $description, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(true, [], 'Specialization updated.');
}

$slug = unique_slug($db, 'specializations', $name);
$stmt = mysqli_prepare($db, 'INSERT INTO specializations (name, slug, icon, description) VALUES (?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'ssss', $name, $slug, $icon, $description);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
json_response(true, [], 'Specialization added.');
