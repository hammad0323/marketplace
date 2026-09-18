-- Migration v8: add doctors.designation (job title, e.g. "Consultant
-- Cardiologist"), shown on the public profile above the qualification line.
-- Additive/non-destructive: adds a new nullable column, existing rows are
-- unaffected (designation defaults to NULL and simply won't display).

ALTER TABLE doctors ADD COLUMN designation VARCHAR(150) DEFAULT NULL COMMENT 'job title, e.g. Consultant Cardiologist' AFTER qualification;
