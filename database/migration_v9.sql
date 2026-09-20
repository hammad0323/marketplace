-- MediConnect / DoctorApna — incremental migration (v9)
-- Fixes leftover "MediConnect" branding in seeded CONTENT text that
-- migration_v4 didn't touch (it only renamed site_settings.site_name).
-- An SEO/trust audit found the old brand name still surviving in a
-- testimonial, the About/Privacy/Terms pages, and a blog post — all from
-- the original seed data. REPLACE() is a no-op wherever the text isn't
-- present, so this is safe to run even if some of this content was
-- already edited/replaced with real copy. Slugs are left untouched to
-- avoid breaking already-indexed URLs.

UPDATE site_settings
SET setting_value = REPLACE(setting_value, 'MediConnect', 'DoctorApna')
WHERE setting_value LIKE '%MediConnect%';

UPDATE cms_pages SET
    title = REPLACE(title, 'MediConnect', 'DoctorApna'),
    content = REPLACE(content, 'MediConnect', 'DoctorApna'),
    meta_title = REPLACE(meta_title, 'MediConnect', 'DoctorApna'),
    meta_description = REPLACE(meta_description, 'MediConnect', 'DoctorApna')
WHERE title LIKE '%MediConnect%' OR content LIKE '%MediConnect%'
   OR meta_title LIKE '%MediConnect%' OR meta_description LIKE '%MediConnect%';

UPDATE testimonials
SET content = REPLACE(content, 'MediConnect', 'DoctorApna')
WHERE content LIKE '%MediConnect%';

UPDATE blog_posts SET
    title = REPLACE(title, 'MediConnect', 'DoctorApna'),
    content = REPLACE(content, 'MediConnect', 'DoctorApna'),
    excerpt = REPLACE(excerpt, 'MediConnect', 'DoctorApna'),
    meta_title = REPLACE(meta_title, 'MediConnect', 'DoctorApna'),
    meta_description = REPLACE(meta_description, 'MediConnect', 'DoctorApna')
WHERE title LIKE '%MediConnect%' OR content LIKE '%MediConnect%' OR excerpt LIKE '%MediConnect%'
   OR meta_title LIKE '%MediConnect%' OR meta_description LIKE '%MediConnect%';

UPDATE medicine_info SET
    content = REPLACE(content, 'MediConnect', 'DoctorApna'),
    meta_title = REPLACE(meta_title, 'MediConnect', 'DoctorApna'),
    meta_description = REPLACE(meta_description, 'MediConnect', 'DoctorApna')
WHERE content LIKE '%MediConnect%' OR meta_title LIKE '%MediConnect%' OR meta_description LIKE '%MediConnect%';
