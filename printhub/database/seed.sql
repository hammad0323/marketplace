-- =====================================================================
-- PrintHub master data. Configuration only — no demo shops, customers or orders.
-- Import after schema.sql. Everything here can be changed in the Super Admin panel.
-- =====================================================================
SET NAMES utf8mb4;

-- First Super Admin. CHANGE THE PASSWORD: you are forced to set a new one at first sign-in.
-- Email: admin@printhub.pk   Password: ChangeMe@2026
INSERT INTO admins (name, email, password_hash, role, must_change_password) VALUES
('Platform Owner', 'admin@printhub.pk', '$2y$10$dNcIu8i9cf6ISYeq014RXup90XrmU3JYApcX6rcbT7geUrt47dRH.', 'super_admin', 1);

INSERT INTO platform_settings (setting_key, setting_value) VALUES
('platform_name', 'PrintHub'),
('platform_tagline', 'Scan. Upload. Print.'),
('support_phone', ''),
('support_email', ''),
('default_subscription_price', '1500'),
('default_grace_days', '3'),
('reminder_days_before', '5'),
('default_retention_days', '7'),
('max_retention_days', '30'),
('max_upload_mb', '25'),
('temp_upload_hours', '24'),
('android_package', ''),
('android_sha256', ''),
('ios_app_id', ''),
('play_store_url', ''),
('app_store_url', ''),
('show_install_prompt', '1'),
('privacy_notice', 'Your files are used only to print your order at the shop you choose. They are stored privately, visible only to that shop, and deleted automatically after your order is closed. We never sell your files or use them to train AI.');

-- ---------------------------------------------------------------- printing options
INSERT INTO printing_options (option_group, code, label, width_mm, height_mm, sort_order) VALUES
('paper_size', 'a4', 'A4', 210, 297, 1),
('paper_size', 'a3', 'A3', 297, 420, 2),
('paper_size', 'a5', 'A5', 148, 210, 3),
('paper_size', 'legal', 'Legal', 216, 356, 4),
('paper_size', 'letter', 'Letter', 216, 279, 5),
('paper_size', 'photo_4x6', '4 × 6 in photo', 102, 152, 6),
('paper_size', 'photo_5x7', '5 × 7 in photo', 127, 178, 7),
('paper_size', 'passport', 'Passport photo (35 × 45 mm)', 35, 45, 8),
('paper_size', 'card', 'Visiting card (3.5 × 2 in)', 89, 51, 9),
('paper_size', 'id_card', 'ID card (CR80)', 85.6, 54, 10),
('paper_size', 'custom', 'Custom size', NULL, NULL, 20);
INSERT INTO printing_options (option_group, code, label, sort_order) VALUES
('color', 'bw', 'Black & White', 1),
('color', 'color', 'Full Colour', 2),
('sides', 'single', 'Single-sided', 1),
('sides', 'double', 'Double-sided', 2),
('paper_type', 'normal', 'Normal paper', 1),
('paper_type', 'premium', 'Premium paper', 2),
('paper_type', 'card', 'Card paper', 3),
('paper_type', 'photo', 'Photo paper', 4),
('paper_type', 'glossy', 'Glossy', 5),
('paper_type', 'matte', 'Matte', 6),
('paper_type', 'sticker', 'Sticker paper', 7),
('gsm', '70', '70 GSM', 1), ('gsm', '80', '80 GSM', 2), ('gsm', '100', '100 GSM', 3), ('gsm', '120', '120 GSM', 4),
('gsm', '150', '150 GSM', 5), ('gsm', '200', '200 GSM', 6), ('gsm', '250', '250 GSM', 7), ('gsm', '300', '300 GSM', 8),
('finishing', 'lamination', 'Lamination', 1),
('finishing', 'spiral', 'Spiral binding', 2),
('finishing', 'ring', 'Ring binding', 3),
('finishing', 'tape', 'Tape binding', 4),
('finishing', 'hard_binding', 'Hard binding', 5),
('finishing', 'soft_binding', 'Soft binding', 6),
('finishing', 'stapling', 'Stapling', 7),
('finishing', 'cutting', 'Cutting', 8),
('finishing', 'custom_finishing', 'Custom finishing', 9);

-- ---------------------------------------------------------------- categories
INSERT INTO categories (code, name, name_ur, description, icon, pricing_basis, sort_order) VALUES
('document', 'Document Printing', 'دستاویز پرنٹنگ', 'Assignments, notes, office documents, forms and reports', 'file-earmark-text', 'pages', 1),
('educational', 'Educational Printing', 'تعلیمی پرنٹنگ', 'Thesis, project reports, books, question papers and certificates', 'mortarboard', 'pages', 2),
('personal', 'Personal Documents', 'ذاتی دستاویزات', 'CNIC copies, ID cards, passport photos, visa documents and CVs', 'person-vcard', 'pages', 3),
('business', 'Business Printing', 'کاروباری پرنٹنگ', 'Visiting cards, letterheads, flyers, stickers and bill books', 'briefcase', 'items', 4),
('photo', 'Photo & Premium', 'فوٹو اور پریمیم', 'Glossy and matte photos, passport photos, PVC cards and invitations', 'camera', 'items', 5),
('custom', 'Other / Custom', 'دیگر', 'Upload any file and tell the shop exactly what you need', 'sliders', 'pages', 6);

INSERT INTO subcategories (category_id, code, name, pricing_basis, sort_order)
SELECT c.id, s.code, s.name, s.basis, s.sort FROM categories c JOIN (
  SELECT 'document' cat, 'general' code, 'General printing' name, NULL basis, 1 sort UNION ALL
  SELECT 'document', 'assignment', 'Assignment printing', NULL, 2 UNION ALL
  SELECT 'document', 'notes', 'Notes printing', NULL, 3 UNION ALL
  SELECT 'document', 'office', 'Office documents', NULL, 4 UNION ALL
  SELECT 'document', 'research', 'Research papers', NULL, 5 UNION ALL
  SELECT 'document', 'forms', 'Forms', NULL, 6 UNION ALL
  SELECT 'document', 'reports', 'Reports', NULL, 7 UNION ALL
  SELECT 'educational', 'assignments', 'Assignments', NULL, 1 UNION ALL
  SELECT 'educational', 'thesis', 'Thesis', NULL, 2 UNION ALL
  SELECT 'educational', 'research', 'Research papers', NULL, 3 UNION ALL
  SELECT 'educational', 'project_reports', 'Project reports', NULL, 4 UNION ALL
  SELECT 'educational', 'book', 'Book printing', NULL, 5 UNION ALL
  SELECT 'educational', 'notes', 'Notes', NULL, 6 UNION ALL
  SELECT 'educational', 'mark_sheets', 'Mark sheets', NULL, 7 UNION ALL
  SELECT 'educational', 'certificates', 'Certificates', NULL, 8 UNION ALL
  SELECT 'educational', 'question_papers', 'Question papers', NULL, 9 UNION ALL
  SELECT 'personal', 'cnic_copy', 'CNIC copy (front & back on one page)', 'items', 1 UNION ALL
  SELECT 'personal', 'id_card', 'ID card printing', 'items', 2 UNION ALL
  SELECT 'personal', 'passport_photos', 'Passport-size photographs', 'items', 3 UNION ALL
  SELECT 'personal', 'visa', 'Visa documents', NULL, 4 UNION ALL
  SELECT 'personal', 'cv', 'CV printing', NULL, 5 UNION ALL
  SELECT 'personal', 'certificates', 'Certificates', NULL, 6 UNION ALL
  SELECT 'personal', 'forms', 'Personal forms', NULL, 7 UNION ALL
  SELECT 'business', 'visiting_cards', 'Visiting cards', NULL, 1 UNION ALL
  SELECT 'business', 'business_cards', 'Business cards', NULL, 2 UNION ALL
  SELECT 'business', 'letterheads', 'Letterheads', NULL, 3 UNION ALL
  SELECT 'business', 'envelopes', 'Envelopes', NULL, 4 UNION ALL
  SELECT 'business', 'flyers', 'Flyers', NULL, 5 UNION ALL
  SELECT 'business', 'brochures', 'Brochures', NULL, 6 UNION ALL
  SELECT 'business', 'stickers', 'Stickers', NULL, 7 UNION ALL
  SELECT 'business', 'labels', 'Product labels', NULL, 8 UNION ALL
  SELECT 'business', 'bill_books', 'Bill books', NULL, 9 UNION ALL
  SELECT 'photo', 'glossy', 'Glossy photos', NULL, 1 UNION ALL
  SELECT 'photo', 'matte', 'Matte photos', NULL, 2 UNION ALL
  SELECT 'photo', 'passport', 'Passport-size photos', NULL, 3 UNION ALL
  SELECT 'photo', '4x6', '4 × 6 photographs', NULL, 4 UNION ALL
  SELECT 'photo', '5x7', '5 × 7 photographs', NULL, 5 UNION ALL
  SELECT 'photo', 'a4_photo', 'A4 photo printing', NULL, 6 UNION ALL
  SELECT 'photo', 'pvc_cards', 'PVC ID cards', NULL, 7 UNION ALL
  SELECT 'photo', 'invitations', 'Wedding invitations', NULL, 8 UNION ALL
  SELECT 'custom', 'plain', 'Custom / plain printing', NULL, 1
) s ON s.cat = c.code;

-- Which options each category shows (only relevant ones — e.g. no binding for passport photos)
INSERT INTO category_options (category_id, option_id, is_default)
SELECT c.id, o.id, IF(FIND_IN_SET(CONCAT(o.option_group, ':', o.code), m.defaults), 1, 0)
FROM categories c
JOIN (
  SELECT 'document' cat,
    'paper_size:a4,paper_size:a3,paper_size:a5,paper_size:legal,paper_size:letter,paper_size:custom,color:bw,color:color,sides:single,sides:double,paper_type:normal,paper_type:premium,gsm:70,gsm:80,gsm:100,finishing:stapling,finishing:spiral,finishing:ring,finishing:tape,finishing:soft_binding,finishing:hard_binding,finishing:lamination' opts,
    'paper_size:a4,color:bw,sides:single,paper_type:normal,gsm:80' defaults
  UNION ALL SELECT 'educational',
    'paper_size:a4,paper_size:a3,paper_size:legal,paper_size:letter,paper_size:custom,color:bw,color:color,sides:single,sides:double,paper_type:normal,paper_type:premium,paper_type:card,gsm:70,gsm:80,gsm:100,gsm:120,finishing:stapling,finishing:spiral,finishing:ring,finishing:tape,finishing:soft_binding,finishing:hard_binding,finishing:lamination',
    'paper_size:a4,color:bw,sides:single,paper_type:normal,gsm:80'
  UNION ALL SELECT 'personal',
    'paper_size:a4,paper_size:legal,paper_size:passport,paper_size:id_card,color:bw,color:color,sides:single,sides:double,paper_type:normal,paper_type:premium,paper_type:photo,paper_type:glossy,paper_type:card,gsm:80,gsm:100,gsm:200,gsm:250,gsm:300,finishing:lamination,finishing:cutting,finishing:stapling',
    'paper_size:a4,color:bw,sides:single,paper_type:normal,gsm:80'
  UNION ALL SELECT 'business',
    'paper_size:card,paper_size:a4,paper_size:a5,paper_size:a3,paper_size:letter,paper_size:custom,color:bw,color:color,sides:single,sides:double,paper_type:premium,paper_type:card,paper_type:glossy,paper_type:matte,paper_type:sticker,gsm:100,gsm:120,gsm:150,gsm:200,gsm:250,gsm:300,finishing:lamination,finishing:cutting,finishing:custom_finishing',
    'color:color,sides:single,paper_type:card,gsm:300'
  UNION ALL SELECT 'photo',
    'paper_size:passport,paper_size:photo_4x6,paper_size:photo_5x7,paper_size:a4,paper_size:id_card,paper_size:custom,color:color,sides:single,paper_type:photo,paper_type:glossy,paper_type:matte,paper_type:card,gsm:200,gsm:250,gsm:300,finishing:lamination,finishing:cutting',
    'paper_size:photo_4x6,color:color,sides:single,paper_type:glossy,gsm:250'
  UNION ALL SELECT 'custom',
    'paper_size:a4,paper_size:a3,paper_size:a5,paper_size:legal,paper_size:letter,paper_size:custom,color:bw,color:color,sides:single,sides:double,paper_type:normal,paper_type:premium,paper_type:card,paper_type:photo,paper_type:glossy,paper_type:matte,paper_type:sticker,gsm:70,gsm:80,gsm:100,gsm:120,gsm:150,gsm:200,gsm:250,gsm:300,finishing:lamination,finishing:spiral,finishing:ring,finishing:tape,finishing:hard_binding,finishing:soft_binding,finishing:stapling,finishing:cutting,finishing:custom_finishing',
    'paper_size:a4,color:bw,sides:single,paper_type:normal,gsm:80'
) m ON m.cat = c.code
JOIN printing_options o ON FIND_IN_SET(CONCAT(o.option_group, ':', o.code), m.opts);

-- ---------------------------------------------------------------- CV templates (layouts live in core/cv_templates/)
INSERT INTO cv_templates (template_key, name, name_ur, category, description, default_color, supports_photo, ats_friendly, is_featured, sort_order) VALUES
('modern', 'Modern', 'جدید', 'modern', 'Two-column layout with a coloured sidebar for skills and contact details.', '#0F766E', 1, 0, 1, 1),
('minimal', 'Minimal', 'سادہ', 'minimal', 'Lots of white space, thin dividers and a single column.', '#111827', 0, 1, 0, 2),
('corporate', 'Corporate', 'کارپوریٹ', 'corporate', 'Classic header band — suited to banks, offices and corporate roles.', '#1E3A8A', 1, 1, 1, 3),
('professional', 'Professional', 'پروفیشنل', 'professional', 'Balanced single column with a strong name block.', '#0F766E', 1, 1, 1, 4),
('student', 'Student', 'طالب علم', 'student', 'Education and projects first — ideal for internships.', '#7C3AED', 1, 1, 0, 5),
('fresher', 'Fresh Graduate', 'فریش گریجویٹ', 'fresher', 'Highlights education, skills and achievements for a first job.', '#0369A1', 1, 1, 0, 6),
('experienced', 'Experienced Professional', 'تجربہ کار', 'experienced', 'Experience-led layout with a skills column.', '#334155', 1, 1, 0, 7),
('creative', 'Creative', 'تخلیقی', 'creative', 'Bold accent header and timeline — for designers and media.', '#DB2777', 1, 0, 0, 8),
('ats', 'Simple ATS-Friendly', 'اے ٹی ایس', 'ats', 'Plain single column with standard headings that applicant tracking systems read correctly.', '#000000', 0, 1, 1, 9),
('executive', 'Executive', 'ایگزیکٹو', 'executive', 'Elegant serif headings for senior and management roles.', '#78350F', 1, 1, 0, 10);
