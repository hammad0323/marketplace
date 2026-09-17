<?php
http_response_code(404);
// Self-contained subfolder detection (this file must keep working even if
// config.php is broken, so it doesn't require() it — see README).
if (!defined('BASE_URL')) {
    $wh_root = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''
        ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/')
        : '';
    $wh_dir = rtrim(str_replace('\\', '/', __DIR__), '/');
    define('BASE_URL', ($wh_root !== '' && strpos($wh_dir, $wh_root) === 0) ? substr($wh_dir, strlen($wh_root)) : '');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Not Found</title>
<style>
  body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f0b14;color:#f4eee8;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center;padding:24px}
  .wrap{max-width:480px}
  h1{font-size:5rem;margin:0;background:linear-gradient(135deg,#c79a4b,#7a1f3d);-webkit-background-clip:text;background-clip:text;color:transparent}
  p{color:#c9bfc4;line-height:1.6}
  a{display:inline-block;margin-top:16px;padding:12px 28px;border-radius:999px;background:#7a1f3d;color:#fff;text-decoration:none;font-weight:600}
  a:hover{background:#93254b}
</style>
</head>
<body>
  <div class="wrap">
    <h1>404</h1>
    <p>The page you're looking for doesn't exist or may have moved.</p>
    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/">Back to Home</a>
  </div>
</body>
</html>
