<?php
/**
 * Calculation Engine - reusable quality/statistical formulas.
 * No formula is hardcoded into UI pages; pages call these shared functions.
 */

function calc_oee(float $availability, float $performance, float $quality): float
{
    return round($availability * $performance * $quality / 10000, 2); // inputs as % -> result as %
}

function calc_availability(float $plannedMinutes, float $downtimeMinutes): float
{
    if ($plannedMinutes <= 0) return 0;
    $runTime = max(0, $plannedMinutes - $downtimeMinutes);
    return round(($runTime / $plannedMinutes) * 100, 2);
}

function calc_performance(float $idealCycleTime, float $totalCount, float $runTimeMinutes): float
{
    if ($runTimeMinutes <= 0) return 0;
    $val = (($idealCycleTime * $totalCount) / $runTimeMinutes) * 100;
    return round(min($val, 100), 2);
}

function calc_quality_rate(float $goodCount, float $totalCount): float
{
    if ($totalCount <= 0) return 0;
    return round(($goodCount / $totalCount) * 100, 2);
}

function calc_cp(float $usl, float $lsl, float $stdev): ?float
{
    if ($stdev <= 0) return null;
    return round(($usl - $lsl) / (6 * $stdev), 3);
}

function calc_cpk(float $usl, float $lsl, float $mean, float $stdev): ?float
{
    if ($stdev <= 0) return null;
    $cpu = ($usl - $mean) / (3 * $stdev);
    $cpl = ($mean - $lsl) / (3 * $stdev);
    return round(min($cpu, $cpl), 3);
}

function capability_interpretation(?float $cpk): string
{
    if ($cpk === null) return 'Insufficient data';
    if ($cpk >= 1.67) return 'Excellent - process highly capable';
    if ($cpk >= 1.33) return 'Adequate - process capable';
    if ($cpk >= 1.0) return 'Marginal - process barely capable, monitor closely';
    return 'Poor - process not capable, action required';
}

function calc_rpn(int $severity, int $occurrence, int $detection): int
{
    return $severity * $occurrence * $detection;
}

function rpn_risk_level(int $rpn): string
{
    if ($rpn >= 200) return 'critical';
    if ($rpn >= 100) return 'high';
    if ($rpn >= 50) return 'medium';
    return 'low';
}

function calc_defect_rate(float $defects, float $totalUnits): float
{
    if ($totalUnits <= 0) return 0;
    return round(($defects / $totalUnits) * 100, 2);
}

function calc_fpy(float $goodUnitsFirstPass, float $totalUnits): float
{
    if ($totalUnits <= 0) return 0;
    return round(($goodUnitsFirstPass / $totalUnits) * 100, 2);
}

function calc_scrap_rate(float $scrapQty, float $producedQty): float
{
    if ($producedQty <= 0) return 0;
    return round(($scrapQty / $producedQty) * 100, 2);
}

function calc_rework_rate(float $reworkQty, float $producedQty): float
{
    if ($producedQty <= 0) return 0;
    return round(($reworkQty / $producedQty) * 100, 2);
}

function calc_cost_of_quality(float $prevention, float $appraisal, float $internalFailure, float $externalFailure): array
{
    $total = $prevention + $appraisal + $internalFailure + $externalFailure;
    return [
        'prevention' => $prevention, 'appraisal' => $appraisal,
        'internal_failure' => $internalFailure, 'external_failure' => $externalFailure,
        'total' => round($total, 2),
        'conformance_cost' => round($prevention + $appraisal, 2),
        'nonconformance_cost' => round($internalFailure + $externalFailure, 2),
    ];
}

/**
 * SPC control limits. $type: xbar_r | p | np | c | u
 * $data: array of subgroup summary values depending on chart type.
 */
function calc_control_limits(string $type, array $data): array
{
    switch ($type) {
        case 'xbar_r':
            $xbars = $data['xbars']; $ranges = $data['ranges']; $n = $data['subgroup_size'] ?? 5;
            $A2 = spc_constant('A2', $n); $D3 = spc_constant('D3', $n); $D4 = spc_constant('D4', $n);
            $xbarbar = array_sum($xbars) / max(1, count($xbars));
            $rbar = array_sum($ranges) / max(1, count($ranges));
            return [
                'cl' => round($xbarbar, 4), 'ucl' => round($xbarbar + $A2 * $rbar, 4), 'lcl' => round($xbarbar - $A2 * $rbar, 4),
                'r_cl' => round($rbar, 4), 'r_ucl' => round($D4 * $rbar, 4), 'r_lcl' => round($D3 * $rbar, 4),
            ];
        case 'p':
            $defectives = array_sum($data['defectives']); $totalInspected = array_sum($data['sample_sizes']);
            $pbar = $totalInspected > 0 ? $defectives / $totalInspected : 0;
            $avgN = count($data['sample_sizes']) ? $totalInspected / count($data['sample_sizes']) : 1;
            $sigma = $avgN > 0 ? sqrt($pbar * (1 - $pbar) / $avgN) : 0;
            return ['cl' => round($pbar, 4), 'ucl' => round(min(1, $pbar + 3 * $sigma), 4), 'lcl' => round(max(0, $pbar - 3 * $sigma), 4)];
        case 'c':
            $counts = $data['counts']; $cbar = count($counts) ? array_sum($counts) / count($counts) : 0;
            $sigma = sqrt($cbar);
            return ['cl' => round($cbar, 4), 'ucl' => round($cbar + 3 * $sigma, 4), 'lcl' => round(max(0, $cbar - 3 * $sigma), 4)];
        case 'u':
            $defects = array_sum($data['defects']); $units = array_sum($data['units']);
            $ubar = $units > 0 ? $defects / $units : 0;
            $avgN = count($data['units']) ? $units / count($data['units']) : 1;
            $sigma = $avgN > 0 ? sqrt($ubar / $avgN) : 0;
            return ['cl' => round($ubar, 4), 'ucl' => round($ubar + 3 * $sigma, 4), 'lcl' => round(max(0, $ubar - 3 * $sigma), 4)];
        default:
            return ['cl' => 0, 'ucl' => 0, 'lcl' => 0];
    }
}

function spc_constant(string $constant, int $n): float
{
    $table = [
        2 => ['A2' => 1.880, 'D3' => 0, 'D4' => 3.267],
        3 => ['A2' => 1.023, 'D3' => 0, 'D4' => 2.574],
        4 => ['A2' => 0.729, 'D3' => 0, 'D4' => 2.282],
        5 => ['A2' => 0.577, 'D3' => 0, 'D4' => 2.114],
        6 => ['A2' => 0.483, 'D3' => 0, 'D4' => 2.004],
        7 => ['A2' => 0.419, 'D3' => 0.076, 'D4' => 1.924],
        8 => ['A2' => 0.373, 'D3' => 0.136, 'D4' => 1.864],
        9 => ['A2' => 0.337, 'D3' => 0.184, 'D4' => 1.816],
        10 => ['A2' => 0.308, 'D3' => 0.223, 'D4' => 1.777],
    ];
    $n = max(2, min(10, $n));
    return $table[$n][$constant] ?? $table[5][$constant];
}

function detect_control_violations(array $points, float $cl, float $ucl, float $lcl): array
{
    $violations = [];
    $n = count($points);
    for ($i = 0; $i < $n; $i++) {
        if ($points[$i] > $ucl || $points[$i] < $lcl) {
            $violations[] = ['index' => $i, 'rule' => 'Rule 1: Point beyond control limits', 'value' => $points[$i]];
        }
    }
    for ($i = 0; $i <= $n - 9; $i++) {
        $slice = array_slice($points, $i, 9);
        if (count(array_filter($slice, fn($v) => $v > $cl)) === 9 || count(array_filter($slice, fn($v) => $v < $cl)) === 9) {
            $violations[] = ['index' => $i + 8, 'rule' => 'Rule 2: 9 consecutive points on one side of centerline', 'value' => $points[$i + 8]];
        }
    }
    return $violations;
}

/**
 * AQL sample-size lookup (simplified ANSI/ASQ Z1.4 general inspection level II, normal severity).
 */
function aql_sample_plan(int $lotSize): array
{
    $table = [
        [8, 2, 0, 1], [15, 3, 0, 1], [25, 5, 0, 1], [50, 8, 0, 1], [90, 13, 1, 2],
        [150, 20, 1, 2], [280, 32, 2, 3], [500, 50, 3, 4], [1200, 80, 5, 6],
        [3200, 125, 7, 8], [10000, 200, 10, 11], [35000, 315, 14, 15],
        [150000, 500, 21, 22], [500000, 800, 21, 22], [PHP_INT_MAX, 1250, 21, 22],
    ];
    foreach ($table as [$maxLot, $sampleSize, $ac, $re]) {
        if ($lotSize <= $maxLot) {
            return ['sample_size' => $sampleSize, 'accept' => $ac, 'reject' => $re];
        }
    }
    return ['sample_size' => 1250, 'accept' => 21, 'reject' => 22];
}

/**
 * Weighted composite quality score. $components = ['key' => ['value' => 0-100, 'weight' => 0-1]]
 */
function calc_weighted_score(array $components): float
{
    $totalWeight = 0;
    $sum = 0;
    foreach ($components as $c) {
        $sum += ($c['value'] ?? 0) * ($c['weight'] ?? 0);
        $totalWeight += ($c['weight'] ?? 0);
    }
    if ($totalWeight <= 0) return 0;
    return round($sum / $totalWeight, 2);
}

/**
 * Evaluate a numeric reading against threshold engine config.
 * Returns: normal | warning | critical | out_of_spec
 */
function evaluate_threshold(float $value, ?float $min, ?float $max, ?float $warningLow = null, ?float $warningHigh = null, ?float $criticalLow = null, ?float $criticalHigh = null): string
{
    if ($criticalLow !== null && $value <= $criticalLow) return 'critical';
    if ($criticalHigh !== null && $value >= $criticalHigh) return 'critical';
    if ($min !== null && $value < $min) return 'out_of_spec';
    if ($max !== null && $value > $max) return 'out_of_spec';
    if ($warningLow !== null && $value <= $warningLow) return 'warning';
    if ($warningHigh !== null && $value >= $warningHigh) return 'warning';
    return 'normal';
}

/**
 * Safe arithmetic formula evaluator (no eval()). Supports + - * / ( ) and named variables.
 * Example: evaluate_formula("(availability*performance*quality)/10000", ['availability'=>90,'performance'=>85,'quality'=>99])
 */
function evaluate_formula(string $expression, array $variables): ?float
{
    $expression = strtolower($expression);
    foreach ($variables as $key => $val) {
        $expression = preg_replace('/\b' . preg_quote(strtolower($key), '/') . '\b/', '(' . (float)$val . ')', $expression);
    }
    if (!preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/', $expression)) {
        return null;
    }
    try {
        $tokens = preg_split('/([\+\-\*\/\(\)])/', $expression, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => $t !== ''));
        $pos = 0;
        $result = _formula_expr($tokens, $pos);
        return round($result, 6);
    } catch (Throwable $e) {
        return null;
    }
}

function _formula_expr(array $tokens, int &$pos): float
{
    $value = _formula_term($tokens, $pos);
    while (isset($tokens[$pos]) && in_array($tokens[$pos], ['+', '-'], true)) {
        $op = $tokens[$pos++];
        $rhs = _formula_term($tokens, $pos);
        $value = $op === '+' ? $value + $rhs : $value - $rhs;
    }
    return $value;
}

function _formula_term(array $tokens, int &$pos): float
{
    $value = _formula_factor($tokens, $pos);
    while (isset($tokens[$pos]) && in_array($tokens[$pos], ['*', '/'], true)) {
        $op = $tokens[$pos++];
        $rhs = _formula_factor($tokens, $pos);
        $value = $op === '*' ? $value * $rhs : ($rhs != 0 ? $value / $rhs : 0);
    }
    return $value;
}

function _formula_factor(array $tokens, int &$pos): float
{
    $token = $tokens[$pos] ?? null;
    if ($token === '(') {
        $pos++;
        $value = _formula_expr($tokens, $pos);
        if (($tokens[$pos] ?? null) === ')') $pos++;
        return $value;
    }
    if ($token === '-') {
        $pos++;
        return -_formula_factor($tokens, $pos);
    }
    $pos++;
    return is_numeric($token) ? (float)$token : 0.0;
}
