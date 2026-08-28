<?php
if (!defined('BASE_URL')) { require_once __DIR__ . '/includes/config.php'; }
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>403 - Access Denied</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#0F172A;color:#fff;min-height:100vh;display:flex;align-items:center;font-family:Inter,sans-serif;}
.card{max-width:480px;margin:auto;background:#111C33;border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:44px;text-align:center;}
.icon{width:64px;height:64px;border-radius:16px;background:rgba(220,38,38,.15);color:#F87171;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 18px;}</style>
</head><body><div class="card"><div class="icon"><i class="bi bi-shield-lock"></i></div>
<h3 class="fw-bold">403 - Access Denied</h3>
<p class="text-white-50">You don't have permission to view this page, or your session has expired.</p>
<a href="<?= BASE_URL ?>/" class="btn btn-primary mt-2">Back to Home</a></div></body></html>
