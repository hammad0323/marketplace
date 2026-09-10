<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$posts = mysqli_query(db(), "
    SELECT p.*, u.full_name AS author_name FROM blog_posts p JOIN users u ON u.id = p.author_id
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Blog';
$heading = 'Blog';
$extraScripts = '<script defer src="/assets/js/rich-editor.js"></script><script defer src="/assets/js/admin-blog.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <button class="btn btn-primary" id="add-post-btn"><i class="ri-add-line"></i> New Post</button>
</div>

<?php if (!$posts): ?>
<div class="card empty-state" data-reveal><i class="ri-quill-pen-line"></i><h4>No blog posts yet</h4><p>Write your first post to start publishing health content on the site.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Title</th><th>Author</th><th>Status</th><th>Published</th><th>Actions</th></tr></thead>
        <tbody id="post-tbody">
        <?php foreach ($posts as $p): ?>
        <tr data-post-id="<?= (int) $p['id'] ?>"
            data-title="<?= e($p['title']) ?>" data-excerpt="<?= e($p['excerpt'] ?? '') ?>"
            data-status="<?= e($p['status']) ?>"
            data-meta-title="<?= e($p['meta_title'] ?? '') ?>" data-meta-description="<?= e($p['meta_description'] ?? '') ?>">
            <td style="max-width:320px;"><?= e($p['title']) ?></td>
            <td><?= e($p['author_name']) ?></td>
            <td><span class="status-pill status-<?= $p['status'] === 'published' ? 'active' : 'pending' ?>"><?= ucfirst($p['status']) ?></span></td>
            <td><?= $p['published_at'] ? format_date($p['published_at']) : '—' ?></td>
            <td style="white-space:nowrap;">
                <?php if ($p['status'] === 'published'): ?>
                <a href="<?= e(blog_url($p['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm btn-edit-post">Edit</button>
                <button class="btn btn-danger btn-sm btn-delete-post">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<script>
window.BLOG_POST_CONTENT = <?= json_encode(array_column($posts, 'content', 'id')) ?>;
</script>

<div class="modal-overlay" id="post-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:760px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;max-height:88vh;overflow-y:auto;">
            <h3 style="margin-bottom:20px;" id="post-modal-title">New Post</h3>
            <form id="post-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="post-id" value="0">
                <div class="form-group" data-field="title">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" id="post-title" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Excerpt (shown in the blog listing)</label>
                    <textarea class="form-control" name="excerpt" id="post-excerpt" rows="2" maxlength="300"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Featured Image (optional)</label>
                    <input type="file" class="form-control" name="featured_image" accept=".jpg,.jpeg,.png,.webp">
                </div>
                <div class="form-group" data-field="content">
                    <label class="form-label">Content</label>
                    <div data-rich-editor data-target="#post-content"></div>
                    <textarea id="post-content" name="content"></textarea>
                    <div class="form-error"></div>
                </div>
                <div class="divider-fade"></div>
                <h4 style="font-size:14.5px;margin-bottom:4px;">SEO <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></h4>
                <p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:14px;">Leave blank to auto-generate from the title/excerpt above.</p>
                <div class="form-group">
                    <label class="form-label">Meta Title</label>
                    <input type="text" class="form-control" name="meta_title" id="post-meta-title" maxlength="200" placeholder="Defaults to: Title — <?= e(SITE_NAME) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Description</label>
                    <textarea class="form-control" name="meta_description" id="post-meta-description" rows="2" maxlength="300" placeholder="Defaults to the excerpt/first 155 characters of content"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status" id="post-status">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Save Post</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
