<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
[$aw, $ap] = template_access_sql($u);
$rows = db_all("SELECT c.category_id, c.category_key, c.category_name, c.description, c.icon,
                  (SELECT COUNT(*) FROM screen_templates t WHERE t.category_id = c.category_id AND t.status = 'active' AND t.deleted_at IS NULL AND t.is_public = 1 AND {$aw}) AS template_count
             FROM template_categories c WHERE c.status = 'active' ORDER BY c.sort_order", $ap);
// Hide categories with nothing the user may use (admins can ask for all, e.g. to file a new template).
if (is_admin($u) && v_bool(input(), 'all', false)) ok($rows);
ok(array_values(array_filter($rows, fn($r) => (int)$r['template_count'] > 0)));
