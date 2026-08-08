<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();

$stmt = mysqli_prepare($db, 'SELECT is_premium FROM doctors WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$isPremium = (bool) (mysqli_stmt_get_result($stmt)->fetch_assoc()['is_premium'] ?? 0);
mysqli_stmt_close($stmt);

if (!$isPremium) {
    json_response(false, [], 'Selling products/services is a Premium feature. Contact us to upgrade.');
}

$id = (int) ($_POST['id'] ?? 0);
$type = ($_POST['type'] ?? 'product') === 'service' ? 'service' : 'product';
$name = clean($_POST['name'] ?? '');
$categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
$description = clean($_POST['description'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$stock = $type === 'product' ? (int) ($_POST['stock'] ?? 0) : null;
$durationLabel = $type === 'service' ? clean($_POST['duration_label'] ?? '') : null;
$isActive = !empty($_POST['is_active']) ? 1 : 0;
$metaTitle = clean($_POST['meta_title'] ?? '') ?: null;
$metaDescription = clean($_POST['meta_description'] ?? '') ?: null;

$errors = [];
if ($name === '' || mb_strlen($name) < 3) {
    $errors['name'] = 'Please enter a name (at least 3 characters).';
}
if ($price < 0) {
    $errors['price'] = 'Price cannot be negative.';
}
if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$imagePath = null;
if (!empty($_FILES['image']['name'])) {
    [$ok, $result] = handle_upload('image', 'products', ['jpg', 'jpeg', 'png', 'webp'], 4 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $imagePath = $result;
}

if ($id > 0) {
    // Editing: must own the product.
    $stmt = mysqli_prepare($db, 'SELECT image FROM doctor_products WHERE id = ? AND doctor_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $doctorId);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$existing) {
        json_response(false, [], 'Listing not found.');
    }
    if ($imagePath === null) {
        $imagePath = $existing['image'];
    }
    $stmt = mysqli_prepare($db, 'UPDATE doctor_products SET category_id=?, type=?, name=?, description=?, price=?, stock=?, duration_label=?, image=?, is_active=?, meta_title=?, meta_description=? WHERE id=? AND doctor_id=?');
    mysqli_stmt_bind_param($stmt, 'isssdississii', $categoryId, $type, $name, $description, $price, $stock, $durationLabel, $imagePath, $isActive, $metaTitle, $metaDescription, $id, $doctorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(true, ['id' => $id], 'Listing updated.');
}

$slug = unique_slug($db, 'doctor_products', $name);
$stmt = mysqli_prepare($db, 'INSERT INTO doctor_products (doctor_id, category_id, type, name, slug, description, price, stock, duration_label, image, is_active, meta_title, meta_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
mysqli_stmt_bind_param($stmt, 'iissssdississ', $doctorId, $categoryId, $type, $name, $slug, $description, $price, $stock, $durationLabel, $imagePath, $isActive, $metaTitle, $metaDescription);
mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

json_response(true, ['id' => $newId], 'Listing added.');
