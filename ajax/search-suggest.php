<?php
require __DIR__ . '/../config/config.php';

$q = trim(clean($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    json_response(true, ['doctors' => [], 'specializations' => []]);
}

$like = '%' . $q . '%';
$db = db();

$stmt = mysqli_prepare($db, "
    SELECT u.full_name, u.avatar, d.slug,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS spec_names
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
      AND (u.full_name LIKE ? OR d.qualification LIKE ? OR d.id IN (
          SELECT ds.doctor_id FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE s.name LIKE ?
      ))
    ORDER BY d.is_premium DESC, d.rating_avg DESC
    LIMIT 6
");
mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
mysqli_stmt_execute($stmt);
$doctors = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
foreach ($doctors as &$d) {
    $d['avatar'] = avatar_url($d['avatar'], $d['full_name']);
}
unset($d);

$stmt = mysqli_prepare($db, "SELECT name, slug FROM specializations WHERE is_active = 1 AND name LIKE ? ORDER BY name LIMIT 5");
mysqli_stmt_bind_param($stmt, 's', $like);
mysqli_stmt_execute($stmt);
$specializations = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

json_response(true, ['doctors' => $doctors, 'specializations' => $specializations]);
