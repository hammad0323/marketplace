-- Migration v7: widen doctors.bio to MEDIUMTEXT so it can hold rich HTML
-- from the new bio text editor (headings, bold/italic, bullet/numbered
-- lists). Additive/non-destructive: MODIFY COLUMN only widens the type,
-- existing plain-text bios are preserved as-is.

ALTER TABLE doctors MODIFY COLUMN bio MEDIUMTEXT COMMENT 'rich HTML from the bio text editor';
