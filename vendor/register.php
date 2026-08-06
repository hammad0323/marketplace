<?php
require __DIR__ . '/../config/config.php';

if (!mp_get_setting('vendor_registration_enabled', true)) {
    $pageTitle = 'Vendor Registration Closed';
    $theme = 'main';
    require __DIR__ . '/../templates/header.php';
    ?>
    <div class="empty-state reveal">
        <span class="empty-state-icon">🚧</span>
        <h2>Vendor registration is temporarily closed</h2>
        <p>We're not accepting new vendor applications right now. Please check back later.</p>
        <a class="btn" href="<?= mp_e(ROUTE_HOME) ?>">Back to Home</a>
    </div>
    <?php
    require __DIR__ . '/../templates/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $storeName = trim($_POST['store_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $vendorTypeSlug = $_POST['vendor_type'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $bankAccountTitle = trim($_POST['bank_account_title'] ?? '');
    $bankAccountNumber = trim($_POST['bank_account_number'] ?? '');
    $termsAccepted = isset($_POST['terms_accepted']);
    $requestedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []);

    if ($storeName === '' || $email === '' || $phone === '' || strlen($password) < 8
        || !in_array($vendorTypeSlug, ['artisan', 'business'], true)) {
        mp_flash('error', 'Please fill in all required fields (password must be at least 8 characters).');
        mp_redirect('register.php');
    }

    if (!$termsAccepted) {
        mp_flash('error', 'You must accept the Vendor Terms & Seller Agreement to create a store.');
        mp_redirect('register.php');
    }

    if (mp_find_vendor_by_email($email)) {
        mp_flash('error', 'An account with that email already exists.');
        mp_redirect('register.php');
    }

    $marketplaceType = mp_find_marketplace_type_by_slug($vendorTypeSlug);
    $slugBase = mp_slugify($storeName);
    $newSlug = $slugBase;
    $suffix = 1;
    while (mp_find_vendor_by_slug($newSlug)) {
        $newSlug = $slugBase . '-' . (++$suffix);
    }

    $vendorId = mp_insert_vendor([
        'marketplace_type_id' => $marketplaceType['id'],
        'store_name'          => $storeName,
        'slug'                => $newSlug,
        'email'               => $email,
        'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
        'phone'               => $phone,
        'tax_id'               => $taxId ?: null,
        'bank_name'            => $bankName ?: null,
        'bank_account_title'   => $bankAccountTitle ?: null,
        'bank_account_number'  => $bankAccountNumber ?: null,
        'status'              => 'pending',
        'terms_accepted_at'   => date('Y-m-d H:i:s'),
    ]);

    // Business vendors request the categories they want to sell in; an
    // admin must approve each one before products can use them.
    // Categories share one ID space across marketplace types, so the
    // submitted IDs are filtered down to ones that actually belong to
    // the business marketplace before being stored.
    if ($vendorTypeSlug === 'business' && $requestedCategoryIds) {
        $validCategoryIds = mp_filter_category_ids_by_marketplace($requestedCategoryIds, $marketplaceType['id']);
        if ($validCategoryIds) {
            mp_request_vendor_categories($vendorId, $validCategoryIds);
        }
    }

    $verifyToken = mp_set_vendor_verification_token($vendorId);
    $verifyUrl = ROUTE_VENDOR . 'verify.php?token=' . $verifyToken;

    mp_notify('vendor.welcome', $email, ['store_name' => $storeName, 'verify_url' => $verifyUrl]);
    mp_notify('admin.new_vendor_registration', 'admin@marketplace.test', ['vendor_id' => $vendorId, 'store_name' => $storeName]);
    mp_log_activity('vendor', $vendorId, 'vendor.registered', 'vendor', $vendorId, "{$storeName} registered as a {$vendorTypeSlug} vendor");

    mp_login_vendor(['id' => $vendorId]);

    $_SESSION['_just_registered_verify_url'] = $verifyUrl;
    mp_redirect('registered.php');
}

$artisanType = mp_find_marketplace_type_by_slug('artisan');
$businessType = mp_find_marketplace_type_by_slug('business');
$artisanCategories = mp_active_categories_by_marketplace($artisanType['id']);
$businessCategories = mp_active_categories_by_marketplace($businessType['id']);

$pageTitle = 'Become a Vendor';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card form-card-wide reveal">
    <h1>Become a Vendor</h1>
    <p>Choose the marketplace you want to sell in. Your store stays in <strong>pending</strong>
       status — you can complete your profile right away, but products and orders unlock only
       after an admin approves your application.</p>

    <ol class="wizard-steps" id="wizard-steps">
        <li class="wizard-step is-active" data-step="1"><span>1</span>Account</li>
        <li class="wizard-step" data-step="2"><span>2</span>Store Details</li>
        <li class="wizard-step" data-step="3"><span>3</span>Payout Info</li>
        <li class="wizard-step" data-step="4"><span>4</span>Review</li>
    </ol>

    <form method="post" action="register.php" id="vendor-register-form" novalidate>
        <?= mp_csrf_field() ?>

        <fieldset class="wizard-panel is-active" data-step="1">
            <div class="form-group">
                <label>Vendor Type</label>
                <label style="font-weight:400;"><input type="radio" name="vendor_type" value="artisan" onclick="toggleCategories()" checked> Artisan (handmade creator)</label><br>
                <label style="font-weight:400;"><input type="radio" name="vendor_type" value="business" onclick="toggleCategories()"> Business Shop (retail store)</label>
            </div>

            <div class="form-group">
                <label for="store_name">Store Name</label>
                <input type="text" id="store_name" name="store_name" required value="<?= mp_e(mp_old('store_name')) ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= mp_e(mp_old('email')) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" required>
                <small>At least 8 characters.</small>
            </div>
        </fieldset>

        <fieldset class="wizard-panel" data-step="2">
            <div class="form-group">
                <label for="phone">Store Phone</label>
                <input type="text" id="phone" name="phone" required placeholder="+92 300 1234567">
            </div>

            <div class="form-group">
                <label for="tax_id">Business Registration / Tax ID <span style="font-weight:400; color:var(--ink-500);">(optional)</span></label>
                <input type="text" id="tax_id" name="tax_id" placeholder="NTN / CNIC / Business Reg. No.">
            </div>

            <div class="form-group" id="business-categories" style="display:none;">
                <label>Which categories would you like to sell in?</label>
                <small>Each category you request needs separate admin approval before you can list products in it.</small>
                <div class="checkbox-grid">
                    <?php foreach ($businessCategories as $category): ?>
                        <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= mp_e($category['name']) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="wizard-panel" data-step="3">
            <p style="color:var(--ink-500); font-size:.9rem; margin-top:0;">Optional now — you can add or change this anytime from your store profile. We'll use it once payouts go live.</p>
            <div class="form-group">
                <label for="bank_name">Bank Name</label>
                <input type="text" id="bank_name" name="bank_name">
            </div>
            <div class="form-group">
                <label for="bank_account_title">Account Title</label>
                <input type="text" id="bank_account_title" name="bank_account_title">
            </div>
            <div class="form-group">
                <label for="bank_account_number">Account Number / IBAN</label>
                <input type="text" id="bank_account_number" name="bank_account_number">
            </div>
        </fieldset>

        <fieldset class="wizard-panel" data-step="4">
            <h2 style="margin-top:0;">Review Your Application</h2>
            <div class="wizard-review" id="wizard-review"></div>

            <div class="form-group" style="margin-top:1.25rem;">
                <label style="font-weight:400; display:flex; gap:.5rem; align-items:flex-start;">
                    <input type="checkbox" name="terms_accepted" id="terms_accepted" required style="margin-top:.25rem;">
                    <span>I agree to the Vendor Terms &amp; Seller Agreement, including the marketplace commission on completed sales and the category/product approval process.</span>
                </label>
            </div>
        </fieldset>

        <div class="wizard-nav">
            <button type="button" class="btn btn-secondary" id="wizard-back" style="visibility:hidden;">Back</button>
            <button type="button" class="btn" id="wizard-next">Continue</button>
            <button type="submit" class="btn" id="wizard-submit" style="display:none;">Create My Store</button>
        </div>
    </form>

    <p style="margin-top:1rem;">Already have a store? <a href="login.php">Log in</a></p>
</div>

<script>
function toggleCategories() {
    var isBusiness = document.querySelector('input[name="vendor_type"]:checked').value === 'business';
    document.getElementById('business-categories').style.display = isBusiness ? 'block' : 'none';
}

(function () {
    var form = document.getElementById('vendor-register-form');
    var panels = Array.prototype.slice.call(form.querySelectorAll('.wizard-panel'));
    var steps = Array.prototype.slice.call(document.querySelectorAll('#wizard-steps .wizard-step'));
    var backBtn = document.getElementById('wizard-back');
    var nextBtn = document.getElementById('wizard-next');
    var submitBtn = document.getElementById('wizard-submit');
    var current = 0;

    function showStep(index) {
        panels.forEach(function (panel, i) { panel.classList.toggle('is-active', i === index); });
        steps.forEach(function (step, i) {
            step.classList.toggle('is-active', i === index);
            step.classList.toggle('is-done', i < index);
        });
        backBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
        nextBtn.style.display = index === panels.length - 1 ? 'none' : 'inline-flex';
        submitBtn.style.display = index === panels.length - 1 ? 'inline-flex' : 'none';
        if (index === panels.length - 1) {
            renderReview();
        }
        current = index;
    }

    function panelIsValid(panel) {
        var fields = panel.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].checkValidity()) {
                fields[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    function renderReview() {
        var type = document.querySelector('input[name="vendor_type"]:checked').value === 'business' ? 'Business Shop' : 'Artisan';
        var rows = [
            ['Vendor Type', type],
            ['Store Name', document.getElementById('store_name').value],
            ['Email', document.getElementById('email').value],
            ['Phone', document.getElementById('phone').value],
            ['Tax / Registration ID', document.getElementById('tax_id').value || '—'],
            ['Bank', document.getElementById('bank_name').value || '—'],
        ];
        document.getElementById('wizard-review').innerHTML = rows.map(function (r) {
            return '<div class="wizard-review-row"><span>' + r[0] + '</span><strong>' + (r[1].replace(/</g, '&lt;') || '—') + '</strong></div>';
        }).join('');
    }

    nextBtn.addEventListener('click', function () {
        if (!panelIsValid(panels[current])) { return; }
        showStep(Math.min(current + 1, panels.length - 1));
    });
    backBtn.addEventListener('click', function () {
        showStep(Math.max(current - 1, 0));
    });

    showStep(0);
})();
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
