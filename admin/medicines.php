<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$medicines = mysqli_query(db(), "
    SELECT m.*, u.full_name AS author_name FROM medicine_info m JOIN users u ON u.id = m.author_id
    ORDER BY m.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$medicineFaqsById = [];
foreach ($medicines as $m) {
    $medicineFaqsById[$m['id']] = get_medicine_faqs($m['id']);
}

$csrfToken = csrf_token();
$pageTitle = 'Medicine Info';
$heading = 'Medicine Info';
$extraScripts = '<script defer src="/assets/js/rich-editor.js"></script><script defer src="/assets/js/seo-score.js"></script>'
    . '<script>window.MEDICINE_SAVE_URL="/ajax/admin-medicine-save.php";window.MEDICINE_DELETE_URL="/ajax/admin-medicine-delete.php";</script>'
    . '<script defer src="/assets/js/medicine-form.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<p style="color:var(--color-text-muted);margin-bottom:20px;max-width:720px;">
    Informational reference content — not a store listing. Published entries get their own public
    page so the site can rank when someone searches a medicine name; doctors can add their own from
    <strong>Doctor Dashboard → Medicine Info</strong>, and you can edit or remove any entry here.
</p>
<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <button class="btn btn-primary" id="add-medicine-btn"><i class="ri-add-line"></i> Add Medicine</button>
</div>

<?php if (!$medicines): ?>
<div class="card empty-state" data-reveal><i class="ri-capsule-line"></i><h4>No medicine entries yet</h4><p>Add the first one to start building out searchable medicine information.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Name</th><th>Author</th><th>SEO Score</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="medicine-tbody">
        <?php foreach ($medicines as $m): ?>
        <tr data-medicine-id="<?= (int) $m['id'] ?>"
            data-name="<?= e($m['name']) ?>" data-generic-name="<?= e($m['generic_name'] ?? '') ?>"
            data-category="<?= e($m['category'] ?? '') ?>" data-composition="<?= e($m['composition'] ?? '') ?>"
            data-focus-keyword="<?= e($m['focus_keyword'] ?? '') ?>"
            data-meta-title="<?= e($m['meta_title'] ?? '') ?>" data-meta-description="<?= e($m['meta_description'] ?? '') ?>"
            data-status="<?= e($m['status']) ?>">
            <td style="max-width:260px;"><?= e($m['name']) ?></td>
            <td><?= e($m['author_name']) ?></td>
            <td>
                <div class="seo-score-track" style="width:80px;display:inline-block;vertical-align:middle;"><div class="seo-score-bar" style="width:<?= (int) $m['seo_score'] ?>%;background:<?= $m['seo_score'] >= 80 ? '#22C55E' : ($m['seo_score'] >= 50 ? '#F59E0B' : '#EF4444') ?>;"></div></div>
                <span style="font-size:12px;color:var(--color-text-muted);"><?= (int) $m['seo_score'] ?></span>
            </td>
            <td><span class="status-pill status-<?= $m['status'] === 'published' ? 'active' : 'pending' ?>"><?= ucfirst($m['status']) ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($m['status'] === 'published'): ?>
                <a href="<?= e(medicine_url($m['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm btn-edit-medicine">Edit</button>
                <button class="btn btn-danger btn-sm btn-delete-medicine">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<script>
window.MEDICINE_RICH_FIELDS = <?= json_encode(array_column(array_map(fn($m) => [
    'id' => $m['id'],
    'content' => $m['content'], 'uses' => $m['uses'], 'dosage' => $m['dosage'],
    'side_effects' => $m['side_effects'], 'precautions' => $m['precautions'],
], $medicines), null, 'id')) ?>;
window.MEDICINE_FAQS = <?= json_encode($medicineFaqsById) ?>;
</script>

<div class="modal-overlay" id="medicine-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:760px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;max-height:88vh;overflow-y:auto;">
            <h3 style="margin-bottom:20px;" id="medicine-modal-title">Add Medicine</h3>
            <form id="medicine-form">
                <?php require __DIR__ . '/../includes/medicine-form-fields.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
