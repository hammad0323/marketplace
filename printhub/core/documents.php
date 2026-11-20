<?php
/**
 * Document processing.
 *  - The original upload is never modified. Edits are stored as instructions and a separate print-ready PDF is built from them.
 *  - PDFs: page selection, order and rotation, fit / actual size / scale, margins, orientation (FPDI + FPDF).
 *  - Images: crop, rotation, mirroring, fit / fill / actual size, photo sheets (passport, ID card) and CNIC front + back layout (GD + FPDF).
 *  - DOCX: converted to PDF with LibreOffice when SOFFICE_PATH is configured; otherwise the shop prints the original
 *    (we never pretend to edit Word documents in the browser).
 * When a PDF uses features the built-in reader cannot open, it is rewritten with pdftocairo (if configured);
 * otherwise the order keeps the original file plus the customer's instructions and the shop is told why.
 */

use setasign\Fpdi\Fpdi;

class PrintPdf extends Fpdi
{
    /** Places a template rotated by 0/90/180/270° so that it fills the box (x, y, w, h) in mm. */
    public function placeTemplate($tpl, float $x, float $y, float $w, float $h, int $rotate): void
    {
        $rotate = (($rotate % 360) + 360) % 360;
        if ($rotate === 0) {
            $this->useTemplate($tpl, $x, $y, $w, $h);
            return;
        }
        $k = $this->k;
        $cx = ($x + $w / 2) * $k;
        $cy = ($this->h - ($y + $h / 2)) * $k;
        $a = deg2rad(-$rotate);
        $c = cos($a);
        $s = sin($a);
        // rotate around the box centre; the template is drawn with its sides swapped for 90/270
        [$tw, $th] = in_array($rotate, [90, 270], true) ? [$h, $w] : [$w, $h];
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        $this->useTemplate($tpl, $x + ($w - $tw) / 2, $y + ($h - $th) / 2, $tw, $th);
        $this->_out('Q');
    }
}

function pdf_page_count(string $file): ?int
{
    try {
        $pdf = new Fpdi();
        return $pdf->setSourceFile($file);
    } catch (Throwable $e) {
        $norm = pdf_normalise($file);
        if ($norm) {
            try {
                $pdf = new Fpdi();
                $n = $pdf->setSourceFile($norm);
                @unlink($norm);
                return $n;
            } catch (Throwable $e2) {
                @unlink($norm);
            }
        }
        // last resort: count page objects (approximate, never used for building files)
        $raw = (string) @file_get_contents($file);
        $n = preg_match_all('#/Type\s*/Page[^s]#', $raw);
        return $n > 0 ? $n : null;
    }
}

/** Rewrites a PDF with pdftocairo so FPDI can read it. Returns a temp path or null. */
function pdf_normalise(string $file): ?string
{
    if (!PDFTOCAIRO_PATH || !is_executable(PDFTOCAIRO_PATH)) {
        return null;
    }
    $out = tempnam(sys_get_temp_dir(), 'phn') . '.pdf';
    exec(escapeshellarg(PDFTOCAIRO_PATH) . ' -pdf ' . escapeshellarg($file) . ' ' . escapeshellarg($out) . ' 2>&1', $o, $code);
    return $code === 0 && is_file($out) && filesize($out) > 0 ? $out : null;
}

/** DOCX → PDF with LibreOffice. Returns the stored relative path or null when conversion is unavailable or fails. */
function docx_convert(string $file): ?string
{
    if (!SOFFICE_PATH || !is_executable(SOFFICE_PATH)) {
        return null;
    }
    $dir = sys_get_temp_dir() . '/phconv_' . random_token(6);
    @mkdir($dir, 0700);
    $src = $dir . '/doc.docx';
    copy($file, $src);
    $cmd = 'HOME=' . escapeshellarg($dir) . ' timeout 60 ' . escapeshellarg(SOFFICE_PATH) . ' --headless --norestore --convert-to pdf --outdir ' . escapeshellarg($dir) . ' ' . escapeshellarg($src) . ' 2>&1';
    exec($cmd, $o, $code);
    $pdf = $dir . '/doc.pdf';
    $rel = null;
    if ($code === 0 && is_file($pdf) && filesize($pdf) > 0) {
        [$rel, $full] = storage_new_path('uploads', 'pdf');
        rename($pdf, $full);
    }
    array_map('unlink', glob($dir . '/*') ?: []);
    @rmdir($dir);
    return $rel;
}

/** Paper size in mm for an item (option or custom), oriented. */
function item_paper_mm(array $item): array
{
    $w = $h = null;
    if (!empty($item['paper_size_id'])) {
        $o = db_row('SELECT code, width_mm, height_mm FROM printing_options WHERE id = ?', [(int) $item['paper_size_id']]);
        if ($o && $o['width_mm']) {
            [$w, $h] = [(float) $o['width_mm'], (float) $o['height_mm']];
        }
    }
    if (!$w && !empty($item['custom_width_mm']) && !empty($item['custom_height_mm'])) {
        [$w, $h] = [max(20, min(1200, (float) $item['custom_width_mm'])), max(20, min(1200, (float) $item['custom_height_mm']))];
    }
    [$w, $h] = $w ? [$w, $h] : [210.0, 297.0];
    $landscape = ($item['orientation'] ?? 'portrait') === 'landscape';
    return $landscape === ($w > $h) ? [$w, $h] : [$h, $w];
}

/** Normalised layout instructions from the customer (with safe limits). */
function layout_clean($layout): array
{
    $l = is_array($layout) ? $layout : (json_decode((string) $layout, true) ?: []);
    $num = fn($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (float) $v)) : $def;
    $crop = $l['crop'] ?? null;
    if (is_array($crop)) {
        $crop = ['x' => $num($crop['x'] ?? 0, 0, 1, 0), 'y' => $num($crop['y'] ?? 0, 0, 1, 0), 'w' => $num($crop['w'] ?? 1, 0.01, 1, 1), 'h' => $num($crop['h'] ?? 1, 0.01, 1, 1)];
        $crop['w'] = min($crop['w'], 1 - $crop['x']);
        $crop['h'] = min($crop['h'], 1 - $crop['y']);
    }
    return [
        'fit' => in_array($l['fit'] ?? 'fit', ['fit', 'fill', 'actual', 'grid', 'id_pair'], true) ? ($l['fit'] ?? 'fit') : 'fit',
        'margin_mm' => $num($l['margin_mm'] ?? 5, 0, 40, 5),
        'scale' => $num($l['scale'] ?? 100, 10, 400, 100),
        'rotate' => (int) $num(round(($l['rotate'] ?? 0) / 90) * 90, -270, 270, 0),
        'flip' => !empty($l['flip']),
        'crop' => $crop,
        'item_w_mm' => $num($l['item_w_mm'] ?? 0, 0, 300, 0),
        'item_h_mm' => $num($l['item_h_mm'] ?? 0, 0, 300, 0),
        'gap_mm' => $num($l['gap_mm'] ?? 3, 0, 20, 3),
        'sheet' => ($l['sheet'] ?? '4x6') === 'a4' ? 'a4' : '4x6',
    ];
}

/** Page selection [{page, rotate}] limited to existing pages. Empty = all pages. */
function pages_clean($sel, int $pageCount): array
{
    $sel = is_array($sel) ? $sel : (json_decode((string) $sel, true) ?: []);
    $out = [];
    foreach (array_slice($sel, 0, 2000) as $p) {
        $n = (int) (is_array($p) ? ($p['page'] ?? 0) : $p);
        if ($n >= 1 && $n <= $pageCount) {
            $out[] = ['page' => $n, 'rotate' => (int) (round(((int) (is_array($p) ? ($p['rotate'] ?? 0) : 0)) / 90) * 90) % 360];
        }
    }
    if (!$out) {
        for ($i = 1; $i <= $pageCount; $i++) {
            $out[] = ['page' => $i, 'rotate' => 0];
        }
    }
    return $out;
}

/** Box (x, y, w, h) for content of size cw × ch in a printable area, for fit / actual / scale. */
function layout_box(float $cw, float $ch, float $aw, float $ah, float $ax, float $ay, array $l): array
{
    if ($l['fit'] === 'actual') {
        $s = $l['scale'] / 100;
        $w = $cw * $s;
        $h = $ch * $s;
    } else {
        $s = min($aw / $cw, $ah / $ch) * ($l['fit'] === 'fit' ? $l['scale'] / 100 : 1);
        $w = $cw * $s;
        $h = $ch * $s;
    }
    return [$ax + ($aw - $w) / 2, $ay + ($ah - $h) / 2, $w, $h];
}

/** Applies crop / rotation / mirroring to an image with GD; returns [temp png/jpg path, width px, height px]. */
function image_prepare(string $file, array $l, ?float $targetAspect = null): array
{
    $info = getimagesize($file);
    $src = match ($info['mime'] ?? '') {
        'image/jpeg' => imagecreatefromjpeg($file),
        'image/png' => imagecreatefrompng($file),
        'image/webp' => imagecreatefromwebp($file),
        default => null,
    };
    if (!$src) {
        throw new RuntimeException('Image could not be read.');
    }
    if (($info['mime'] ?? '') === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        if (in_array($o, [3, 6, 8], true)) {
            $src = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$o], 0);
        }
    }
    // same order as the browser editor (CSS rotate() scaleX()): mirror first, then rotate clockwise, then crop
    if ($l['flip']) {
        imageflip($src, IMG_FLIP_HORIZONTAL);
    }
    if ($l['rotate']) {
        $src = imagerotate($src, -$l['rotate'], imagecolorallocate($src, 255, 255, 255));
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $cx = 0;
    $cy = 0;
    $cw = $w;
    $ch = $h;
    if ($l['crop']) {
        $cx = (int) round($l['crop']['x'] * $w);
        $cy = (int) round($l['crop']['y'] * $h);
        $cw = max(1, (int) round($l['crop']['w'] * $w));
        $ch = max(1, (int) round($l['crop']['h'] * $h));
    }
    if ($targetAspect) { // fill: centre-crop to the target aspect
        if ($cw / $ch > $targetAspect) {
            $nw = (int) round($ch * $targetAspect);
            $cx += (int) (($cw - $nw) / 2);
            $cw = $nw;
        } else {
            $nh = (int) round($cw / $targetAspect);
            $cy += (int) (($ch - $nh) / 2);
            $ch = $nh;
        }
    }
    $scale = min(1, 4000 / max($cw, $ch));
    $ow = max(1, (int) round($cw * $scale));
    $oh = max(1, (int) round($ch * $scale));
    $dst = imagecreatetruecolor($ow, $oh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $ow, $oh, $cw, $ch);
    $tmp = tempnam(sys_get_temp_dir(), 'phi') . '.jpg';
    imagejpeg($dst, $tmp, 92);
    imagedestroy($src);
    imagedestroy($dst);
    return [$tmp, $ow, $oh];
}

/**
 * Builds the print-ready PDF for an item from its source files (originals or DOCX conversions).
 * $sources: [['path' => absolute path, 'kind' => pdf|image]]. Returns ['path' => temp file, 'pages' => n] or throws.
 */
function build_print_ready(array $item, array $sources): array
{
    [$pw, $ph] = item_paper_mm($item);
    $l = layout_clean($item['layout'] ?? []);
    if (in_array($l['fit'], ['grid', 'id_pair'], true) && min($pw, $ph) < 100) {
        // the chosen "paper" is the photo / card size: lay copies out on a printing sheet
        $l['item_w_mm'] = $l['item_w_mm'] ?: min($pw, $ph);
        $l['item_h_mm'] = $l['item_h_mm'] ?: max($pw, $ph);
        if ($l['fit'] === 'id_pair') {
            [$l['item_w_mm'], $l['item_h_mm']] = [max($pw, $ph), min($pw, $ph)];
        }
        [$pw, $ph] = $l['fit'] === 'id_pair' || $l['sheet'] === 'a4' ? [210.0, 297.0] : [101.6, 152.4];
    }
    $pdf = new PrintPdf($pw > $ph ? 'L' : 'P', 'mm', [min($pw, $ph), max($pw, $ph)]);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetCreator(platform_name());
    $m = $l['margin_mm'];
    $aw = max(10, $pw - 2 * $m);
    $ah = max(10, $ph - 2 * $m);
    $temps = [];
    try {
        $images = array_values(array_filter($sources, fn($s) => $s['kind'] === 'image'));
        if ($images && in_array($l['fit'], ['grid', 'id_pair'], true)) {
            build_image_sheet($pdf, $item, $images, $l, $pw, $ph, $temps);
        } else {
            foreach ($sources as $src) {
                if ($src['kind'] === 'pdf') {
                    $file = $src['path'];
                    try {
                        $count = $pdf->setSourceFile($file);
                    } catch (Throwable $e) {
                        $norm = pdf_normalise($file);
                        if (!$norm) {
                            throw new RuntimeException('This PDF uses features the server cannot rewrite; print the original file with the selected pages.');
                        }
                        $temps[] = $norm;
                        $count = $pdf->setSourceFile($norm);
                    }
                    foreach (pages_clean($item['page_selection'] ?? [], $count) as $sel) {
                        $tpl = $pdf->importPage($sel['page']);
                        $size = $pdf->getTemplateSize($tpl);
                        $rot = (($sel['rotate'] + $l['rotate']) % 360 + 360) % 360;
                        [$cw, $ch] = in_array($rot, [90, 270], true) ? [$size['height'], $size['width']] : [$size['width'], $size['height']];
                        $pdf->AddPage();
                        [$x, $y, $w, $h] = layout_box($cw, $ch, $aw, $ah, $m, $m, $l['fit'] === 'fill' ? ['fit' => 'fit', 'scale' => 100] + $l : $l);
                        $pdf->placeTemplate($tpl, $x, $y, $w, $h, $rot);
                    }
                } else {
                    $fill = $l['fit'] === 'fill';
                    [$tmp, $iw, $ih] = image_prepare($src['path'], $l, $fill ? $aw / $ah : null);
                    $temps[] = $tmp;
                    $pdf->AddPage();
                    if ($fill) {
                        $pdf->Image($tmp, $m, $m, $aw, $ah);
                    } else {
                        $dpiW = $iw / 300 * 25.4; // actual size assumes 300 dpi
                        $dpiH = $ih / 300 * 25.4;
                        [$x, $y, $w, $h] = layout_box($l['fit'] === 'actual' ? $dpiW : $iw, $l['fit'] === 'actual' ? $dpiH : $ih, $aw, $ah, $m, $m, $l);
                        $pdf->Image($tmp, $x, $y, $w, $h);
                    }
                }
            }
        }
        $out = tempnam(sys_get_temp_dir(), 'php') . '.pdf';
        $pdf->Output('F', $out);
        return ['path' => $out, 'pages' => $pdf->PageNo()];
    } finally {
        foreach ($temps as $t) {
            @unlink($t);
        }
    }
}

/** Photo sheets: repeats each image at item size in a grid (passport photos, ID cards), or CNIC front + back on one page. */
function build_image_sheet(PrintPdf $pdf, array $item, array $images, array $l, float $pw, float $ph, array &$temps): void
{
    $m = $l['margin_mm'];
    $gap = $l['gap_mm'];
    if ($l['fit'] === 'id_pair') {
        $iw = $l['item_w_mm'] ?: 85.6;
        $ih = $l['item_h_mm'] ?: 54;
        $pdf->AddPage();
        $y = $m + 12; // front at the top, back below it, like a photocopied CNIC
        foreach (array_slice($images, 0, 2) as $i => $img) {
            [$tmp] = image_prepare($img['path'], $l, $iw / $ih);
            $temps[] = $tmp;
            $pdf->Image($tmp, ($pw - $iw) / 2, $y + $i * ($ih + $gap * 6), $iw, $ih);
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Rect(($pw - $iw) / 2, $y + $i * ($ih + $gap * 6), $iw, $ih);
        }
        return;
    }
    $iw = $l['item_w_mm'] ?: 35;
    $ih = $l['item_h_mm'] ?: 45;
    $cols = max(1, (int) floor(($pw - 2 * $m + $gap) / ($iw + $gap)));
    $rows = max(1, (int) floor(($ph - 2 * $m + $gap) / ($ih + $gap)));
    $perSheet = $cols * $rows;
    $want = max(1, (int) ($item['quantity'] ?? $perSheet));
    $x0 = ($pw - ($cols * $iw + ($cols - 1) * $gap)) / 2;
    $y0 = $m;
    foreach ($images as $img) {
        [$tmp] = image_prepare($img['path'], $l, $iw / $ih);
        $temps[] = $tmp;
        for ($n = 0; $n < $want; $n++) {
            if ($n % $perSheet === 0) {
                $pdf->AddPage();
            }
            $k = $n % $perSheet;
            $x = $x0 + ($k % $cols) * ($iw + $gap);
            $y = $y0 + intdiv($k, $cols) * ($ih + $gap);
            $pdf->Image($tmp, $x, $y, $iw, $ih);
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->Rect($x, $y, $iw, $ih);
        }
    }
}
