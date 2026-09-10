<?php
/**
 * Shared medicine-info form fields, included inside a <form id="medicine-form">
 * by both admin/medicines.php and doctor/medicines.php. Expects $csrfToken to
 * be set by the including page.
 */
?>
<input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
<input type="hidden" name="id" id="medicine-id" value="0">
<div class="form-group" data-field="name">
    <label class="form-label">Medicine Name</label>
    <input type="text" class="form-control" name="name" id="medicine-name" required>
    <div class="form-error"></div>
</div>
<div class="grid grid-2">
    <div class="form-group">
        <label class="form-label">Generic Name</label>
        <input type="text" class="form-control" name="generic_name" id="medicine-generic-name">
    </div>
    <div class="form-group">
        <label class="form-label">Category</label>
        <input type="text" class="form-control" name="category" id="medicine-category" placeholder="e.g. Antibiotic, Painkiller">
    </div>
</div>
<div class="form-group">
    <label class="form-label">Composition / Active Ingredients</label>
    <input type="text" class="form-control" name="composition" id="medicine-composition">
</div>

<div class="form-group">
    <label class="form-label">Uses</label>
    <div data-rich-editor data-target="#medicine-uses" data-upload-url="/ajax/medicine-image-upload.php"></div>
    <textarea id="medicine-uses" name="uses"></textarea>
</div>
<div class="form-group">
    <label class="form-label">Dosage</label>
    <div data-rich-editor data-target="#medicine-dosage" data-upload-url="/ajax/medicine-image-upload.php"></div>
    <textarea id="medicine-dosage" name="dosage"></textarea>
</div>
<div class="form-group">
    <label class="form-label">Side Effects</label>
    <div data-rich-editor data-target="#medicine-side-effects" data-upload-url="/ajax/medicine-image-upload.php"></div>
    <textarea id="medicine-side-effects" name="side_effects"></textarea>
</div>
<div class="form-group">
    <label class="form-label">Precautions / Warnings</label>
    <div data-rich-editor data-target="#medicine-precautions" data-upload-url="/ajax/medicine-image-upload.php"></div>
    <textarea id="medicine-precautions" name="precautions"></textarea>
</div>
<div class="form-group">
    <label class="form-label">Featured Image (optional)</label>
    <input type="file" class="form-control" name="featured_image" accept=".jpg,.jpeg,.png,.webp">
</div>
<div class="form-group" data-field="content">
    <label class="form-label">Full Description</label>
    <div data-rich-editor data-target="#medicine-content" data-upload-url="/ajax/medicine-image-upload.php"></div>
    <textarea id="medicine-content" name="content"></textarea>
    <div class="form-error"></div>
</div>

<div class="divider-fade"></div>
<h4 style="font-size:14.5px;margin-bottom:4px;">FAQs <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></h4>
<p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:14px;">
    Common questions patients ask about this medicine — shown as an FAQ section on its public page.
</p>
<div id="medicine-faq-list"></div>
<button type="button" class="btn btn-outline btn-sm" id="add-faq-row-btn" style="margin-bottom:20px;"><i class="ri-add-line"></i> Add FAQ</button>

<div class="divider-fade"></div>
<h4 style="font-size:14.5px;margin-bottom:4px;">SEO</h4>
<p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:14px;">
    The focus keyword is normally the medicine's name — set it explicitly if patients are more
    likely to search a brand or generic name. Meta title/description are optional; left blank
    they're auto-generated from the name and uses.
</p>
<div class="form-group">
    <label class="form-label">Focus Keyword</label>
    <input type="text" class="form-control" name="focus_keyword" id="medicine-keyword" placeholder="e.g. the medicine name patients would search">
</div>
<div class="form-group">
    <label class="form-label">Meta Title</label>
    <input type="text" class="form-control" name="meta_title" id="medicine-meta-title" maxlength="200">
</div>
<div class="form-group">
    <label class="form-label">Meta Description</label>
    <textarea class="form-control" name="meta_description" id="medicine-meta-description" rows="2" maxlength="300"></textarea>
</div>
<div class="form-group">
    <label class="form-label">SEO Score</label>
    <div class="seo-score-track"><div class="seo-score-bar" id="medicine-seo-bar"></div></div>
    <span class="seo-score-label" id="medicine-seo-label">0/100</span>
</div>

<div class="form-group">
    <label class="form-label">Status</label>
    <select class="form-control" name="status" id="medicine-status">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
    </select>
</div>
<button type="submit" class="btn btn-primary btn-block">Save Medicine</button>
