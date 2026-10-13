-- Migration v15: doctor URLs without the "dr-" prefix
-- (/doctors/dr-sarah-chen -> /doctors/sarah-chen). Also covers old
-- "dr-dr-" slugs, so it is safe to run whether or not v14 was run.
-- A doctor keeps their current slug if the new one is already taken.
-- doctor-profile.php 301-redirects the old URLs to the new ones.
-- After running it, click "Generate Sitemap" in Admin > Site Settings.

DROP TABLE IF EXISTS tmp_doctor_slug_fix;
CREATE TABLE tmp_doctor_slug_fix AS
SELECT id,
       CASE WHEN slug LIKE 'dr-dr-%' THEN SUBSTRING(slug, 7) ELSE SUBSTRING(slug, 4) END AS new_slug
FROM doctors
WHERE slug LIKE 'dr-%';

-- Skip empty results and slugs that another doctor already uses.
DELETE FROM tmp_doctor_slug_fix WHERE new_slug = '';
DELETE f FROM tmp_doctor_slug_fix f JOIN doctors taken ON taken.slug = f.new_slug;
-- If two doctors would end up with the same slug, only the older one is renamed.
DELETE f FROM tmp_doctor_slug_fix f JOIN tmp_doctor_slug_fix older ON older.new_slug = f.new_slug AND older.id < f.id;

UPDATE doctors d JOIN tmp_doctor_slug_fix f ON f.id = d.id SET d.slug = f.new_slug;
DROP TABLE tmp_doctor_slug_fix;
