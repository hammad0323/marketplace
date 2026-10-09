<?php
/**
 * Admin AJAX endpoint: drag-and-drop ordering and inline toggles.
 * Every entity is mapped to a table, an allow-listed column and a permission.
 */
require __DIR__ . '/_inc/bootstrap.php';
require_admin();
if (!is_post()) {
    json_response(['ok' => false, 'message' => 'POST required'], 405);
}
require_csrf();

$entities = [
    'category' => ['categories', ['is_active', 'show_on_home'], 'categories.manage'],
    'categories' => ['categories', [], 'categories.manage'],
    'slide' => ['banner_slides', ['is_active'], 'content.slides'],
    'slides' => ['banner_slides', [], 'content.slides'],
    'testimonial' => ['testimonials', ['is_active'], 'content.testimonials'],
    'testimonials' => ['testimonials', [], 'content.testimonials'],
    'collection' => ['collections', ['is_active'], 'collections.manage'],
    'collections' => ['collections', [], 'collections.manage'],
    'page' => ['pages', ['is_published'], 'content.pages'],
    'pages' => ['pages', [], 'content.pages'],
    'coupon' => ['coupons', ['is_active'], 'coupons.manage'],
    'redirect' => ['redirects', ['is_active'], 'seo.manage'],
    'zone' => ['shipping_zones', ['is_active', 'cod_available'], 'shipping.manage'],
    'sections' => ['homepage_sections', [], 'content.homepage'],
    'section' => ['homepage_sections', ['draft_enabled'], 'content.homepage'],
];
$entity = input('entity');
if (!isset($entities[$entity])) {
    json_response(['ok' => false, 'message' => 'Unknown entity'], 400);
}
[$table, $fields, $perm] = $entities[$entity];
require_admin($perm);

switch (input('action')) {
    case 'reorder':
        $ids = array_values(array_filter(array_map('intval', input_array('ids'))));
        $col = $table === 'homepage_sections' ? 'draft_sort' : 'sort_order';
        db_tx(function () use ($ids, $table, $col) {
            foreach ($ids as $i => $id) {
                db_exec('UPDATE ' . db_ident($table) . ' SET ' . db_ident($col) . ' = ? WHERE id = ?', [($i + 1) * 10, $id]);
            }
        });
        audit_log('reordered', $table, null, ['ids' => $ids]);
        json_response(['ok' => true, 'message' => $table === 'homepage_sections' ? 'Order saved as draft — publish to go live.' : 'Order saved']);
    case 'toggle':
        $field = input('field');
        if (!in_array($field, $fields, true)) {
            json_response(['ok' => false, 'message' => 'Field not editable'], 400);
        }
        $id = input_int('id');
        db_exec('UPDATE ' . db_ident($table) . ' SET ' . db_ident($field) . ' = ? WHERE id = ?', [input_bool('value'), $id]);
        audit_log('toggled', $table, $id, [$field => input_bool('value')]);
        json_response(['ok' => true, 'message' => $table === 'homepage_sections' ? 'Saved as draft' : 'Saved']);
}
json_response(['ok' => false, 'message' => 'Unknown action'], 400);
