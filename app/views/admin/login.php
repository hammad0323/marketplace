<div class="form-card">
    <h1>Admin Login</h1>
    <form method="post" action="/admin/login">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Log In</button>
    </form>
</div>
