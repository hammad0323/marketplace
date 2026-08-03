<div class="form-card">
    <h1>Create Account</h1>
    <form method="post" action="/customer/register">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e($_GET['redirect_to'] ?? '/') ?>">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Create Account</button>
    </form>
    <p style="margin-top:1rem;">Already have an account? <a href="/customer/login">Log in</a></p>
</div>
