<?php
/** How to measure: interactive mannequin per garment + step-by-step tips. */
$garments = studio_garments();
seo_set(['title' => 'How to Measure Yourself — Shalwar Qameez, Shirt & Ladies Suit', 'description' => 'Step-by-step guide to measuring chest, shoulder, sleeve, qameez length, shalwar length and more for a perfect custom fit.',
    'breadcrumbs' => [['Home', url()], ['Measurement Guide', url('measurement-guide')]]]);
$bodyClass = 'page-measure-guide';
$pageScripts = '<script src="' . e(asset('js/studio.js')) . '"></script>';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-listing-hero">
    <div class="uc-container uc-listing-hero-inner">
        <?= breadcrumbs_html([['Home', url()], ['Measurement Guide', null]]) ?>
        <h1 class="uc-page-title">How to Measure Yourself</h1>
        <p class="uc-page-intro">Hover or tap any measurement to see exactly where to place the tape. All measurements are in inches.</p>
    </div>
</section>
<section class="uc-container uc-page-pad">
    <div class="uc-measure-tips mb-4"><i class="bi bi-lightbulb"></i><span><strong>Before you start:</strong> use a soft tailor's tape · wear light, fitted clothing · stand relaxed and straight · keep the tape snug but not tight · ask a friend or family member to help · measure twice.
        <br><strong>Easier option:</strong> lay a qameez or shirt that fits you well on a flat surface and measure it — then tell us in the notes that these are garment measurements.</span></div>
    <ul class="nav uc-tabs" role="tablist">
        <?php foreach ($garments as $i => $g): ?><li class="nav-item"><button class="nav-link<?= $i === 0 ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#mg-<?= e($g['code']) ?>" id="<?= e($g['code']) ?>" type="button"><?= e($g['name']) ?></button></li><?php endforeach; ?>
    </ul>
    <div class="tab-content">
    <?php foreach ($garments as $i => $g):
        $def = studio_definitions()[$g['code']];
        $chart = studio_chart((int) $g['id']);
        $opts = studio_options((int) $g['id']);
        $styles = [];
        foreach ($opts as $grp => $list) {
            $d = array_values(array_filter($list, fn($o) => (int) $o['is_default']))[0] ?? $list[0];
            $styles[$grp] = $d['value_key'];
        }
        $cfg = ['gender' => $def['gender'], 'base' => $def['base'], 'measurements' => $def['measurements'], 'chart' => $chart['M'] ?? (reset($chart) ?: []), 'styles' => $styles, 'color' => '#E9E1D5'];
    ?>
        <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="mg-<?= e($g['code']) ?>" role="tabpanel">
            <div class="uc-guide" data-measure-guide data-config="<?= e(json_encode($cfg)) ?>">
                <div class="uc-studio-stage-card"><div class="uc-stage" data-stage></div></div>
                <ol class="uc-guide-list">
                    <?php foreach ($def['measurements'] as $k => $f): ?>
                        <li tabindex="0" data-guide-key="<?= e($k) ?>"><strong><?= e($f['label']) ?></strong><span><?= e($f['help']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <div class="uc-card-box mt-4"><h2 class="h5">Standard sizes — <?= e($g['name']) ?></h2><?php partial('components/size-chart', ['def' => $def, 'chart' => $chart, 'fitTable' => studio_fit_table((int) $g['id'])]); ?></div>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="text-center mt-5"><a class="uc-btn" href="<?= e(url('custom-size')) ?>">Start customising</a></div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
