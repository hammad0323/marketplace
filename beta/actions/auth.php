<?php
require __DIR__ . '/../config/config.php';

$do = $_GET['do'] ?? $_POST['do'] ?? '';

// --------------------------------------------------------------
// CUSTOMER LOGIN
// --------------------------------------------------------------
if ($do === 'customer_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $customer = db_fetch_one("SELECT * FROM customers WHERE email = ?", 's', [$email]);
    if (!$customer || !$customer['password_hash'] || !password_verify($password, $customer['password_hash'])) {
        flash('error', 'Invalid email or password.');
        redirect(base_url('login.php'));
    }
    if ($customer['status'] !== 'active') {
        flash('error', 'Your account has been ' . $customer['status'] . '. Contact support.');
        redirect(base_url('login.php'));
    }
    $_SESSION['customer'] = ['id' => $customer['id'], 'first_name' => $customer['first_name'], 'last_name' => $customer['last_name'], 'email' => $customer['email']];
    db_exec("UPDATE customers SET last_login = NOW() WHERE id = ?", 'i', [$customer['id']]);
    $redirect = $_POST['redirect'] ?? '';
    redirect($redirect ? base_url(ltrim($redirect, '/')) : customer_url('dashboard.php'));
}

// --------------------------------------------------------------
// CUSTOMER REGISTER
// --------------------------------------------------------------
if ($do === 'customer_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$first || !$last || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        flash('error', 'Please fill all fields correctly. Password must be at least 6 characters.');
        redirect(base_url('register.php'));
    }
    if ($password !== $confirm) {
        flash('error', 'Passwords do not match.');
        redirect(base_url('register.php'));
    }
    if (db_fetch_one("SELECT id FROM customers WHERE email = ?", 's', [$email])) {
        flash('error', 'An account with this email already exists.');
        redirect(base_url('register.php'));
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $id = db_insert("INSERT INTO customers (first_name,last_name,email,phone,password_hash) VALUES (?,?,?,?,?)",
        'sssss', [$first, $last, $email, $phone, $hash]);
    $_SESSION['customer'] = ['id' => $id, 'first_name' => $first, 'last_name' => $last, 'email' => $email];
    audit_log('customer', $id, "$first $last", 'Registered', 'auth', $id, 'New customer account created');
    flash('success', 'Welcome to ' . site_name() . '!');
    redirect(customer_url('dashboard.php'));
}

// --------------------------------------------------------------
// GOOGLE OAUTH (customers only)
// --------------------------------------------------------------
if ($do === 'google_login') {
    $clientId = get_setting('google_client_id');
    if (!$clientId) { flash('error', 'Google login is not configured yet.'); redirect(base_url('login.php')); }
    $redirectUri = base_url('actions/auth.php?do=google_callback');
    $params = http_build_query([
        'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code',
        'scope' => 'openid email profile', 'access_type' => 'online', 'prompt' => 'select_account',
    ]);
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

if ($do === 'google_callback') {
    $code = $_GET['code'] ?? '';
    if (!$code) { flash('error', 'Google sign-in was cancelled.'); redirect(base_url('login.php')); }

    $clientId = get_setting('google_client_id');
    $clientSecret = get_setting('google_client_secret');
    $redirectUri = base_url('actions/auth.php?do=google_callback');

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ]),
    ]);
    $tokenResponse = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($tokenResponse['access_token'])) {
        flash('error', 'Google sign-in failed. Please try again.');
        redirect(base_url('login.php'));
    }

    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($tokenResponse['access_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userInfo = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($userInfo['sub']) || empty($userInfo['email'])) {
        flash('error', 'Could not fetch Google account details.');
        redirect(base_url('login.php'));
    }

    $googleId = $userInfo['sub']; $email = $userInfo['email'];
    $existing = db_fetch_one("SELECT c.* FROM google_accounts g JOIN customers c ON c.id = g.customer_id WHERE g.google_id = ?", 's', [$googleId]);

    if ($existing) {
        $customer = $existing;
    } else {
        $customer = db_fetch_one("SELECT * FROM customers WHERE email = ?", 's', [$email]);
        if (!$customer) {
            $nameParts = explode(' ', $userInfo['name'] ?? 'Google User', 2);
            $id = db_insert("INSERT INTO customers (first_name,last_name,email,password_hash,email_verified) VALUES (?,?,?,?,1)",
                'ssss', [$nameParts[0], $nameParts[1] ?? '', $email, null]);
            $customer = ['id' => $id, 'first_name' => $nameParts[0], 'last_name' => $nameParts[1] ?? '', 'email' => $email];
        }
        db_insert("INSERT INTO google_accounts (customer_id, google_id, email) VALUES (?,?,?)", 'iss', [$customer['id'], $googleId, $email]);
    }

    $_SESSION['customer'] = ['id' => $customer['id'], 'first_name' => $customer['first_name'], 'last_name' => $customer['last_name'], 'email' => $customer['email']];
    redirect(customer_url('dashboard.php'));
}

// --------------------------------------------------------------
// ADMIN LOGIN
// --------------------------------------------------------------
if ($do === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $admin = db_fetch_one("SELECT * FROM admins WHERE email = ? AND status='active'", 's', [$email]);
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        flash('error', 'Invalid admin credentials.');
        redirect(admin_url('login.php'));
    }
    $_SESSION['admin'] = ['id' => $admin['id'], 'name' => $admin['name'], 'email' => $admin['email']];
    db_exec("UPDATE admins SET last_login = NOW() WHERE id = ?", 'i', [$admin['id']]);
    audit_log('admin', $admin['id'], $admin['name'], 'Logged in', 'auth');
    redirect(admin_url('index.php'));
}

// --------------------------------------------------------------
// SHOP OWNER / STAFF LOGIN (one form, tries owner then staff)
// --------------------------------------------------------------
if ($do === 'shop_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $owner = db_fetch_one("SELECT o.*, s.id as shop_id, s.shop_name, s.slug as shop_slug, s.status as shop_status
                            FROM shop_owners o LEFT JOIN shops s ON s.owner_id = o.id WHERE o.email = ?", 's', [$email]);
    if ($owner && password_verify($password, $owner['password_hash'])) {
        if ($owner['status'] !== 'active') { flash('error', 'Your account is inactive. Contact admin.'); redirect(shop_url('login.php')); }
        $_SESSION['shop_owner'] = ['id' => $owner['id'], 'name' => $owner['name'], 'email' => $owner['email'],
            'shop_id' => $owner['shop_id'], 'shop_name' => $owner['shop_name'], 'shop_slug' => $owner['shop_slug'], 'shop_status' => $owner['shop_status']];
        db_exec("UPDATE shop_owners SET last_login = NOW() WHERE id = ?", 'i', [$owner['id']]);
        audit_log('shop_owner', $owner['id'], $owner['name'], 'Logged in', 'auth');
        redirect(shop_url('dashboard.php'));
    }

    $staff = db_fetch_one("SELECT st.*, s.shop_name, s.slug as shop_slug FROM shop_staff st
                            JOIN shops s ON s.id = st.shop_id WHERE st.email = ?", 's', [$email]);
    if ($staff && password_verify($password, $staff['password_hash'])) {
        if ($staff['status'] !== 'active') { flash('error', 'Your staff account has been disabled.'); redirect(shop_url('login.php')); }
        $perms = db_fetch_all("SELECT permission_key, allowed FROM staff_permissions WHERE staff_id = ?", 'i', [$staff['id']]);
        $permMap = [];
        foreach ($perms as $p) $permMap[$p['permission_key']] = (bool)$p['allowed'];
        $_SESSION['shop_staff'] = ['id' => $staff['id'], 'name' => $staff['name'], 'email' => $staff['email'],
            'shop_id' => $staff['shop_id'], 'shop_name' => $staff['shop_name'], 'shop_slug' => $staff['shop_slug']];
        $_SESSION['staff_permissions'] = $permMap;
        db_exec("UPDATE shop_staff SET last_login = NOW() WHERE id = ?", 'i', [$staff['id']]);
        audit_log('shop_staff', $staff['id'], $staff['name'], 'Logged in', 'auth');
        redirect(employee_url('dashboard.php'));
    }

    flash('error', 'Invalid email or password.');
    redirect(shop_url('login.php'));
}

// --------------------------------------------------------------
// SHOP OWNER REGISTRATION (application, pending admin approval)
// --------------------------------------------------------------
if ($do === 'shop_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ownerName = trim($_POST['owner_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $shopName = trim($_POST['shop_name'] ?? '');
    $description = clean_html($_POST['shop_description'] ?? '');
    $address = trim($_POST['business_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'Pakistan');

    if (!$ownerName || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || !$shopName) {
        flash('error', 'Please fill all required fields correctly.');
        redirect(shop_url('register.php'));
    }
    if (db_fetch_one("SELECT id FROM shop_owners WHERE email = ?", 's', [$email])) {
        flash('error', 'An account with this email already exists.');
        redirect(shop_url('register.php'));
    }

    $logoUpload = handle_image_upload('logo', 'shops');
    $coverUpload = handle_image_upload('cover_image', 'shops');
    if (isset($logoUpload['error'])) { flash('error', $logoUpload['error']); redirect(shop_url('register.php')); }
    if (isset($coverUpload['error'])) { flash('error', $coverUpload['error']); redirect(shop_url('register.php')); }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ownerId = db_insert("INSERT INTO shop_owners (name,email,phone,password_hash) VALUES (?,?,?,?)", 'ssss', [$ownerName, $email, $phone, $hash]);

    $slug = unique_slug('shops', slugify($shopName));
    $shopId = db_insert("INSERT INTO shops (owner_id, shop_name, slug, description, logo, cover_image, business_address, city, country, phone, email, status)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')",
        'issssssssss', [$ownerId, $shopName, $slug, $description, $logoUpload['path'] ?? null, $coverUpload['path'] ?? null, $address, $city, $country, $phone, $email]);

    // Default shop sections/layout for every new shop
    db_insert("INSERT INTO shop_sections (shop_id, section_type, heading, sort_order) VALUES (?, 'featured_products', 'Featured Products', 1)", 'i', [$shopId]);
    db_insert("INSERT INTO shop_sections (shop_id, section_type, heading, sort_order) VALUES (?, 'latest_products', 'Latest Products', 2)", 'i', [$shopId]);

    notify('admin', 1, 'New Shop Application', "$shopName has applied to sell on " . site_name() . '.', 'admin/shops/index.php');
    audit_log('shop_owner', $ownerId, $ownerName, 'Applied for shop', 'shops', $shopId, "Shop application: $shopName");

    flash('success', 'Your shop application has been submitted and is pending admin approval.');
    redirect(shop_url('login.php'));
}

// --------------------------------------------------------------
// LOGOUT (role-scoped)
// --------------------------------------------------------------
if ($do === 'logout_customer') { unset($_SESSION['customer']); redirect(base_url()); }
if ($do === 'logout_admin') { unset($_SESSION['admin']); redirect(admin_url('login.php')); }
if ($do === 'logout_shop') { unset($_SESSION['shop_owner'], $_SESSION['shop_staff'], $_SESSION['staff_permissions']); redirect(shop_url('login.php')); }

redirect(base_url());
