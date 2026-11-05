<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_user();
ok(db_all("SELECT c.category_id, c.category_key, c.category_name, c.description, c.icon,
                  (SELECT COUNT(*) FROM screen_templates t WHERE t.category_id = c.category_id AND t.status = 'active' AND t.deleted_at IS NULL AND t.is_public = 1) AS template_count
             FROM template_categories c WHERE c.status = 'active' ORDER BY c.sort_order"));
