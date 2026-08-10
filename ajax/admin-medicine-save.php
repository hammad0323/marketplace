<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$id = (int) ($_POST['id'] ?? 0);
$fields = [
    'name' => clean($_POST['name'] ?? ''),
    'generic_name' => clean($_POST['generic_name'] ?? '') ?: null,
    'category' => clean($_POST['category'] ?? '') ?: null,
    'composition' => clean($_POST['composition'] ?? '') ?: null,
    'dosage' => clean($_POST['dosage'] ?? '') ?: null,
    'side_effects' => clean($_POST['side_effects'] ?? '') ?: null,
    'uses' => clean($_POST['uses'] ?? '') ?: null,
    'precautions' => clean($_POST['precautions'] ?? '') ?: null,
    'content' => $_POST['content'] ?? '',
    'focus_keyword' => clean($_POST['focus_keyword'] ?? '') ?: null,
    'meta_title' => clean($_POST['meta_title'] ?? '') ?: null,
    'meta_description' => clean($_POST['meta_description'] ?? '') ?: null,
    'status' => ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
];

if ($fields['name'] === '' || mb_strlen($fields['name']) < 2) {
    json_response(false, ['errors' => ['name' => 'Please enter the medicine name.']], 'Please fix the errors below.');
}
if (trim(strip_tags($fields['content'])) === '') {
    json_response(false, ['errors' => ['content' => 'Please add a description.']], 'Please fix the errors below.');
}

$seoScore = seo_score_for_medicine($fields);
$authorId = (int) $_SESSION['user_id'];

$featuredImage = null;
if (!empty($_FILES['featured_image']['name'])) {
    [$ok, $result] = handle_upload('featured_image', 'medicines', ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $featuredImage = $result;
}

if ($id > 0) {
    $stmt = mysqli_prepare($db, 'SELECT featured_image FROM medicine_info WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$existing) {
        json_response(false, [], 'Medicine entry not found.');
    }
    if ($featuredImage === null) {
        $featuredImage = $existing['featured_image'];
    }

    $stmt = mysqli_prepare($db, 'UPDATE medicine_info SET name=?, generic_name=?, category=?, composition=?, dosage=?, side_effects=?, uses=?, precautions=?, content=?, featured_image=?, focus_keyword=?, meta_title=?, meta_description=?, seo_score=?, status=? WHERE id=?');
    mysqli_stmt_bind_param(
        $stmt, 'sssssssssssssisi',
        $fields['name'], $fields['generic_name'], $fields['category'], $fields['composition'], $fields['dosage'],
        $fields['side_effects'], $fields['uses'], $fields['precautions'], $fields['content'], $featuredImage,
        $fields['focus_keyword'], $fields['meta_title'], $fields['meta_description'], $seoScore, $fields['status'], $id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    log_activity($authorId, 'admin', 'update_medicine_info', "Updated medicine info #$id: {$fields['name']}");
    json_response(true, ['id' => $id, 'seo_score' => $seoScore], 'Medicine entry updated.');
}

$slug = unique_slug($db, 'medicine_info', $fields['name']);
$stmt = mysqli_prepare($db, 'INSERT INTO medicine_info (author_id, name, slug, generic_name, category, composition, dosage, side_effects, uses, precautions, content, featured_image, focus_keyword, meta_title, meta_description, seo_score, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
mysqli_stmt_bind_param(
    $stmt, 'issssssssssssssis',
    $authorId, $fields['name'], $slug, $fields['generic_name'], $fields['category'], $fields['composition'],
    $fields['dosage'], $fields['side_effects'], $fields['uses'], $fields['precautions'], $fields['content'],
    $featuredImage, $fields['focus_keyword'], $fields['meta_title'], $fields['meta_description'], $seoScore, $fields['status']
);
mysqli_stmt_execute($stmt);
$newId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

log_activity($authorId, 'admin', 'create_medicine_info', "Created medicine info #$newId: {$fields['name']}");
json_response(true, ['id' => $newId, 'seo_score' => $seoScore], 'Medicine entry created.');
