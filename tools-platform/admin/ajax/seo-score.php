<?php
require __DIR__ . '/../../includes/config.php';
require_admin();

header('Content-Type: application/json');

$context = [
    'title' => (string) ($_POST['title'] ?? ''),
    'slug' => (string) ($_POST['slug'] ?? ''),
    'meta_description' => (string) ($_POST['meta_description'] ?? ''),
    'focus_keyword' => (string) ($_POST['focus_keyword'] ?? ''),
    'h1' => (string) ($_POST['h1'] ?? ''),
    'content_text' => (string) ($_POST['content_text'] ?? ''),
    'has_faq' => !empty($_POST['has_faq']),
    'has_examples' => !empty($_POST['has_examples']),
    'has_formula' => !empty($_POST['has_formula']),
    'has_how_to' => !empty($_POST['has_how_to']),
    'related_count' => (int) ($_POST['related_count'] ?? 0),
    'image' => (string) ($_POST['image'] ?? ''),
    'image_alt' => (string) ($_POST['image_alt'] ?? ''),
    'canonical' => (string) ($_POST['canonical'] ?? ''),
    'robots' => (string) ($_POST['robots'] ?? ''),
    'schema_type' => (string) ($_POST['schema_type'] ?? ''),
    'og_title' => (string) ($_POST['og_title'] ?? ''),
    'twitter_title' => (string) ($_POST['twitter_title'] ?? ''),
];

echo json_encode(calculate_seo_score($context));
