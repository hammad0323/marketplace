<div class="form-card form-card-wide">
    <h1>Become a Vendor</h1>
    <p>Choose the marketplace you want to sell in. Your store stays in <strong>pending</strong>
       status — you can complete your profile right away, but products and orders unlock only
       after an admin approves your application.</p>

    <form method="post" action="/vendor/register">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Vendor Type</label>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="artisan" onclick="toggleCategories()" checked> Artisan (handmade creator)</label><br>
            <label style="font-weight:400;"><input type="radio" name="vendor_type" value="business" onclick="toggleCategories()"> Business Shop (retail store)</label>
        </div>

        <div class="form-group">
            <label for="store_name">Store Name</label>
            <input type="text" id="store_name" name="store_name" required value="<?= e(old('store_name')) ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e(old('email')) ?>">
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
            <small>At least 8 characters.</small>
        </div>

        <div class="form-group" id="business-categories" style="display:none;">
            <label>Which categories would you like to sell in?</label>
            <small>Each category you request needs separate admin approval before you can list products in it.</small>
            <div class="checkbox-grid">
                <?php foreach ($businessCategories as $category): ?>
                    <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= e($category['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn">Create My Store</button>
    </form>

    <p style="margin-top:1rem;">Already have a store? <a href="/vendor/login">Log in</a></p>
</div>

<script>
function toggleCategories() {
    var isBusiness = document.querySelector('input[name="vendor_type"]:checked').value === 'business';
    document.getElementById('business-categories').style.display = isBusiness ? 'block' : 'none';
}
</script>
