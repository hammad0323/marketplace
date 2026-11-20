<?php
/** Print sheet for a design: previews, artwork files and exact print sizes/positions. Included by design.php and view.php. */
if (!defined('UC_ROOT')) {
    http_response_code(404);
    exit;
}

function admin_design_sheet(array $design): void
{
    $spec = json_decode((string) $design['spec'], true) ?: ['type' => 'tshirt', 'elements' => []];
    $type = design_types()[$spec['type']] ?? design_types()['tshirt'];
    $product = db_row('SELECT name FROM products WHERE id = ?', [(int) $design['product_id']]);
    $price = design_price($spec['elements']);
    ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="row g-2">
                <?php foreach (['preview_front' => 'Front', 'preview_back' => 'Back'] as $k => $label): ?>
                    <div class="col-6 text-center"><?php if ($design[$k]): ?><a href="<?= e(img_url($design[$k])) ?>" target="_blank"><img src="<?= e(img_url($design[$k])) ?>" alt="<?= $label ?>" class="img-fluid rounded" style="background:#f1f0ec"></a><?php endif; ?><div class="small text-muted mt-1"><?= $label ?></div></div>
                <?php endforeach; ?>
            </div>
            <table class="table table-sm mt-3 small"><tr><td class="text-muted">Garment</td><td><?= e(($product['name'] ?? '') . ' — ' . $type['label']) ?></td></tr>
                <tr><td class="text-muted">Colour</td><td><?= e($design['color']) ?></td></tr>
                <tr><td class="text-muted">Print area</td><td>Front <?= $type['area_cm']['front'][0] ?> × <?= $type['area_cm']['front'][1] ?> cm · Back <?= $type['area_cm']['back'][0] ?> × <?= $type['area_cm']['back'][1] ?> cm</td></tr>
                <tr><td class="text-muted">Auto print price</td><td><?= e(money($price['total'])) ?> per piece <span class="text-muted">(current price list)</span></td></tr></table>
        </div>
        <div class="col-lg-7">
            <div class="table-responsive"><table class="table table-sm a-table align-middle">
                <thead><tr><th>Side</th><th style="min-width:230px">Artwork</th><th>Print size</th><th>Position <small class="text-muted">from top-left of print area</small></th><th>Rotation</th></tr></thead><tbody>
                <?php foreach ($spec['elements'] as $el):
                    $cm = $type['area_cm'][$el['side']];
                    $w = $el['w'] * $cm[0]; $h = $el['h'] * $cm[1];
                    $left = ($el['cx'] - $el['w'] / 2) * $cm[0]; $top = ($el['cy'] - $el['h'] / 2) * $cm[1]; ?>
                    <tr><td><?= $el['side'] === 'back' ? 'Back' : 'Front' ?></td>
                        <td><?php if ($el['kind'] === 'text'): ?>
                                <strong style="color:<?= e($el['color']) ?>;<?= strtoupper($el['color']) === '#FFFFFF' ? 'background:#333;padding:0 4px' : '' ?>"><?= e($el['text']) ?></strong><div class="small text-muted"><?= e($el['font']) ?> · <?= e($el['color']) ?></div>
                            <?php else: $asset = db_row('SELECT * FROM design_assets WHERE token = ?', [$el['asset']]); $orig = $asset && $asset['source_id'] ? db_row('SELECT * FROM design_assets WHERE id = ?', [(int) $asset['source_id']]) : null; ?>
                                <div class="d-flex gap-2 align-items-center"><span class="a-thumb a-thumb-sm" style="background:repeating-conic-gradient(#eee 0 25%,#fff 0 50%) 0 0/10px 10px"><img src="<?= e(img_url($el['image'])) ?>" alt=""></span>
                                <div class="small"><a href="<?= e(img_url($el['image'])) ?>" download><i class="bi bi-download"></i> Print file<?= (int) $el['bg_removed'] ? ' (background removed)' : '' ?></a>
                                    <?php if ($orig): ?><br><a href="<?= e(img_url($orig['image'])) ?>" download class="text-muted"><i class="bi bi-download"></i> Original upload</a><?php endif; ?>
                                    <?php if ($asset): ?><br><span class="text-muted"><?= (int) $asset['width'] ?> × <?= (int) $asset['height'] ?> px<?= $asset['width'] && $w > 0 && $asset['width'] / ($w / 2.54) < 150 ? ' · <span class="text-danger">low resolution for this size</span>' : '' ?></span><?php endif; ?>
                                    <?= !empty($el['flip']) ? '<br><span class="badge text-bg-warning">mirrored</span>' : '' ?></div></div>
                            <?php endif; ?></td>
                        <td class="text-nowrap"><?= number_format($w, 1) ?> × <?= number_format($h, 1) ?> cm<div class="small text-muted"><?= $el['kind'] === 'image' ? ucfirst(design_size_class($el)) : 'Text' ?></div></td>
                        <td class="text-nowrap small"><?= number_format($left, 1) ?> cm across, <?= number_format($top, 1) ?> cm down</td>
                        <td><?= (float) $el['rot'] ? e(round($el['rot'])) . '°' : '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <p class="small text-muted mb-0">Sizes are measured on the print area shown above. Parts of an image outside the print area are not printed (the previews show exactly what is printed). Low-resolution warnings are based on 150 dpi.</p>
        </div>
    </div>
    <?php
}
