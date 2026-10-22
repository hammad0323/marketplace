<?php
/**
 * Variant selection + add to bag form, shared by the product page and quick view.
 * Expects $product, $variants, $groups (variant_option_groups), $context ('page'|'quickview').
 */
$pi = price_info($product);
$colorHex = [];
foreach (db_all('SELECT name, hex_code FROM colors') as $c) {
    $colorHex[$c['name']] = $c['hex_code'];
}
$variantData = array_map(function ($v) use ($product) {
    $pr = price_info($product, $v);
    $avail = available_qty($product, $v);
    return [
        'id' => (int) $v['id'], 'sku' => $v['sku'], 'options' => $v['options'],
        'price' => money($pr['final']), 'regular' => $pr['on_sale'] ? money($pr['regular']) : null,
        'available' => $avail === PHP_INT_MAX ? 999 : $avail,
        'low' => $avail !== PHP_INT_MAX && $avail > 0 && $avail <= (int) ($v['low_stock_threshold'] ?? $product['low_stock_threshold']),
    ];
}, $variants);
$simpleAvail = (int) $product['has_variants'] ? null : available_qty($product);
$inStock = (int) $product['has_variants'] ? (bool) array_filter($variantData, fn($v) => $v['available'] > 0) : $simpleAvail > 0;
$customFields = (int) $product['is_customizable'] ? array_filter(explode(',', (string) $product['custom_fields'])) : [];
$colorImages = [];
foreach (db_all('SELECT color_name, image FROM product_images WHERE product_id = ? AND color_name IS NOT NULL ORDER BY sort_order', [(int) $product['id']]) as $ci) {
    $colorImages[$ci['color_name']] = $colorImages[$ci['color_name']] ?? img_sized($ci['image'], 1200);
}
$uid = 'pb' . (int) $product['id'] . ($context === 'quickview' ? 'q' : '');
?>
<form class="uc-buy" data-buy-form data-variants="<?= e(json_encode($variantData)) ?>" data-color-images="<?= e(json_encode($colorImages)) ?>" novalidate>
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="variant_id" value="" data-variant-input>

    <div class="uc-buy-price" data-price-box>
        <?php if ($pi['on_sale']): ?>
            <span class="uc-price-now is-sale" data-price><?= e(money($pi['sale'])) ?></span>
            <s class="uc-price-was" data-regular><?= e(money($pi['regular'])) ?></s>
            <span class="uc-price-off">Save <?= (int) round(100 - $pi['sale'] / max(1, $pi['regular']) * 100) ?>%</span>
        <?php else: ?>
            <span class="uc-price-now" data-price><?= e(money($pi['final'])) ?></span>
            <s class="uc-price-was" data-regular hidden></s>
        <?php endif; ?>
    </div>
    <p class="uc-tax-note">Price includes applicable taxes. Delivery calculated at checkout.</p>

    <?php foreach ($groups as $name => $values): $isColor = $name === 'Color'; ?>
        <fieldset class="uc-opt" data-option-group="<?= e($name) ?>">
            <legend>
                <span><?= e($name === 'Color' ? 'Colour' : $name) ?>: <strong data-option-selected></strong></span>
                <?php if (!$isColor && $context === 'page'): ?>
                    <button type="button" class="uc-link-btn" data-bs-toggle="modal" data-bs-target="#ucSizeGuide"><i class="bi bi-rulers"></i> Size guide</button>
                <?php endif; ?>
            </legend>
            <div class="uc-opt-values<?= $isColor ? ' is-color' : '' ?>">
                <?php foreach ($values as $val): $id = $uid . md5($name . $val); ?>
                    <input type="radio" class="btn-check" name="opt[<?= e($name) ?>]" id="<?= e($id) ?>" value="<?= e($val) ?>" <?= count($values) === 1 ? 'checked' : '' ?> data-option="<?= e($name) ?>">
                    <?php if ($isColor): ?>
                        <label class="uc-opt-color" for="<?= e($id) ?>" title="<?= e($val) ?>"><span class="uc-swatch-dot" style="--sw:<?= e(safe_color($colorHex[$val] ?? '#cccccc', '#cccccc')) ?>"></span><span class="visually-hidden"><?= e($val) ?></span></label>
                    <?php else: ?>
                        <label class="uc-opt-btn" for="<?= e($id) ?>"><?= e($val) ?></label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($customFields): ?>
        <div class="uc-custom">
            <button type="button" class="uc-custom-toggle" data-accordion aria-expanded="false">
                <span><i class="bi bi-scissors"></i> Personalise / made-to-measure <small>(+<?= e(money($product['customization_fee'])) ?>)</small></span><i class="bi bi-plus-lg"></i>
            </button>
            <div class="uc-custom-body">
                <?php if ((int) $product['made_to_order'] || (int) $product['lead_time_days']): ?>
                    <p class="small text-muted">Customised items are made to order — allow an extra <?= max(1, (int) $product['lead_time_days'] ?: 7) ?> days for production. Customised items cannot be returned for change of mind.</p>
                <?php endif; ?>
                <?php if (in_array('measurements', $customFields, true)): ?>
                    <label class="uc-field"><span>Your measurements (inches)</span>
                        <textarea name="custom_measurements" rows="3" maxlength="600" placeholder="e.g. Chest 40, Waist 34, Shoulder 18, Sleeve 25, Length 30"></textarea></label>
                <?php endif; ?>
                <?php if (in_array('color', $customFields, true)): ?>
                    <label class="uc-field"><span>Custom colour</span><input type="text" name="custom_color" maxlength="80" placeholder="Describe the colour you would like"></label>
                <?php endif; ?>
                <?php if (in_array('personalization', $customFields, true)): ?>
                    <label class="uc-field"><span>Personalisation (initials / name)</span><input type="text" name="personalization" maxlength="40" placeholder="e.g. A.K."></label>
                <?php endif; ?>
                <?php if (in_array('instructions', $customFields, true)): ?>
                    <label class="uc-field"><span>Special instructions</span><textarea name="custom_instructions" rows="2" maxlength="1000"></textarea></label>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <p class="uc-stock" data-stock-msg>
        <?php if (!(int) $product['has_variants']): ?>
            <?php if (!$inStock): ?><span class="is-out"><i class="bi bi-x-circle"></i> Out of stock</span>
            <?php elseif ((int) $product['made_to_order']): ?><span class="is-in"><i class="bi bi-hourglass-split"></i> Made to order — ships in <?= (int) $product['lead_time_days'] ?>+ days</span>
            <?php elseif ($simpleAvail !== PHP_INT_MAX && $simpleAvail <= (int) $product['low_stock_threshold']): ?><span class="is-low"><i class="bi bi-exclamation-circle"></i> Only <?= $simpleAvail ?> left</span>
            <?php else: ?><span class="is-in"><i class="bi bi-check2-circle"></i> In stock<?= (int) $product['ready_to_ship'] ? ' — ready to ship' : '' ?></span><?php endif; ?>
        <?php elseif (!$inStock): ?>
            <span class="is-out"><i class="bi bi-x-circle"></i> Out of stock</span>
        <?php else: ?>
            <span class="text-muted">Select <?= e(strtolower(implode(' & ', array_map(fn($n) => $n === 'Color' ? 'colour' : $n, array_keys($groups))))) ?></span>
        <?php endif; ?>
    </p>

    <div class="uc-buy-row">
        <div class="uc-qty" data-qty>
            <button type="button" data-qty-minus aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
            <input type="number" name="qty" value="1" min="1" max="<?= UC_CART_MAX_QTY ?>" inputmode="numeric" aria-label="Quantity">
            <button type="button" data-qty-plus aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
        </div>
        <button type="submit" class="uc-btn uc-btn--block" data-add-btn<?= $inStock ? '' : ' disabled' ?>><span><?= $inStock ? 'Add to Bag' : 'Sold Out' ?></span></button>
        <button type="button" class="uc-icon-btn uc-icon-btn--box<?= in_array((int) $product['id'], wishlist_product_ids(), true) ? ' is-active' : '' ?>" data-wishlist="<?= (int) $product['id'] ?>" aria-label="Add to wishlist"><i class="bi bi-heart"></i><i class="bi bi-heart-fill"></i></button>
    </div>
    <?php if ($inStock): ?>
        <button type="button" class="uc-btn uc-btn--outline uc-btn--block" data-buy-now>Buy It Now</button>
    <?php endif; ?>
    <?php if ($context === 'quickview'): ?>
        <a class="uc-link-arrow mt-3" href="<?= e(url('product/' . $product['slug'])) ?>">View full details <i class="bi bi-arrow-right"></i></a>
    <?php endif; ?>
</form>
