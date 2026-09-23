<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$posts = mysqli_query(db(), "
    SELECT p.*, u.full_name AS author_name FROM blog_posts p JOIN users u ON u.id = p.author_id
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$allDoctors = mysqli_query(db(), "
    SELECT d.id, u.full_name AS name, GROUP_CONCAT(s.name SEPARATOR ', ') AS spec
    FROM doctors d JOIN users u ON u.id = d.user_id
    LEFT JOIN doctor_specializations ds ON ds.doctor_id = d.id
    LEFT JOIN specializations s ON s.id = ds.specialization_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    GROUP BY d.id ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);
$allMedicines = mysqli_query(db(), "SELECT id, name FROM medicine_info WHERE status = 'published' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$existingCategories = mysqli_query(db(), "SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Blog';
$heading = 'Blog';
$extraScripts = '<script defer src="' . asset_url('/assets/js/rich-editor.js') . '"></script>'
    . '<script defer src="' . asset_url('/assets/js/blog-block-editor.js') . '"></script>'
    . '<script defer src="' . asset_url('/assets/js/admin-blog.js') . '"></script>';
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
            data-status="<?= e($p['status']) ?>" data-category="<?= e($p['category'] ?? '') ?>"
            data-meta-title="<?= e($p['meta_title'] ?? '') ?>" data-meta-description="<?= e($p['meta_description'] ?? '') ?>">
            <td style="max-width:320px;"><?= e($p['title']) ?><?php if ($p['category']): ?><span class="badge badge-free" style="margin-left:8px;"><?= e($p['category']) ?></span><?php endif; ?></td>
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
window.BLOG_POST_BLOCKS = <?= json_encode(array_map(function ($p) {
    $b = json_decode($p['blocks'] ?? '', true);
    return is_array($b) ? $b : null;
}, array_column($posts, null, 'id'))) ?>;
window.ALL_DOCTORS = <?= json_encode(array_map(fn($d) => ['id' => (int) $d['id'], 'name' => $d['name'], 'spec' => $d['spec']], $allDoctors)) ?>;
window.ALL_MEDICINES = <?= json_encode(array_map(fn($m) => ['id' => (int) $m['id'], 'name' => $m['name']], $allMedicines)) ?>;
</script>

<div class="modal-overlay" id="post-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:820px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;max-height:88vh;overflow-y:auto;">
            <h3 style="margin-bottom:20px;" id="post-modal-title">New Post</h3>
            <form id="post-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="post-id" value="0">
                <input type="hidden" name="blocks" id="post-blocks-json" value="[]">
                <div class="form-group" data-field="title">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" name="title" id="post-title" required>
                    <div class="form-error"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Category (optional)</label>
                        <input type="text" class="form-control" name="category" id="post-category" list="blog-category-list" placeholder="e.g. Mental Health">
                        <datalist id="blog-category-list">
                            <?php foreach ($existingCategories as $c): ?><option value="<?= e($c['category']) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Featured Image (optional)</label>
                        <input type="file" class="form-control" name="featured_image" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Excerpt (shown in the blog listing)</label>
                    <textarea class="form-control" name="excerpt" id="post-excerpt" rows="2" maxlength="300"></textarea>
                </div>
                <div class="form-group" data-field="content">
                    <label class="form-label">Content</label>
                    <p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:12px;">Build the post from blocks — mix text, images, videos, maps, doctor cards, comparison tables, FAQs and more, in any order.</p>
                    <div id="blog-blocks-list"></div>
                    <div id="blog-blocks-empty" class="empty-state card" style="padding:24px;"><i class="ri-layout-3-line"></i><p style="margin:0;">No blocks yet — click "Add Block" to start writing.</p></div>
                    <div style="position:relative;margin-top:12px;">
                        <button type="button" class="btn btn-outline" id="add-block-btn" data-dropdown-trigger="add-block-menu"><i class="ri-add-line"></i> Add Block</button>
                        <div id="add-block-menu" class="dropdown-menu">
                            <?php foreach ([
                                'heading' => ['ri-h-1', 'Heading'], 'richtext' => ['ri-text', 'Text'],
                                'image' => ['ri-image-line', 'Image'], 'gallery' => ['ri-gallery-line', 'Image Gallery / Carousel'],
                                'video' => ['ri-video-line', 'Video'], 'map' => ['ri-map-pin-line', 'Map'],
                                'doctor' => ['ri-user-heart-line', 'Doctor Card'], 'doctor_carousel' => ['ri-team-line', 'Featured Doctors Carousel'],
                                'medicine' => ['ri-capsule-line', 'Medicine Card'], 'comparison_table' => ['ri-table-line', 'Doctor Comparison Table'],
                                'fact_box' => ['ri-file-list-3-line', 'Quick Facts Box'], 'callout' => ['ri-lightbulb-line', 'Highlight / Callout Box'],
                                'faq' => ['ri-question-line', 'FAQ Accordion'], 'toc' => ['ri-list-check-2', 'Table of Contents'],
                                'quote' => ['ri-double-quotes-l', 'Quote'], 'divider' => ['ri-separator', 'Divider'],
                            ] as $type => $info): ?>
                            <a href="#" data-block-type="<?= e($type) ?>"><i class="<?= e($info[0]) ?>"></i> <?= e($info[1]) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
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
