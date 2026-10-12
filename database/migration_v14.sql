-- Migration v14: fix doctor slugs created as "dr-dr-<name>" (when the name
-- was entered as "Dr. <name>"). Each is renamed to "dr-<name>" unless that
-- slug is already taken. doctor-profile.php 301-redirects the old URLs.
UPDATE doctors d
LEFT JOIN doctors taken ON taken.slug = SUBSTRING(d.slug, 4)
SET d.slug = SUBSTRING(d.slug, 4)
WHERE d.slug LIKE 'dr-dr-%' AND taken.id IS NULL;
