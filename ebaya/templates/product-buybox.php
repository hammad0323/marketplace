<?php
/**
 * Purchase form shared by the product page and quick view.
 * Expects $product, $matrix. Optional $compact (quick view).
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$compact = $compact ?? false;
$hasOptions = !empty($matrix['options']);
$anyAvailable = (bool)array_filter($matrix['variants'], fn($v) => $v['available']);
$wish = in_array((int)$product['id'], wishlist_ids(), true);
$sleeveOpts = array_filter(array_map('trim', explode(',', (string)$product['custom_sleeve_options'])));
$uid = 'bb' . (int)$product['id'] . ($compact ? 'q' : '');
$prices = array_column($matrix['variants'], 'price');
$minP = $prices ? min($prices) : product_price($product);
$maxP = $prices ? max($prices) : product_price($product);
?>
<form class="buybox" data-buybox data-matrix='<?= e(json_encode($matrix, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>' data-track="<?= (int)$product['track_inventory'] ?>" novalidate>
  <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
  <input type="hidden" name="variant_id" value="<?= !$hasOptions && count($matrix['variants']) === 1 ? (int)$matrix['variants'][0]['id'] : '' ?>">

  <div class="bb-price" data-price>
    <?php if (product_on_sale($product)): ?>
      <span class="price-sale"><?= money(product_price($product)) ?></span> <del class="price-old"><?= money($product['regular_price']) ?></del>
      <span class="save-tag">Save <?= round(100 - product_price($product) / max(1, (float)$product['regular_price']) * 100) ?>%</span>
    <?php elseif ($minP != $maxP): ?>
      <span><?= money($minP) ?> – <?= money($maxP) ?></span>
    <?php else: ?>
      <span><?= money($minP) ?></span>
    <?php endif; ?>
  </div>

  <?php foreach ($matrix['options'] as $code => $opt): ?>
    <fieldset class="bb-option" data-option="<?= e($code) ?>">
      <legend>
        <span><?= e($opt['name']) ?>: <strong data-option-label><?= count($opt['values']) === 1 ? e(reset($opt['values'])['value']) : 'Select' ?></strong></span>
        <?php if ($code === 'size' && !$compact): ?><button type="button" class="link-btn" data-bs-toggle="modal" data-bs-target="#sizeGuide"><i class="bi bi-rulers"></i> Size guide</button><?php endif; ?>
      </legend>
      <div class="<?= $code === 'color' ? 'opt-swatches' : 'opt-buttons' ?>">
        <?php foreach ($opt['values'] as $vid => $v): ?>
          <label class="<?= $code === 'color' ? 'opt-swatch' : 'opt-btn' ?>" title="<?= e($v['value']) ?>">
            <input type="radio" name="opt_<?= e($code) ?>" value="<?= (int)$vid ?>" data-label="<?= e($v['value']) ?>" class="visually-hidden"<?= count($opt['values']) === 1 ? ' checked' : '' ?>>
            <?php if ($code === 'color'): ?><span class="swatch lg" style="background:<?= e($v['hex'] ?: '#ccc') ?>"></span><span class="visually-hidden"><?= e($v['value']) ?></span>
            <?php else: ?><span><?= e($v['value']) ?></span><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <div class="bb-stock" data-stock>
    <?php if (!$anyAvailable): ?><span class="text-muted"><i class="bi bi-x-circle"></i> Sold out</span>
    <?php elseif ($product['fulfillment_type'] === 'made_to_order'): ?><span><i class="bi bi-scissors"></i> Made to order — production time <?= (int)$product['production_lead_days'] ?> days</span>
    <?php elseif ($hasOptions): ?><span class="text-muted">Select options to check availability</span>
    <?php else: ?><span><i class="bi bi-check2-circle"></i> In stock, ready to ship</span><?php endif; ?>
  </div>

  <?php if ($product['allow_customization'] && !$compact && ($product['custom_length_enabled'] || ($product['custom_sleeve_enabled'] && $sleeveOpts) || $product['custom_notes_enabled'])): ?>
    <details class="bb-custom">
      <summary><i class="bi bi-scissors"></i> Personalise this piece<?= (float)$product['customization_fee'] > 0 ? ' (+' . money($product['customization_fee']) . ')' : '' ?></summary>
      <div class="bb-custom-body">
        <?php if ($product['custom_length_enabled']): ?>
          <label class="form-label" for="<?= $uid ?>len">Custom length (inches, <?= (int)($product['custom_length_min'] ?: 48) ?>–<?= (int)($product['custom_length_max'] ?: 64) ?>)</label>
          <input class="form-control" type="number" id="<?= $uid ?>len" name="custom_length" min="<?= (int)($product['custom_length_min'] ?: 48) ?>" max="<?= (int)($product['custom_length_max'] ?: 64) ?>" placeholder="e.g. 56">
        <?php endif; ?>
        <?php if ($product['custom_sleeve_enabled'] && $sleeveOpts): ?>
          <label class="form-label mt-3" for="<?= $uid ?>slv">Sleeve preference</label>
          <select class="form-select" id="<?= $uid ?>slv" name="custom_sleeve"><option value="">As designed</option>
            <?php foreach ($sleeveOpts as $so): ?><option value="<?= e($so) ?>"><?= e($so) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <?php if ($product['custom_notes_enabled']): ?>
          <label class="form-label mt-3" for="<?= $uid ?>notes">Additional instructions</label>
          <textarea class="form-control" id="<?= $uid ?>notes" name="custom_notes" rows="3" maxlength="1000" placeholder="Anything we should know?"></textarea>
        <?php endif; ?>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> <?= e($product['custom_terms'] ?: setting('custom_order_terms')) ?> Production time: <?= (int)$product['production_lead_days'] ?> days.</p>
      </div>
    </details>
  <?php endif; ?>

  <div class="bb-actions">
    <div class="qty-box">
      <button type="button" data-qty="-1" aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
      <input type="number" name="qty" value="1" min="1" max="<?= CART_MAX_QTY ?>" aria-label="Quantity" inputmode="numeric">
      <button type="button" data-qty="1" aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
    </div>
    <button type="submit" class="btn btn-eb btn-primary-eb flex-grow-1" data-add-btn<?= $anyAvailable ? '' : ' disabled' ?>><span>Add to bag</span></button>
    <button type="button" class="btn btn-eb btn-outline-eb bb-wish<?= $wish ? ' is-active' : '' ?>" data-wishlist="<?= (int)$product['id'] ?>" aria-pressed="<?= $wish ? 'true' : 'false' ?>" aria-label="Wishlist"><i class="bi bi-heart<?= $wish ? '-fill' : '' ?>"></i></button>
  </div>
  <button type="button" class="btn btn-eb btn-dark-eb w-100 mt-2" data-buy-now<?= $anyAvailable ? '' : ' disabled' ?>>Buy it now</button>
  <?php if ($compact): ?><a class="d-block text-center mt-3 small" href="<?= e(product_url($product)) ?>">View full details</a><?php endif; ?>
</form>
