<?php
/** Standard size chart for a garment. Expects $def, $chart, $fitTable. */
if (!$chart) {
    return;
}
$sizes = array_keys($chart);
?>
<div class="table-responsive uc-size-chart">
    <table class="table table-sm align-middle">
        <thead><tr><th>Body measurement (inches)</th><?php foreach ($sizes as $s): ?><th class="text-center"><?= e($s) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($def['measurements'] as $k => $f): if ($k === 'height') continue; ?>
            <tr><th scope="row"><?= e($f['label']) ?></th><?php foreach ($sizes as $s): ?><td class="text-center"><?= isset($chart[$s][$k]) ? e(rtrim(rtrim(number_format($chart[$s][$k], 1), '0'), '.')) : '—' ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if ($fitTable): $byS = array_column($fitTable, null, 'size_label'); ?>
            <tr class="uc-chart-fit"><th scope="row">Usually fits height</th><?php foreach ($sizes as $s): $r = $byS[$s] ?? null; ?><td class="text-center small"><?= $r ? e(floor($r['height_min_in'] / 12) . "'" . fmod($r['height_min_in'], 12) . '"–' . floor($r['height_max_in'] / 12) . "'" . fmod($r['height_max_in'], 12) . '"') : '—' ?></td><?php endforeach; ?></tr>
            <tr class="uc-chart-fit"><th scope="row">Usually fits weight</th><?php foreach ($sizes as $s): $r = $byS[$s] ?? null; ?><td class="text-center small"><?= $r ? e((int) $r['weight_min_kg'] . '–' . (int) $r['weight_max_kg'] . ' kg') : '—' ?></td><?php endforeach; ?></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<p class="small text-muted mb-0">Typical Pakistani ready-to-wear sizing. Body measurements — the garment is stitched with comfortable ease added.</p>
