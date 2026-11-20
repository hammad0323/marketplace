<?php
/** Design Studio: design your own T-shirt / hoodie with images and text, preview in 3D, order or request a quote. */
if (!design_enabled()) {
    redirect('custom-size');
}
$blanks = design_blanks();
if (!$blanks) {
    flash('info', 'The Design Studio will open soon.');
    redirect('custom-size');
}
$selected = 0;
if (!empty($ROUTE['params']['slug'])) {
    foreach ($blanks as $i => $b) {
        if ($b['slug'] === $ROUTE['params']['slug']) {
            $selected = $i;
        }
    }
}
$mode = design_mode();
$showPrice = $mode === 'auto' || setting_bool('design_show_estimate', true);
$customer = current_customer();
seo_set([
    'title' => 'Design Your Own T-Shirt & Hoodie',
    'description' => 'Upload your photos and logos, add text, place them on the front and back of a T-shirt, oversized tee or hoodie, preview in 3D and order — printed in Pakistan.',
    'breadcrumbs' => [['Home', url()], ['Custom Size', url('custom-size')], ['Design Studio', url('design-studio')]],
]);
$fontsCss = 'https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Courier+Prime:wght@700&family=Montserrat:wght@800&family=Pacifico&family=Permanent+Marker&family=Playfair+Display:wght@700&display=swap';
$cfg = [
    'blanks' => $blanks, 'selected' => $selected, 'fromSlug' => !empty($ROUTE['params']['slug']), 'mode' => $mode, 'showPrice' => $showPrice,
    'pricing' => $showPrice ? design_pricing() : null, 'maxElements' => design_max_elements(), 'aiBg' => design_ai_bg_enabled(),
    'fonts' => array_map(fn($f, $n) => ['name' => $n, 'css' => $f[0], 'weight' => $f[1]], UC_DESIGN_FONTS, array_keys(UC_DESIGN_FONTS)),
    'productionDays' => design_production_days(), 'maxUploadMb' => defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 5,
    'three' => asset('vendor/three/three.min.js'), 'endpoint' => url('ajax/designer.php'),
];
$bodyClass = 'page-design-studio';
$pageScripts = '<script>window.UC_DESIGNER = ' . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script><script src="' . e(asset('js/designer.js')) . '"></script>';
require UC_ROOT . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e($fontsCss) ?>">
<section class="uc-container uc-page-pad uc-dz" data-designer>
    <?= breadcrumbs_html([['Home', url()], ['Custom Size', url('custom-size')], ['Design Studio', null]]) ?>
    <div class="uc-dz-head">
        <div>
            <p class="uc-eyebrow">Design Studio</p>
            <h1 class="uc-page-title">Design your own T-shirt &amp; hoodie</h1>
            <p class="uc-page-intro">Choose a regular tee, oversized tee or hoodie, pick a colour, then add your photos, logos and text anywhere on the front and back. Remove image backgrounds in one tap and check it in 3D before you order.</p>
        </div>
    </div>

    <div class="uc-dz-grid">
        <!-- Stage -->
        <div class="uc-dz-stage-col">
            <div class="uc-dz-stage-card">
                <div class="uc-dz-toolbar">
                    <div class="uc-dz-sides" role="group" aria-label="Side">
                        <button type="button" class="is-active" data-side="front">Front <span data-side-count="front"></span></button>
                        <button type="button" data-side="back">Back <span data-side-count="back"></span></button>
                    </div>
                    <button type="button" class="uc-btn uc-btn--sm" data-open-3d><i class="bi bi-badge-3d"></i> 3D preview</button>
                </div>
                <div class="uc-dz-stage" data-stage tabindex="0" aria-label="Design area. Use arrow keys to move the selected item, Delete to remove it.">
                    <canvas data-garment width="600" height="700"></canvas>
                    <div class="uc-dz-area" data-area>
                        <div class="uc-dz-area-content" data-content></div>
                        <div class="uc-dz-area-ui" data-ui></div>
                        <span class="uc-dz-guide is-v" data-guide-v></span><span class="uc-dz-guide is-h" data-guide-h></span>
                    </div>
                    <div class="uc-dz-empty" data-empty><i class="bi bi-image"></i><strong>Drop an image here</strong><span>or use “Upload image” — JPG, PNG or WebP</span></div>
                    <div class="uc-dz-busy" data-busy hidden><span class="spinner-border spinner-border-sm"></span> <span data-busy-text>Working…</span></div>
                </div>
                <p class="uc-dz-hint"><i class="bi bi-arrows-move"></i> Drag to move · corner to resize · <i class="bi bi-arrow-clockwise"></i> to rotate · dashed line = print area</p>
            </div>
        </div>

        <!-- Panel -->
        <div class="uc-dz-panel">
            <div class="uc-dz-block">
                <h2 class="uc-dz-step"><span>1</span> Choose your garment</h2>
                <div class="uc-dz-blanks" data-blanks>
                    <?php foreach ($blanks as $i => $b): ?>
                        <button type="button" class="uc-dz-blank<?= $i === $selected ? ' is-active' : '' ?>" data-blank="<?= $i ?>" aria-pressed="<?= $i === $selected ? 'true' : 'false' ?>">
                            <canvas width="120" height="140" data-blank-icon="<?= e($b['type']) ?>" aria-hidden="true"></canvas>
                            <strong><?= e($b['label']) ?></strong><small>from <?= e(money($b['price'])) ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="uc-dz-block">
                <h2 class="uc-dz-step"><span>2</span> Colour: <strong data-color-name></strong></h2>
                <div class="uc-dz-colors" data-colors></div>
            </div>

            <div class="uc-dz-block">
                <h2 class="uc-dz-step"><span>3</span> Add your design <small data-side-label>on the front</small></h2>
                <div class="uc-dz-add">
                    <label class="uc-btn uc-btn--sm"><i class="bi bi-upload"></i> Upload image<input type="file" accept="image/png,image/jpeg,image/webp" data-upload hidden multiple></label>
                    <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-add-text><i class="bi bi-fonts"></i> Add text</button>
                </div>
                <ul class="uc-dz-layers" data-layers></ul>
                <div class="uc-dz-props" data-props hidden></div>
            </div>

            <div class="uc-dz-block">
                <h2 class="uc-dz-step"><span>4</span> Sizes &amp; quantity</h2>
                <div class="uc-dz-sizes" data-sizes></div>
                <p class="small text-muted mb-0"><a href="<?= e(url('size-guide')) ?>" target="_blank">Size guide</a> · Order several sizes of the same design in one go.</p>
            </div>

            <div class="uc-dz-block uc-dz-summary" data-summary>
                <h2 class="uc-dz-step"><span>5</span> <?= $mode === 'auto' ? 'Price &amp; order' : 'Get your price' ?></h2>
                <?php if ($showPrice): ?><dl class="uc-totals" data-price-lines></dl><?php endif; ?>
                <?php if ($mode === 'quote'): ?>
                    <p class="small text-muted"><i class="bi bi-info-circle"></i> <?= $showPrice ? 'This is an estimate. ' : '' ?>Our team checks every design and confirms the final price — usually within a few hours. You then order with one tap.</p>
                    <form class="uc-dz-quote" data-quote-form novalidate>
                        <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="row g-2">
                            <div class="col-sm-6"><label class="uc-field"><span>Name</span><input class="form-control" name="name" required value="<?= e($customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : '') ?>"></label></div>
                            <div class="col-sm-6"><label class="uc-field"><span>Phone / WhatsApp</span><input class="form-control" name="phone" type="tel" required value="<?= e($customer['phone'] ?? '') ?>"></label></div>
                            <div class="col-sm-6"><label class="uc-field"><span>Email (optional)</span><input class="form-control" name="email" type="email" value="<?= e($customer['email'] ?? '') ?>"></label></div>
                            <div class="col-sm-6"><label class="uc-field"><span>City</span><input class="form-control" name="city" required></label></div>
                            <div class="col-12"><label class="uc-field"><span>Notes (optional)</span><textarea class="form-control" name="notes" rows="2" placeholder="e.g. needed by Friday, team names on the back…"></textarea></label></div>
                        </div>
                        <button type="submit" class="uc-btn uc-btn--block mt-3" data-submit>Request price confirmation</button>
                    </form>
                <?php else: ?>
                    <p class="small text-muted"><i class="bi bi-printer"></i> Printed for you in <?= design_production_days() ?> working days, then shipped.</p>
                    <button type="button" class="uc-btn uc-btn--block" data-add-bag data-submit>Add to bag</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="uc-dz-mobilebar" data-mobilebar><span><small>Total</small><strong data-mobile-total>—</strong></span><button type="button" class="uc-btn uc-btn--sm" data-jump-summary><?= $mode === 'auto' ? 'Order' : 'Get price' ?></button></div>
</section>

<div class="uc-dz-3d" data-3d hidden role="dialog" aria-modal="true" aria-label="3D preview">
    <div class="uc-dz-3d-inner">
        <button type="button" class="uc-dz-3d-close" data-close-3d aria-label="Close 3D preview"><i class="bi bi-x-lg"></i></button>
        <div class="uc-dz-3d-view" data-3d-view></div>
        <div class="uc-dz-3d-bar">
            <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-3d-turn="0">Front</button>
            <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-3d-turn="180">Back</button>
            <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-3d-spin><i class="bi bi-arrow-repeat"></i> Spin</button>
        </div>
        <p class="uc-dz-3d-hint"><i class="bi bi-hand-index"></i> Drag to turn your design</p>
    </div>
</div>
<?php require UC_ROOT . '/includes/footer.php';
