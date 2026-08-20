<?php
/**
 * seed.php — one-time installer. Run this once after importing
 * schema.sql (php database/seed.php from the CLI, or visit it in a
 * browser on a fresh install) to populate:
 *   - the default super-admin account
 *   - default site settings
 *   - the 12 starter categories
 *   - the 55 starter tools (metadata + content + FAQs + examples + SEO)
 *   - route files (the literal /slug.php files every tool/category needs)
 *   - an initial sitemap.xml / robots.txt
 *
 * Safe to re-run: categories/tools are matched by slug and skipped if
 * they already exist, so this never creates duplicates.
 */

require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    if (tp_query_one('SELECT id FROM admins LIMIT 1')) {
        http_response_code(403);
        exit('Seeding has already run (an admin account exists). Delete this file or run it from the CLI only if you really need to re-seed.');
    }
}

$data = require __DIR__ . '/seed-data.php';

echo "== Seeding admin account ==\n";
$adminEmail = 'admin@example.com';
$adminPassword = 'ChangeMe123!';
if (!tp_query_one('SELECT id FROM admins WHERE email = ?', 's', [$adminEmail])) {
    tp_execute(
        'INSERT INTO admins (name, email, password_hash, role) VALUES (?,?,?,?)',
        'ssss',
        ['Super Admin', $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'super_admin']
    );
    echo "Created admin: {$adminEmail} / {$adminPassword}  <-- CHANGE THIS PASSWORD IMMEDIATELY\n";
} else {
    echo "Admin already exists, skipping.\n";
}

echo "\n== Seeding site settings ==\n";
$defaultSettings = [
    'site_name' => 'ToolStack',
    'site_tagline' => 'Smart Tools for Work, Business & Everyday Life',
    'default_theme' => 'system',
    'footer_copyright' => '© ' . date('Y') . ' ToolStack. All rights reserved.',
    'default_disclaimer' => 'Results are estimates for informational purposes only and should not be considered professional financial, engineering, tax, medical, or legal advice.',
    'currency_base' => 'USD',
];
foreach ($defaultSettings as $key => $value) {
    if (!tp_query_one('SELECT setting_key FROM site_settings WHERE setting_key = ?', 's', [$key])) {
        tp_save_setting($key, $value);
    }
}

echo "\n== Seeding categories ==\n";
$categoryIdBySlug = [];
foreach ($data['categories'] as $i => $cat) {
    $existing = tp_query_one('SELECT id FROM categories WHERE slug = ?', 's', [$cat['slug']]);
    if ($existing) {
        $categoryIdBySlug[$cat['slug']] = (int) $existing['id'];
        echo "  - {$cat['name']} already exists, skipping.\n";
        continue;
    }
    $result = tp_execute(
        'INSERT INTO categories (name, slug, description, icon, color, status, sort_order) VALUES (?,?,?,?,?,"published",?)',
        'sssssi',
        [$cat['name'], $cat['slug'], $cat['description'], $cat['icon'], $cat['color'], $i]
    );
    $categoryIdBySlug[$cat['slug']] = $result['insert_id'];
    tp_write_category_route($cat['slug']);
    echo "  + Created: {$cat['name']}\n";
}

echo "\n== Seeding tools ==\n";
foreach ($data['tools'] as $i => $tool) {
    if (tp_query_one('SELECT id FROM tools WHERE slug = ?', 's', [$tool['slug']])) {
        echo "  - {$tool['name']} already exists, skipping.\n";
        continue;
    }

    $categoryId = $categoryIdBySlug[$tool['category_slug']] ?? null;
    if (!$categoryId) {
        echo "  ! Skipping {$tool['name']} — unknown category {$tool['category_slug']}\n";
        continue;
    }

    $result = tp_execute(
        'INSERT INTO tools (category_id, name, slug, short_description, icon, tool_file, tool_type, status, is_featured, is_popular, is_trending, sort_order)
         VALUES (?,?,?,?,?,?,?,"published",?,?,?,?)',
        'issssssiiii',
        [
            $categoryId, $tool['name'], $tool['slug'], $tool['short_description'], $tool['icon'],
            $tool['tool_file'], $tool['tool_type'],
            $tool['is_featured'] ?? 0, $tool['is_popular'] ?? 0, $tool['is_trending'] ?? 0, $i,
        ]
    );
    $toolId = $result['insert_id'];

    $content = $tool['content'] ?? [];
    tp_execute(
        'INSERT INTO tool_content (tool_id, introduction, how_to_use, formula, formula_explanation, benefits, tips, disclaimer)
         VALUES (?,?,?,?,?,?,?,?)',
        'isssssss',
        [
            $toolId,
            $content['introduction'] ?? '', $content['how_to_use'] ?? '', $content['formula'] ?? '',
            $content['formula_explanation'] ?? '', $content['benefits'] ?? '', $content['tips'] ?? '',
            $content['disclaimer'] ?? '',
        ]
    );

    foreach ($tool['faqs'] ?? [] as $idx => $faq) {
        tp_execute(
            'INSERT INTO tool_faqs (tool_id, question, answer, sort_order) VALUES (?,?,?,?)',
            'issi',
            [$toolId, $faq['q'], $faq['a'], $idx]
        );
    }

    foreach ($tool['examples'] ?? [] as $idx => $ex) {
        tp_execute(
            'INSERT INTO tool_examples (tool_id, title, input_summary, output_summary, sort_order) VALUES (?,?,?,?,?)',
            'isssi',
            [$toolId, $ex['title'] ?? '', $ex['input'] ?? '', $ex['output'] ?? '', $idx]
        );
    }

    $seo = $tool['seo'] ?? [];
    save_seo_settings('tool', $toolId, [
        'seo_title' => $tool['name'] . ' — Free Online Tool',
        'meta_description' => $tool['short_description'],
        'focus_keyword' => $seo['focus_keyword'] ?? '',
        'schema_type' => $seo['schema_type'] ?? 'WebApplication',
        'robots' => 'index,follow',
        'image_alt_text' => $tool['name'],
    ]);

    tp_write_tool_route($tool['slug']);
    echo "  + Created: {$tool['name']}\n";
}

echo "\n== Linking related tools within each category ==\n";
foreach ($categoryIdBySlug as $slug => $catId) {
    $toolIds = array_column(tp_query('SELECT id FROM tools WHERE category_id = ? ORDER BY sort_order', 'i', [$catId]), 'id');
    foreach ($toolIds as $toolId) {
        $others = array_slice(array_filter($toolIds, fn($id) => $id !== $toolId), 0, 5);
        foreach (array_values($others) as $idx => $relatedId) {
            tp_execute('INSERT IGNORE INTO tool_related (tool_id, related_tool_id, sort_order) VALUES (?,?,?)', 'iii', [$toolId, $relatedId, $idx]);
        }
    }
}

echo "\n== Seeding static pages ==\n";
$staticPages = [
    ['slug' => 'about', 'title' => 'About Us', 'content' => '<p>' . tp_setting('site_name') . ' is a free online tools platform offering calculators, converters and generators for professionals, students and everyday use.</p>'],
    ['slug' => 'contact', 'title' => 'Contact Us', 'content' => '<p>Reach us at <a href="mailto:' . tp_setting('contact_email') . '">' . tp_setting('contact_email') . '</a>.</p>'],
    ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'content' => '<p>We do not require registration to use our tools. Calculation inputs are processed in your browser and are not transmitted to our servers unless a tool explicitly states otherwise.</p>'],
    ['slug' => 'terms', 'title' => 'Terms of Service', 'content' => '<p>Tools are provided "as is" for informational purposes. See our Disclaimer for important limitations.</p>'],
    ['slug' => 'disclaimer', 'title' => 'Disclaimer', 'content' => '<p>' . tp_setting('default_disclaimer') . '</p>'],
    ['slug' => 'cookie-policy', 'title' => 'Cookie Policy', 'content' => '<p>We use only strictly necessary session cookies and, where you opt in, browser localStorage for theme preference and favorites.</p>'],
    ['slug' => 'faq', 'title' => 'Frequently Asked Questions', 'content' => '<p>All tools are free to use and require no registration. See each tool\'s own FAQ section for tool-specific questions.</p>'],
];
foreach ($staticPages as $page) {
    if (!tp_query_one('SELECT id FROM pages WHERE slug = ?', 's', [$page['slug']])) {
        tp_execute('INSERT INTO pages (title, slug, content, status, is_system) VALUES (?,?,?,"published",1)', 'sss', [$page['title'], $page['slug'], $page['content']]);
        echo "  + Created page: {$page['title']}\n";
    }
}

echo "\n== Regenerating sitemap.xml and robots.txt ==\n";
tp_regenerate_sitemap();
tp_regenerate_robots();

echo "\nDone. Log in at /admin/login.php with {$adminEmail} / {$adminPassword} and change the password immediately.\n";
