-- Urban Collections: "T-Shirt Rail" homepage section (T-shirts hanging on a pipe, turning on hover).
-- For stores installed before this update. Safe to re-run: the section is only added if there is none yet.
-- No table changes are needed. Afterwards edit it under Admin » Homepage Builder.
SET NAMES utf8mb4;

INSERT INTO homepage_sections (section_type, admin_label, settings, sort_order, status)
SELECT 'hanger_rail', 'T-shirt rail — New drop',
       CONCAT('{"eyebrow":"New Drop","title":"Fresh Off the Rail","subtitle":"Every tee turns to show its back — tap one to shop.",',
              '"source":"category","category_ids":[', IFNULL((SELECT id FROM categories WHERE slug = 't-shirts' LIMIT 1), ''), '],',
              '"limit":"12","image":"main","hover":"spin","sway":"1","details":"hover","pipe":"black",',
              '"button_text":"Shop all T-shirts","button_url":"category/t-shirts","width":"wide"}'),
       25, 'published'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM homepage_sections WHERE section_type = 'hanger_rail');
