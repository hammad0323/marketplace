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
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';

        // Redirect rows are stored relative to the site root, without a
        // ".php" suffix — strip both from the real request path (the
        // same base-path detection config.php uses) before matching.
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $documentRootReal = $documentRoot !== '' ? (realpath($documentRoot) ?: rtrim(str_replace('\\', '/', $documentRoot), '/')) : '';
        $siteRootReal = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
        $documentRootReal = str_replace('\\', '/', $documentRootReal);
        $basePath = '';
        if ($documentRootReal !== '' && str_starts_with($siteRootReal, $documentRootReal)) {
            $basePath = '/' . trim(substr($siteRootReal, strlen($documentRootReal)), '/');
        }
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        if (str_ends_with($path, '.php')) {
            $path = substr($path, 0, -4);
        }
        $path = '/' . ltrim($path, '/');

        $stmt = @mysqli_prepare($conn, "SELECT new_url, redirect_type FROM redirects WHERE old_url = ? AND status = 'active' LIMIT 1");
        if ($stmt && $path) {
            mysqli_stmt_bind_param($stmt, 's', $path);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $redirect = $result ? mysqli_fetch_assoc($result) : null;
            if ($redirect) {
                $target = $redirect['new_url'];
                $isExternal = str_starts_with($target, 'http://') || str_starts_with($target, 'https://');
                if (!$isExternal) {
                    $target = ($basePath && $basePath !== '/' ? $basePath : '') . '/' . ltrim($target, '/');
                }
                header('Location: ' . $target, true, (int) $redirect['redirect_type']);
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
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#fff;color:#1E1B2E;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center;}
  .box{max-width:480px;padding:2rem;}
  h1{font-size:5rem;margin:0;color:#7C3AED;font-weight:800;}
  p{color:#6B7280;}
  a{display:inline-block;margin-top:1rem;background:#7C3AED;color:#fff;padding:.75rem 1.5rem;border-radius:999px;text-decoration:none;font-weight:600;}
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
