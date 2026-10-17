<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (attempt_login(post('email'), (string) ($_POST['password'] ?? ''))) {
        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        if ($to && strpos($to, '/') === 0 && strpos($to, '//') !== 0) {
            header('Location: ' . $to);
            exit;
        }
        redirect('index.php');
    }
    $error = 'Invalid email or password.';
}
$co = company();
$noLayout = true;
$pageTitle = 'Log in';
include __DIR__ . '/layouts/header.php';
?>
<div class="auth-wrap">
  <div class="auth-side">
    <div class="brand p-0"><span class="brand-mark"><i class="bi bi-file-earmark-richtext"></i></span><span class="brand-text">Proposal<b>Maker</b></span></div>
    <div style="max-width:460px;position:relative;z-index:2">
      <h1 class="text-white fw-bold" style="font-size:40px;letter-spacing:-1px">Win more clients with stunning proposals.</h1>
      <p class="opacity-75 fs-6 mt-3">Design, send and track beautiful proposals with 10 premium templates, live editing, e-signatures and pixel-perfect PDFs.</p>
      <div class="d-flex gap-4 mt-4 opacity-75 small"><span><i class="bi bi-palette2"></i> 10 templates</span><span><i class="bi bi-pen"></i> E-signatures</span><span><i class="bi bi-graph-up"></i> View tracking</span></div>
    </div>
    <div class="small opacity-50">&copy; <?= date('Y') ?> <?= e($co['company_name'] ?? '') ?></div>
  </div>
  <div class="auth-form">
    <form method="post">
      <?= csrf_field() ?>
      <h2 class="mb-1">Welcome back</h2>
      <p class="text-muted mb-4">Log in to manage your proposals.</p>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <?php foreach (take_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['msg']) ?></div><?php endforeach; ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control form-control-lg" required autofocus value="<?= e(post('email')) ?>"></div>
      <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control form-control-lg" required></div>
      <button class="btn btn-brand btn-lg w-100">Log in <i class="bi bi-arrow-right"></i></button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/layouts/footer.php'; ?>
