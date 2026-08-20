<?php
/**
 * 404.php — fully self-contained: works even if config.php/the DB is
 * broken, so it can never itself be the thing that's broken. It makes
 * a best-effort attempt to honor a stored 301 redirect (for old URLs
 * that no longer match any tool/category/page route) but silently
 * falls back to the plain 404 view on any failure.
 */
try {
    // Deliberately NOT requiring config.php here: this page must render
    // correctly even if the DB is down, and config.php's DB layer calls
    // exit() on a failed connection (uncatchable). A tiny, disposable
    // mysqli connection with all errors suppressed keeps this page safe.
    $dbHost = getenv('TOOLS_DB_HOST') ?: 'localhost';
    $dbName = getenv('TOOLS_DB_NAME') ?: 'tools_platform';
    $dbUser = getenv('TOOLS_DB_USER') ?: 'root';
    $dbPass = getenv('TOOLS_DB_PASS') ?: '';

    $conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn) {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $stmt = @mysqli_prepare($conn, "SELECT new_url, redirect_type FROM redirects WHERE old_url = ? AND status = 'active' LIMIT 1");
        if ($stmt && $path) {
            mysqli_stmt_bind_param($stmt, 's', $path);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $redirect = $result ? mysqli_fetch_assoc($result) : null;
            if ($redirect) {
                header('Location: ' . $redirect['new_url'], true, (int) $redirect['redirect_type']);
                mysqli_close($conn);
                exit;
            }
        }
        mysqli_close($conn);
    }
} catch (\Throwable $e) {
    // Deliberately swallowed — this page must render even if the DB is down.
}

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Not Found</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0B1020;color:#F8FAFC;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center;}
  .box{max-width:480px;padding:2rem;}
  h1{font-size:5rem;margin:0;background:linear-gradient(135deg,#6366F1,#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent;}
  p{color:#94A3B8;}
  a{display:inline-block;margin-top:1rem;background:linear-gradient(135deg,#6366F1,#8B5CF6);color:#fff;padding:.75rem 1.5rem;border-radius:999px;text-decoration:none;font-weight:600;}
</style>
</head>
<body>
  <div class="box">
    <h1>404</h1>
    <p>The tool or page you're looking for doesn't exist or may have moved.</p>
    <a href="/">Back to homepage</a>
  </div>
</body>
</html>
