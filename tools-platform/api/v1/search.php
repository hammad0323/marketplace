<?php
/**
 * api/v1/search.php — AJAX search suggestions (also the seed of the
 * future public /api/v1/ surface described in the spec, section 79).
 * Matches tool name, short/full description, category name and tags.
 */
require __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$sql = "SELECT t.id, t.name, t.slug, t.short_description, t.icon, t.tool_type, c.name AS category_name
        FROM tools t
        JOIN categories c ON c.id = t.category_id
        LEFT JOIN tool_tags tt ON tt.tool_id = t.id
        LEFT JOIN tags tg ON tg.id = tt.tag_id
        WHERE t.status = 'published'
        AND (t.name LIKE ? OR t.short_description LIKE ? OR t.description LIKE ? OR c.name LIKE ? OR tg.name LIKE ?)
        GROUP BY t.id
        ORDER BY t.is_popular DESC, t.views DESC
        LIMIT 10";

$results = tp_query($sql, 'sssss', [$like, $like, $like, $like, $like]);

echo json_encode(['results' => $results, 'query' => $q]);
