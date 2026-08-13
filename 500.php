<?php
http_response_code(500);
// Self-contained (no config.php dependency) on purpose — this page is
// shown precisely when the DB connection fails, so it must render fine
// without needing the DB, session, or anything else. Same base-path
// auto-detection as config/config.php's BASE_PATH, duplicated here.
$__docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$__appRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
$basePath = ($__docRoot !== '' && strpos($__appRoot, $__docRoot) === 0) ? substr($__appRoot, strlen($__docRoot)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something went wrong — Wanderly</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: radial-gradient(120% 120% at 10% 0%, #2A1B57 0%, #171129 55%, #0F0B1E 100%); color: #fff; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; text-align: center; }
  .wrap { max-width: 460px; padding: 24px; }
  .code { font-size: 96px; font-weight: 800; line-height: 1; background: linear-gradient(135deg, #C4B5FD, #F0ABFC); -webkit-background-clip: text; background-clip: text; color: transparent; }
  h1 { font-size: 22px; margin: 12px 0 8px; }
  p { color: rgba(255,255,255,0.7); margin-bottom: 28px; }
  a { display: inline-flex; align-items:center; gap:8px; background: linear-gradient(135deg, #8B5CF6, #6D28D9); color: #fff; padding: 13px 26px; border-radius: 999px; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="code">500</div>
    <h1>Something went wrong on our end</h1>
    <p>Our team has been notified. Please try again in a moment.</p>
    <a href="<?php echo htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8'); ?>/"><i class="bi bi-arrow-left"></i> Back to home</a>
  </div>
</body>
</html>
