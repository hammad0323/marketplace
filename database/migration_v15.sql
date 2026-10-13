-- Migration v15: NOT NEEDED — do not run anything here.
-- Doctor URLs are now fixed automatically by the site itself on the first
-- page load after upload (sync_doctor_slugs() in includes/functions.php):
-- "Dr. Ali Khan" -> /doctors/dr-ali-khan (Dr once), "Ali Khan" -> /doctors/ali-khan.
SELECT 'migration_v15 is no longer needed' AS note;
