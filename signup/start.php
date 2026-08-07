<?php
require __DIR__ . '/../config/config.php';

$RESERVED_SUBDOMAINS = ['www', 'platform', 'api', 'admin', 'static', 'assets', 'mail', 'ftp', 'signup', 'app', 'demo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
    $businessName = trim($_POST['business_name'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerEmail = trim($_POST['owner_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $termsAccepted = isset($_POST['terms_accepted']);

    if ($businessName === '' || $ownerName === '' || $ownerEmail === '' || strlen($password) < 8) {
        mp_flash('error', 'Please fill in all required fields (password must be at least 8 characters).');
        mp_redirect('start.php');
    }

    if (!preg_match('/^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$/', $subdomain)) {
        mp_flash('error', 'Subdomain must be 3-63 characters: lowercase letters, numbers, and hyphens only.');
        mp_redirect('start.php');
    }

    if (in_array($subdomain, $RESERVED_SUBDOMAINS, true)) {
        mp_flash('error', 'That subdomain is reserved. Please choose another.');
        mp_redirect('start.php');
    }

    if (!$termsAccepted) {
        mp_flash('error', 'You must accept the Terms of Service to create your marketplace.');
        mp_redirect('start.php');
    }

    if (mp_find_tenant_by_subdomain($subdomain)) {
        mp_flash('error', 'That subdomain is already taken. Please choose another.');
        mp_redirect('start.php');
    }

    mp_db_begin_transaction();

    try {
        $tenantId = mp_insert_tenant([
            'subdomain'     => $subdomain,
            'business_name' => $businessName,
            'plan'          => 'trial',
            'status'        => 'trial',
            'owner_email'   => $ownerEmail,
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
        ]);

        // Set the ambient tenant immediately so every insert below in
        // this same request auto-injects the correct tenant_id (see
        // mp_db_insert() in config/database.php) without needing to be
        // special-cased individually.
        mp_set_current_tenant(mp_find_tenant($tenantId));

        $adminId = mp_insert_admin([
            'name'          => $ownerName,
            'email'         => $ownerEmail,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'super_admin',
        ]);

        mp_seed_default_marketplace_types($tenantId);

        mp_set_setting('site_name', $businessName);
        mp_set_setting('site_tagline', 'Welcome to ' . $businessName);
        mp_set_setting('contact_email', $ownerEmail);
        mp_set_setting('currency_code', 'USD');
        mp_set_setting('currency_symbol', '$');
        mp_set_setting('commission_rate_artisan', 10);
        mp_set_setting('commission_rate_business', 12);
        mp_set_setting('maintenance_mode', false);
        mp_set_setting('vendor_registration_enabled', true);

        mp_log_activity('admin', $adminId, 'tenant.signed_up', 'tenant', $tenantId, $businessName);

        mp_db_commit();
    } catch (Throwable $e) {
        mp_db_rollback();
        mp_flash('error', 'Something went wrong creating your marketplace. Please try again.');
        mp_redirect('start.php');
    }

    mp_redirect('welcome.php?subdomain=' . urlencode($subdomain));
}

$pageTitle = 'Start Your Marketplace';
require __DIR__ . '/../templates/platform-header.php';
?>

<div class="form-card reveal">
    <h1>Start Your Marketplace</h1>
    <p style="color:var(--ink-500);">Free 14-day trial. No credit card required.</p>
    <form method="post" action="start.php">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="business_name">Business Name</label>
            <input type="text" id="business_name" name="business_name" required>
        </div>
        <div class="form-group">
            <label for="subdomain">Choose Your Subdomain</label>
            <input type="text" id="subdomain" name="subdomain" pattern="[a-z0-9-]{3,63}" placeholder="yourbusiness" required>
            <small style="color:var(--ink-500);">yourbusiness.<?= mp_e(APP_BASE_DOMAIN) ?></small>
        </div>
        <div class="form-group">
            <label for="owner_name">Your Name</label>
            <input type="text" id="owner_name" name="owner_name" required>
        </div>
        <div class="form-group">
            <label for="owner_email">Your Email</label>
            <input type="email" id="owner_email" name="owner_email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="terms_accepted" required> I accept the Terms of Service</label>
        </div>
        <button type="submit" class="btn">Create My Marketplace</button>
    </form>
</div>

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
