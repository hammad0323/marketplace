<?php
/**
 * LIBRARY / PRE-BUILT PROPOSAL management (AJAX): rename, copy, delete, restore.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();
$id = (int) post('id', 0);
try {
    switch (post('action')) {
        case 'library_update':
            if (post('name') === '') json_out(['ok' => false, 'message' => 'Name is required.'], 422);
            db_update('content_library', ['name' => post('name'), 'category' => post('category') ?: 'General', 'description' => post('description'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            json_out(['ok' => true]);
        case 'library_delete':
            db_query('DELETE FROM content_library WHERE id = ?', [$id]);
            json_out(['ok' => true]);
        case 'library_duplicate':
            $r = db_row('SELECT * FROM content_library WHERE id = ?', [$id]);
            if (!$r) json_out(['ok' => false, 'message' => 'Not found'], 404);
            db_insert('content_library', ['name' => $r['name'] . ' (Copy)', 'category' => $r['category'], 'description' => $r['description'], 'blocks' => $r['blocks'], 'created_at' => date('Y-m-d H:i:s')]);
            json_out(['ok' => true]);
        case 'service_update':
            if (post('name') === '') json_out(['ok' => false, 'message' => 'Name is required.'], 422);
            db_update('service_templates', ['name' => post('name'), 'service' => post('service'), 'description' => post('description'), 'icon' => preg_replace('/[^a-z0-9\-]/', '', post('icon')) ?: 'file-earmark-text'], 'id = ?', [$id]);
            json_out(['ok' => true]);
        case 'service_delete':
            db_query('DELETE FROM service_templates WHERE id = ?', [$id]);
            json_out(['ok' => true]);
        case 'service_duplicate':
            $r = db_row('SELECT * FROM service_templates WHERE id = ?', [$id]);
            if (!$r) json_out(['ok' => false, 'message' => 'Not found'], 404);
            db_insert('service_templates', ['name' => $r['name'] . ' (Copy)', 'slug' => $r['slug'] . '-copy', 'service' => $r['service'], 'description' => $r['description'], 'icon' => $r['icon'], 'structure' => $r['structure'], 'is_system' => 0, 'created_at' => date('Y-m-d H:i:s')]);
            json_out(['ok' => true]);
        case 'service_restore':
            require_admin();
            db_query('DELETE FROM service_templates WHERE is_system = 1');
            require_once APP_ROOT . '/includes/prebuilt.php';
            foreach (pm_prebuilt_proposals() as $slug => $t) {
                db_insert('service_templates', ['name' => $t['name'], 'slug' => $slug, 'service' => $t['service'], 'description' => $t['description'], 'icon' => $t['icon'], 'structure' => jencode($t['pages']), 'is_system' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            }
            json_out(['ok' => true]);
        case 'set_default_template':
            db_update('company_settings', ['default_template' => pm_template($id)['id']], 'id = 1');
            json_out(['ok' => true]);
        default:
            json_out(['ok' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 500);
}
