<?php
/** Custom Size Studio for one product: /custom-size/{product-slug} */
$product = product_by_slug($ROUTE['params']['slug']);
$garment = $product && $product['custom_garment_id'] ? studio_garment((int) $product['custom_garment_id']) : null;
if (!$product || !$garment) {
    route_not_found($ROUTE['path']);
}
$def = $garment['def'];
$options = studio_options((int) $garment['id']);
$groups = array_filter($def['groups'], fn($g) => !empty($options[$g]), ARRAY_FILTER_USE_KEY);
$chart = studio_chart((int) $garment['id']);
$fitTable = studio_fit_table((int) $garment['id']);
$colors = studio_colors($product);
$profiles = studio_saved_profiles($garment['code']);
$pi = price_info($product);
$img = db_val('SELECT image FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order LIMIT 1', [(int) $product['id']]);

$crumbs = [['Home', url()], ['Custom Size', url('custom-size')], [$product['name'], null]];
seo_set(['title' => 'Customise your size — ' . $product['name'], 'description' => 'Get ' . $product['name'] . ' made to your exact measurements at the ready-made price.', 'canonical' => url('custom-size/' . $product['slug']), 'breadcrumbs' => $crumbs]);

$studioCfg = [
    'productId' => (int) $product['id'],
    'garment' => $garment['code'], 'gender' => $def['gender'], 'base' => $def['base'],
    'measurements' => $def['measurements'], 'groups' => $groups,
    'options' => array_map(fn($opts) => array_map(fn($o) => ['value_key' => $o['value_key'], 'label' => $o['label'], 'extra_price' => (float) $o['extra_price'], 'is_default' => (int) $o['is_default']], $opts), array_intersect_key($options, $groups)),
    'colors' => array_map(fn($c) => ['name' => $c['name'], 'hex' => safe_color($c['hex'], '#cccccc')], $colors),
    'chart' => $chart, 'fee' => (float) $garment['fee'], 'price' => $pi['final'],
];
$bodyClass = 'page-studio';
$pageScripts = '<script>window.UC_STUDIO = ' . json_encode($studioCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script><script src="' . e(asset('js/studio.js')) . '"></script>';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-studio" data-studio>
    <?= breadcrumbs_html($crumbs) ?>
    <div class="uc-studio-head">
        <div>
            <p class="uc-eyebrow">Custom Size Studio</p>
            <h1 class="uc-page-title"><?= e($product['name']) ?></h1>
            <p class="uc-page-intro">Ready-made design, made to <strong>your</strong> measurements. Choose your colour and style, enter your size, and our tailors make it for you<?= (float) $garment['fee'] > 0 ? '' : ' — at the same ready-made price' ?>.</p>
        </div>
        <a class="uc-link-arrow" href="<?= e(url('measurement-guide') . '#' . $garment['code']) ?>" target="_blank"><i class="bi bi-rulers"></i> How to measure</a>
    </div>

    <div class="uc-studio-grid">
        <!-- Mannequin -->
        <div class="uc-studio-stage-col">
            <div class="uc-studio-stage-card">
                <div class="uc-stage-toolbar" role="group" aria-label="View">
                    <button type="button" class="is-active" data-view="front">Front</button>
                    <button type="button" data-view="back">Back</button>
                    <button type="button" data-view="spin"><i class="bi bi-arrow-repeat"></i> 360°</button>
                </div>
                <div class="uc-stage" data-stage aria-live="polite"></div>
                <p class="uc-stage-hint"><i class="bi bi-hand-index"></i> Drag to turn · tap a measurement to see where to measure</p>
            </div>
            <div class="uc-studio-price">
                <?php if ($img): ?><img src="<?= e(img_sized($img, 200)) ?>" alt="" width="60" height="80"><?php endif; ?>
                <div>
                    <span class="uc-price-now" data-total><?= e(money($pi['final'])) ?></span>
                    <?php if ($pi['on_sale']): ?><s class="uc-price-was"><?= e(money($pi['regular'])) ?></s><?php endif; ?>
                    <small data-extra-note>Ready-made price — no extra charge for custom size</small>
                    <small><i class="bi bi-scissors"></i> Stitched in <?= (int) $garment['production_days_min'] ?>–<?= (int) $garment['production_days_max'] ?> working days, then shipped</small>
                </div>
            </div>
        </div>

        <!-- Steps -->
        <form class="uc-studio-steps" data-studio-form novalidate>
            <input type="hidden" name="base_size" value="">

            <div class="uc-sstep is-open" data-step="1">
                <button type="button" class="uc-sstep-head" data-step-toggle><span>1</span> Start with your usual size</button>
                <div class="uc-sstep-body">
                    <p class="small text-muted">Pick the ready-made size you normally wear — we fill in standard measurements that you can fine-tune in step 3.</p>
                    <div class="uc-size-row">
                        <?php foreach (array_keys($chart) as $sz): ?><button type="button" class="uc-opt-btn" data-size-btn="<?= e($sz) ?>"><?= e($sz) ?></button><?php endforeach; ?>
                    </div>
                    <details class="uc-finder">
                        <summary><i class="bi bi-magic"></i> Not sure? Find my size from height &amp; weight</summary>
                        <div class="uc-finder-body" data-size-finder-wrap>
                            <p class="small text-muted mb-0">Loading size finder…</p>
                        </div>
                    </details>
                    <?php if ($profiles): ?>
                        <label class="uc-field mt-3"><span>Use my saved measurements</span>
                            <select class="form-select" data-profile><option value="">Choose…</option>
                                <?php foreach ($profiles as $pr): ?><option data-m="<?= e(json_encode($pr['data'])) ?>"><?= e($pr['profile_name']) ?> (<?= e(format_date($pr['updated_at'] ?? $pr['created_at'])) ?>)</option><?php endforeach; ?>
                            </select></label>
                    <?php elseif (!current_customer()): ?>
                        <p class="small mt-3 mb-0"><a href="<?= e(url('account/login')) ?>">Sign in</a> to save your measurements for next time.</p>
                    <?php endif; ?>
                    <details class="uc-finder">
                        <summary><i class="bi bi-table"></i> View the size chart</summary>
                        <?php partial('components/size-chart', ['def' => $def, 'chart' => $chart, 'fitTable' => $fitTable]); ?>
                    </details>
                    <button type="button" class="uc-btn uc-btn--sm mt-3" data-step-go="2">Next: colour &amp; style</button>
                </div>
            </div>

            <div class="uc-sstep" data-step="2">
                <button type="button" class="uc-sstep-head" data-step-toggle><span>2</span> Colour &amp; style</button>
                <div class="uc-sstep-body">
                    <div class="uc-opt">
                        <p class="uc-opt-label">Colour: <strong data-color-name></strong></p>
                        <div class="uc-opt-values is-color">
                            <?php foreach ($colors as $i => $c): ?>
                                <button type="button" class="uc-opt-color<?= $i === 0 ? ' is-active' : '' ?>" data-color="<?= e($c['name']) ?>" data-hex="<?= e(safe_color($c['hex'], '#cccccc')) ?>" title="<?= e($c['name']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
                                    <span class="uc-swatch-dot" style="--sw:<?= e(safe_color($c['hex'], '#cccccc')) ?>"></span><span class="visually-hidden"><?= e($c['name']) ?></span></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php foreach ($groups as $g => $label): $def0 = array_values(array_filter($options[$g], fn($o) => (int) $o['is_default']))[0] ?? $options[$g][0]; ?>
                        <div class="uc-opt">
                            <p class="uc-opt-label"><?= e($label) ?></p>
                            <div class="uc-style-grid">
                                <?php foreach ($options[$g] as $o): $on = $o['value_key'] === $def0['value_key']; ?>
                                    <button type="button" class="uc-style-btn<?= $on ? ' is-active' : '' ?>" data-style-group="<?= e($g) ?>" data-style="<?= e($o['value_key']) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>">
                                        <span><?= e($o['label']) ?></span>
                                        <?php if ((float) $o['extra_price'] > 0): ?><small>+<?= e(money($o['extra_price'])) ?></small><?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="button" class="uc-btn uc-btn--sm" data-step-go="3">Next: measurements</button>
                </div>
            </div>

            <div class="uc-sstep" data-step="3">
                <button type="button" class="uc-sstep-head" data-step-toggle><span>3</span> Your measurements <small>(inches)</small></button>
                <div class="uc-sstep-body">
                    <p class="small text-muted">Enter <strong>body</strong> measurements — our tailors add the right ease for a comfortable fit. Tap <i class="bi bi-question-circle"></i> for how to measure; the mannequin shows where. (1 inch = 2.54 cm)</p>
                    <div class="uc-mgrid">
                        <?php foreach ($def['measurements'] as $k => $f): ?>
                            <div class="uc-mfield">
                                <label for="m_<?= e($k) ?>"><?= e($f['label']) ?> <button type="button" class="uc-help-btn" data-m-help aria-label="How to measure <?= e($f['label']) ?>"><i class="bi bi-question-circle"></i></button></label>
                                <div class="uc-mstepper">
                                    <button type="button" data-m-step="-0.5" aria-label="Decrease">−</button>
                                    <input id="m_<?= e($k) ?>" type="number" inputmode="decimal" step="0.5" min="<?= e($f['min']) ?>" max="<?= e($f['max']) ?>" data-m="<?= e($k) ?>" required>
                                    <button type="button" data-m-step="0.5" aria-label="Increase">+</button>
                                </div>
                                <p class="uc-mhelp"><?= e($f['help']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="uc-measure-tips"><i class="bi bi-lightbulb"></i> <span>Tip: use a soft measuring tape over light clothing, keep it snug but not tight, and ask someone to help. Or measure a qameez/shirt that fits you well, laid flat.</span></div>
                    <button type="button" class="uc-btn uc-btn--sm mt-3" data-step-go="4">Next: review</button>
                </div>
            </div>

            <div class="uc-sstep" data-step="4">
                <button type="button" class="uc-sstep-head" data-step-toggle><span>4</span> Review &amp; add to bag</button>
                <div class="uc-sstep-body">
                    <dl class="uc-review-list" data-review></dl>
                    <label class="uc-field"><span>Notes for the tailor (optional)</span><textarea name="notes" rows="2" maxlength="500" placeholder="e.g. slightly loose fit around the belly, extra inside pocket"></textarea></label>
                    <?php if (current_customer()): ?>
                        <label class="uc-check"><input type="checkbox" name="save_profile" value="1" checked> <span>Save these measurements to my account as</span></label>
                        <input class="form-control form-control-sm mt-1 mb-3" name="profile_name" value="My measurements" maxlength="60" aria-label="Measurement profile name">
                    <?php endif; ?>
                    <div class="uc-buy-row">
                        <div class="uc-qty" data-qty>
                            <button type="button" data-qty-minus aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
                            <input type="number" name="qty" value="1" min="1" max="5" aria-label="Quantity">
                            <button type="button" data-qty-plus aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
                        </div>
                        <button type="submit" class="uc-btn uc-btn--block"><span>Add to Bag — <span data-total><?= e(money($pi['final'])) ?></span></span></button>
                    </div>
                    <p class="small text-muted mb-0"><i class="bi bi-info-circle"></i> Made-to-measure items are stitched for you and can’t be returned for change of mind. If something doesn’t fit as measured, our <a href="<?= e(url('alterations')) ?>">alteration service</a> will adjust it.</p>
                </div>
            </div>
        </form>
    </div>
</section>
<?php
// Size-finder form markup (kept out of the main form to avoid nested forms)
$finder = '<div class="row g-2"><div class="col-4"><label class="uc-field"><span>Height (ft)</span><input type="number" name="ft" min="4" max="7" value="5" required></label></div>'
    . '<div class="col-4"><label class="uc-field"><span>(in)</span><input type="number" name="in" min="0" max="11" value="8" required></label></div>'
    . '<div class="col-4"><label class="uc-field"><span>Weight (kg)</span><input type="number" name="kg" min="30" max="200" value="' . ($def['gender'] === 'women' ? 58 : 72) . '" required></label></div>'
    . '<div class="col-8"><label class="uc-field"><span>Preferred fit</span><select name="fit" class="form-select"><option value="regular">Regular</option><option value="slim">Slim</option><option value="loose">Loose / relaxed</option></select></label></div>'
    . '<div class="col-4 d-flex align-items-end"><button class="uc-btn uc-btn--sm uc-btn--block mb-3" type="submit">Suggest</button></div></div>'
    . '<p class="uc-finder-result" data-finder-result hidden></p>';
$pageScripts .= '<script>(function(){var w=document.querySelector("[data-size-finder-wrap]");if(!w)return;var f=document.createElement("form");f.setAttribute("data-size-finder","");f.innerHTML=' . ejs($finder) . ';w.innerHTML="";w.appendChild(f);})();</script>';
require UC_ROOT . '/includes/footer.php';
