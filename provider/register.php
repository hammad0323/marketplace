<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$categories = db_select($conn, 'SELECT id, name FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order');
$cities = db_select($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order');

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'business_name' => '', 'category_id' => '', 'city_id' => '', 'address' => '', 'website' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($old) as $field) {
        $old[$field] = clean_input($_POST[$field] ?? '');
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($old['name'] === '') $errors[] = 'Your name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($old['business_name'] === '') $errors[] = 'Business name is required.';
    if ($old['category_id'] === '') $errors[] = 'Please select a category.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $result = register_provider($conn, $old + ['password' => $password]);
        if ($result['ok']) {
            flash_set('success', 'Your provider account was created and is pending admin approval. You can log in now.');
            redirect('/provider/login.php');
        }
        $errors[] = $result['error'];
    }
}

$pageTitle = 'List your business';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:640px;">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-shop"></i> Providers</span>
      <h1 class="section-heading">List your business on <?php echo e($siteName); ?></h1>
      <p class="section-sub">Create your account, then submit your business for admin approval. Once approved you can add services, manage availability and take bookings.</p>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <form method="post" class="form-w panel">
      <?php echo csrf_field(); ?>
      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <div><label>Your full name</label><input type="text" name="name" value="<?php echo e($old['name']); ?>" required></div>
        <div><label>Email address</label><input type="email" name="email" value="<?php echo e($old['email']); ?>" required></div>
        <div><label>Phone</label><input type="tel" name="phone" value="<?php echo e($old['phone']); ?>"></div>
        <div><label>Business name</label><input type="text" name="business_name" value="<?php echo e($old['business_name']); ?>" required></div>
        <div>
          <label>Category</label>
          <select name="category_id" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int) $cat['id']; ?>" <?php echo (string) $cat['id'] === $old['category_id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>City</label>
          <select name="city_id">
            <option value="">Select a city</option>
            <?php foreach ($cities as $city): ?>
              <option value="<?php echo (int) $city['id']; ?>" <?php echo (string) $city['id'] === $old['city_id'] ? 'selected' : ''; ?>><?php echo e($city['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label>Address</label>
      <input type="text" name="address" value="<?php echo e($old['address']); ?>">
      <label>Website (optional)</label>
      <input type="url" name="website" value="<?php echo e($old['website']); ?>" placeholder="https://">
      <label>Business description</label>
      <textarea name="description" rows="4"><?php echo e($old['description']); ?></textarea>
      <div class="row" style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <div><label>Password</label><input type="password" name="password" minlength="8" required></div>
        <div><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" required></div>
      </div>
      <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:24px;">Submit for approval</button>
    </form>
    <p class="sub" style="margin-top:18px;">Already a provider? <a href="<?php echo url('/provider/login.php'); ?>" style="color:var(--purple-600);font-weight:700;">Log in</a></p>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
