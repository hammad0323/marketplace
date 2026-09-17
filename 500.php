<?php http_response_code(500); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something Went Wrong</title>
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
    <h1>500</h1>
    <p>Something went wrong on our end. Please try again shortly.</p>
    <a href="/">Back to Home</a>
  </div>
</body>
</html>
