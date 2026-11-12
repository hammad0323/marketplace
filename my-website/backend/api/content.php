<?php
// =========================================================
// GET /api/content.php
// Sends the services, skills and timeline from the database.
// Used by the Home page and the About page.
// =========================================================

require_once __DIR__ . '/db.php';
requireMethod('GET');

$db = getDB();

try {
    $services = $db->query('SELECT id, icon, title, text FROM services ORDER BY sort_order, id')->fetchAll();
    $skills   = $db->query('SELECT id, name, level FROM skills ORDER BY sort_order, id')->fetchAll();
    $timeline = $db->query('SELECT id, year, title, text FROM timeline ORDER BY sort_order, id')->fetchAll();
} catch (PDOException $e) {
    error_log('content.php: ' . $e->getMessage());
    sendJson(['error' => DEBUG ? $e->getMessage() : 'Could not load content.'], 500);
}

// MySQL gives numbers back as text ("75"), so turn level into a real number
foreach ($skills as &$skill) {
    $skill['level'] = (int) $skill['level'];
}
unset($skill);

sendJson([
    'services' => $services,
    'skills'   => $skills,
    'timeline' => $timeline,
]);
