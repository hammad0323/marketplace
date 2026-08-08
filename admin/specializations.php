<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$specs = mysqli_query(db(), "
    SELECT s.*, (SELECT COUNT(*) FROM doctor_specializations ds WHERE ds.specialization_id = s.id) AS doctor_count
    FROM specializations s ORDER BY s.sort_order, s.name
");

$pageTitle = 'Specializations';
$heading = 'Specializations';
$extraScripts = '<script src="/assets/js/admin-specializations.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <button class="btn btn-primary" id="add-spec-btn"><i class="ri-add-line"></i> Add Specialization</button>
</div>

<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Icon</th><th>Name</th><th>Description</th><th>Doctors</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="spec-tbody">
        <?php while ($s = mysqli_fetch_assoc($specs)): ?>
        <tr data-spec-id="<?= (int)$s['id'] ?>" data-name="<?= e($s['name']) ?>" data-icon="<?= e($s['icon']) ?>" data-description="<?= e($s['description']) ?>">
            <td><i class="<?= e($s['icon']) ?>" style="font-size:20px;color:var(--color-primary);"></i></td>
            <td><?= e($s['name']) ?></td>
            <td style="max-width:280px;"><?= e($s['description']) ?></td>
            <td><?= (int)$s['doctor_count'] ?></td>
            <td><span class="status-pill status-<?= $s['is_active'] ? 'active' : 'suspended' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td style="white-space:nowrap;">
                <button class="btn btn-outline btn-sm btn-edit-spec">Edit</button>
                <button class="btn btn-ghost btn-sm btn-toggle-spec"><?= $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                <button class="btn btn-danger btn-sm btn-delete-spec">Delete</button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>

<div class="modal-overlay" id="spec-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:460px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;">
            <h3 style="margin-bottom:18px;" id="spec-modal-title">Add Specialization</h3>
            <form id="spec-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="spec-id" value="0">
                <div class="form-group" data-field="name">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" id="spec-name" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon (Remix Icon class)</label>
                    <input type="text" class="form-control" name="icon" id="spec-icon" placeholder="ri-stethoscope-line">
                    <p class="form-hint">Browse icons at remixicon.com</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" id="spec-description" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Save</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
