<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

$errors = [];
$cities = db_select($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order');
$privacyFields = ['show_phone', 'show_email', 'show_address', 'show_calendar', 'show_pricing', 'show_reviews', 'show_gallery', 'show_map'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'business') {
        $businessName = clean_input($_POST['business_name'] ?? '');
        $description = clean_input($_POST['description'] ?? '');
        $website = clean_input($_POST['website'] ?? '');
        $address = clean_input($_POST['address'] ?? '');
        $cityId = (int) ($_POST['city_id'] ?? 0) ?: null;
        $latitude = clean_input($_POST['latitude'] ?? '') ?: null;
        $longitude = clean_input($_POST['longitude'] ?? '') ?: null;
        $social = [
            'facebook' => clean_input($_POST['social_facebook'] ?? ''),
            'instagram' => clean_input($_POST['social_instagram'] ?? ''),
            'twitter' => clean_input($_POST['social_twitter'] ?? ''),
        ];
        require_field($businessName, 'Business name', $errors);

        $logo = $provider['logo'];
        if (!empty($_FILES['logo']['name'])) {
            $upload = upload_file('logo', 'providers');
            if (!$upload['ok'] && $upload['error']) {
                $errors[] = $upload['error'];
            } elseif ($upload['ok']) {
                $logo = $upload['path'];
            }
        }
        $cover = $provider['cover_image'];
        if (!empty($_FILES['cover_image']['name'])) {
            $upload = upload_file('cover_image', 'providers');
            if (!$upload['ok'] && $upload['error']) {
                $errors[] = $upload['error'];
            } elseif ($upload['ok']) {
                $cover = $upload['path'];
            }
        }

        if (!$errors) {
            db_execute(
                $conn,
                'UPDATE providers SET business_name=?, description=?, website=?, address=?, city_id=?, latitude=?, longitude=?, social_links=?, logo=?, cover_image=? WHERE id=?',
                [$businessName, $description, $website, $address, $cityId, $latitude, $longitude, json_encode($social), $logo, $cover, (int) $provider['id']]
            );
            flash_set('success', 'Business profile updated.');
            redirect('/provider/profile.php');
        }
    } elseif ($form === 'privacy') {
        $updates = [];
        $params = [];
        foreach ($privacyFields as $field) {
            $updates[] = "$field = ?";
            $params[] = !empty($_POST[$field]) ? 1 : 0;
        }
        $params[] = (int) $provider['id'];
        db_execute($conn, 'UPDATE providers SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);
        flash_set('success', 'Privacy settings updated.');
        redirect('/provider/profile.php');
    } elseif ($form === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if (!$errors) {
            db_execute($conn, 'UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            flash_set('success', 'Password changed.');
            redirect('/provider/profile.php');
        }
    }
    $provider = db_select_one($conn, 'SELECT * FROM providers WHERE id = ?', [(int) $provider['id']]);
}

$social = json_decode((string) $provider['social_links'], true) ?: [];

$pageTitle = 'Business Profile';
$providerActiveTab = 'profile';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-shop"></i> Provider</span>
      <h1 class="section-heading">Business profile</h1>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <div class="panel" style="max-width:680px;">
      <h3 style="font-size:15px;margin-bottom:16px;">Business information</h3>
      <form method="post" class="form-w" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="business">
        <label>Business name</label>
        <input type="text" name="business_name" value="<?php echo e($provider['business_name']); ?>" required>
        <label>Description</label>
        <textarea name="description" rows="4"><?php echo e($provider['description']); ?></textarea>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
          <div>
            <label>City</label>
            <select name="city_id">
              <option value="">Select city</option>
              <?php foreach ($cities as $c): ?>
                <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $c['id'] === (int) $provider['city_id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Website</label><input type="url" name="website" value="<?php echo e($provider['website']); ?>" placeholder="https://"></div>
          <div><label>Address</label><input type="text" name="address" value="<?php echo e($provider['address']); ?>"></div>
          <div></div>
          <div><label>Latitude</label><input type="text" name="latitude" value="<?php echo e($provider['latitude']); ?>"></div>
          <div><label>Longitude</label><input type="text" name="longitude" value="<?php echo e($provider['longitude']); ?>"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 20px;">
          <div><label>Facebook</label><input type="url" name="social_facebook" value="<?php echo e($social['facebook'] ?? ''); ?>"></div>
          <div><label>Instagram</label><input type="url" name="social_instagram" value="<?php echo e($social['instagram'] ?? ''); ?>"></div>
          <div><label>Twitter / X</label><input type="url" name="social_twitter" value="<?php echo e($social['twitter'] ?? ''); ?>"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;margin-top:16px;">
          <div>
            <label>Logo</label>
            <?php if ($provider['logo']): ?><img src="<?php echo e($provider['logo']); ?>" style="width:56px;height:56px;border-radius:12px;object-fit:cover;margin-bottom:8px;"><?php endif; ?>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
          </div>
          <div>
            <label>Cover image</label>
            <?php if ($provider['cover_image']): ?><img src="<?php echo e($provider['cover_image']); ?>" style="width:100%;height:56px;border-radius:12px;object-fit:cover;margin-bottom:8px;"><?php endif; ?>
            <input type="file" name="cover_image" accept="image/png,image/jpeg,image/webp">
          </div>
        </div>
        <button type="submit" class="btn-w btn-primary" style="margin-top:20px;">Save changes</button>
      </form>
    </div>

    <div class="panel" style="max-width:680px;">
      <h3 style="font-size:15px;margin-bottom:6px;">Privacy settings</h3>
      <p class="form-hint" style="margin-bottom:16px;">Control what's publicly visible on your profile.</p>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="privacy">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <?php
          $labels = [
              'show_phone' => 'Show phone number', 'show_email' => 'Show email address', 'show_address' => 'Show exact address',
              'show_calendar' => 'Show availability calendar', 'show_pricing' => 'Show pricing', 'show_reviews' => 'Show reviews',
              'show_gallery' => 'Show photo gallery', 'show_map' => 'Show map',
          ];
          foreach ($privacyFields as $field):
          ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;">
              <input type="checkbox" name="<?php echo $field; ?>" value="1" <?php echo $provider[$field] ? 'checked' : ''; ?> style="width:auto;"> <?php echo $labels[$field]; ?>
            </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn-w btn-primary btn-sm" style="margin-top:20px;">Save privacy settings</button>
      </form>
    </div>

    <div class="panel" style="max-width:680px;">
      <h3 style="font-size:15px;margin-bottom:16px;">Change password</h3>
      <form method="post" class="form-w">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="password">
        <label>Current password</label>
        <input type="password" name="current_password" required>
        <label>New password</label>
        <input type="password" name="new_password" minlength="8" required>
        <label>Confirm new password</label>
        <input type="password" name="confirm_password" minlength="8" required>
        <button type="submit" class="btn-w btn-primary" style="margin-top:20px;">Update password</button>
      </form>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
