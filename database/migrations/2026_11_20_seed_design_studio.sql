-- Urban Collections: Design Studio default settings and admin permission.
-- Run AFTER 2026_11_20_design_studio.sql on stores installed before this update. Safe to re-run.
-- Then mark your plain T-shirt / hoodie products as Design Studio blanks (Products » Edit » Design Studio blank).
SET NAMES utf8mb4;

INSERT IGNORE INTO admin_permissions (perm_key, label, group_name) VALUES ('design_studio', 'Design Studio: prices & design requests', 'Sales');
INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) SELECT r.id, p.id FROM admin_roles r, admin_permissions p WHERE r.slug = 'super-admin' AND p.perm_key = 'design_studio';
INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) SELECT r.id, p.id FROM admin_roles r, admin_permissions p WHERE r.slug = 'store-manager' AND p.perm_key = 'design_studio';
INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) SELECT r.id, p.id FROM admin_roles r, admin_permissions p WHERE r.slug = 'order-manager' AND p.perm_key = 'design_studio';

INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_enabled', '1', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_pricing_mode', 'auto', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_show_estimate', '1', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_price_small', '300', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_price_medium', '500', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_price_large', '800', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_price_text', '150', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_small_max', '12', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_medium_max', '35', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_multi_discount', '10', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_both_sides_fee', '0', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_min_print', '0', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_production_days', '3', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_max_elements', '8', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_ai_daily_limit', '15', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_removebg_key', '', 'design');
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group) VALUES ('design_notify_email', '', 'design');

INSERT INTO navigation_items (menu, parent_id, label, url, highlight, sort_order, is_active) SELECT menu, NULL, 'Design Your Own Tee', 'design-studio', 0, sort_order, 1 FROM navigation_items WHERE url = 'custom-size' AND menu <> 'header' AND NOT EXISTS (SELECT 1 FROM navigation_items WHERE url = 'design-studio') LIMIT 1;
