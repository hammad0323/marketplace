<div class="form-card form-card-wide">
    <h1>Add Product</h1>

    <?php if (!$categories): ?>
        <p>You don't have any approved selling categories yet.
           <a href="/vendor/dashboard/categories">Request one</a> and wait for admin approval.</p>
    <?php else: ?>
        <form method="post" action="/vendor/dashboard/products/create">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="title">Product Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="image_urls">Image URLs (one per line)</label>
                <textarea id="image_urls" name="image_urls" rows="3"></textarea>
                <small>File upload isn't wired up yet — paste hosted image URLs for now.</small>
            </div>
            <button type="submit" class="btn">Publish Product</button>
        </form>
    <?php endif; ?>
</div>
