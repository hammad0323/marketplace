<?php
/**
 * Root Cause diagram rendering - turns dynamic-tool submission data into an actual visual
 * Ishikawa/Fishbone diagram (SVG, server-rendered) instead of a plain list of field values.
 */

function fishbone_split_causes(?string $text, int $max = 4): array
{
    if (!$text) return [];
    $items = preg_split('/[\r\n,;]+/', $text);
    $items = array_values(array_filter(array_map('trim', $items), fn($s) => $s !== ''));
    $extra = count($items) - $max;
    $shown = array_map(fn($s) => mb_strlen($s) > 30 ? mb_substr($s, 0, 28) . '...' : $s, array_slice($items, 0, $max));
    if ($extra > 0) {
        $shown[] = "+$extra more";
    }
    return $shown;
}

/**
 * $causes = ['Man' => text, 'Machine' => text, 'Method' => text, 'Material' => text, 'Measurement' => text, 'Environment' => text]
 */
function render_fishbone_svg(string $effect, array $causes): string
{
    $categories = ['Man', 'Machine', 'Method', 'Material', 'Measurement', 'Environment'];
    $spineY = 300;
    $spineStart = 90;
    $spineEnd = 860;
    $xPositions = [230, 410, 590]; // three attachment points per side
    $svg = '<svg viewBox="0 0 1000 600" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:auto;font-family:Inter,Arial,sans-serif;" role="img" aria-label="Fishbone diagram">';
    $svg .= '<rect width="1000" height="600" fill="#F8FAFC"/>';

    // Spine + arrow head into the effect box
    $svg .= "<line x1=\"$spineStart\" y1=\"$spineY\" x2=\"$spineEnd\" y2=\"$spineY\" stroke=\"#0F172A\" stroke-width=\"3\"/>";
    $svg .= "<polygon points=\"$spineEnd," . ($spineY - 14) . " " . ($spineEnd + 40) . ",$spineY $spineEnd," . ($spineY + 14) . "\" fill=\"#0F172A\"/>";

    // Effect box
    $svg .= '<rect x="900" y="' . ($spineY - 45) . '" width="95" height="90" rx="10" fill="#2563EB"/>';
    $svg .= '<text x="947" y="' . ($spineY - 5) . '" text-anchor="middle" fill="#fff" font-size="12" font-weight="700">EFFECT</text>';
    foreach (fishbone_wrap_text($effect, 12) as $i => $line) {
        $svg .= '<text x="947" y="' . ($spineY + 14 + $i * 13) . '" text-anchor="middle" fill="#fff" font-size="10">' . htmlspecialchars($line) . '</text>';
    }

    foreach ($categories as $i => $cat) {
        $isTop = $i % 2 === 0;
        $x = $xPositions[intdiv($i, 2)];
        $boxY = $isTop ? 30 : 470;
        $boxH = 100;
        $lineEndY = $isTop ? $boxY + $boxH : $boxY;
        $svg .= "<line x1=\"$x\" y1=\"$spineY\" x2=\"" . ($x - 70) . "\" y2=\"$lineEndY\" stroke=\"#94A3B8\" stroke-width=\"2.5\"/>";

        $boxX = $x - 150;
        $svg .= "<rect x=\"$boxX\" y=\"$boxY\" width=\"160\" height=\"$boxH\" rx=\"10\" fill=\"#fff\" stroke=\"#CBD5E1\" stroke-width=\"1.5\"/>";
        $svg .= '<text x="' . ($boxX + 12) . '" y="' . ($boxY + 20) . '" fill="#2563EB" font-size="13" font-weight="700">' . htmlspecialchars($cat) . '</text>';

        $subCauses = fishbone_split_causes($causes[$cat] ?? null);
        if (!$subCauses) {
            $svg .= '<text x="' . ($boxX + 12) . '" y="' . ($boxY + 40) . '" fill="#94A3B8" font-size="10" font-style="italic">No causes logged</text>';
        } else {
            foreach ($subCauses as $j => $line) {
                $svg .= '<text x="' . ($boxX + 12) . '" y="' . ($boxY + 38 + $j * 15) . '" fill="#334155" font-size="10">&#8226; ' . htmlspecialchars($line) . '</text>';
            }
        }
    }

    $svg .= '</svg>';
    return $svg;
}

function fishbone_wrap_text(string $text, int $charsPerLine): array
{
    $words = explode(' ', $text);
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        if (mb_strlen($current . ' ' . $word) > $charsPerLine && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = trim($current . ' ' . $word);
        }
        if (count($lines) >= 3) break;
    }
    if ($current !== '' && count($lines) < 3) $lines[] = $current;
    return $lines ?: [''];
}
