<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email_templates.php';

$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
        $error = 'Please fill in all fields with a valid email address.';
    } else {
        send_contact_email($name, $email, $message);
        $sent = true;
    }
}

$pageTitle = 'Contact Us | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight" style="max-width:700px">
  <h1 class="font-serif mb-4 text-center">Contact Us</h1>
  <?php if ($sent): ?>
    <div class="alert alert-success">Thank you for reaching out! Our team will get back to you shortly.</div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <div class="row g-4 mb-4 text-center">
    <div class="col-md-4"><i class="bi bi-geo-alt fs-3 text-muted"></i><p class="small mt-2"><?= e(get_setting('store_address')) ?></p></div>
    <div class="col-md-4"><i class="bi bi-telephone fs-3 text-muted"></i><p class="small mt-2"><?= e(get_setting('store_phone')) ?></p></div>
    <div class="col-md-4"><i class="bi bi-envelope fs-3 text-muted"></i><p class="small mt-2"><?= e(get_setting('store_email')) ?></p></div>
  </div>
  <form method="post" class="summary-box">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
      <div class="col-12"><button class="btn-brand">Send Message</button></div>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
