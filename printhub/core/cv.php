<?php
/**
 * CV Generator. Customers fill a structured form; templates in core/cv_templates/{key}.php turn it into HTML.
 * The same HTML feeds the live preview (web iframe / app web view) and the A4 PDF (dompdf), so what the
 * customer sees is what prints. Text stays real text in the PDF (selectable, ATS-readable).
 * Language and direction are stored per CV so Urdu / RTL templates can be added; English is enabled now.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

function cv_section_labels(): array
{
    return ['summary' => 'Professional Summary', 'experience' => 'Work Experience', 'education' => 'Education', 'skills' => 'Skills',
        'projects' => 'Projects', 'certifications' => 'Certifications', 'achievements' => 'Achievements', 'languages' => 'Languages', 'references' => 'References'];
}

function cv_default_data(): array
{
    return [
        'personal' => ['full_name' => '', 'title' => '', 'phone' => '', 'email' => '', 'city' => '', 'address' => '', 'website' => '', 'linkedin' => '', 'summary' => ''],
        'education' => [], 'experience' => [], 'skills' => [], 'languages' => [], 'certifications' => [], 'projects' => [], 'achievements' => [], 'references' => [],
        'references_on_request' => false,
    ];
}

/** Cleans submitted CV data: only known fields, trimmed, with length and count limits. */
function cv_clean(array $in): array
{
    $str = fn($v, int $max) => is_scalar($v) ? mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v)), 0, $max) : '';
    $list = function ($rows, array $fields, int $maxRows) use ($str) {
        $out = [];
        foreach (array_slice(is_array($rows) ? $rows : [], 0, $maxRows) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $row = [];
            foreach ($fields as $f => $max) {
                $row[$f] = $f === 'current' ? !empty($r[$f]) : $str($r[$f] ?? '', $max);
            }
            if (implode('', array_filter($row, 'is_string')) !== '') {
                $out[] = $row;
            }
        }
        return $out;
    };
    $p = is_array($in['personal'] ?? null) ? $in['personal'] : [];
    $d = cv_default_data();
    foreach (['full_name' => 100, 'title' => 120, 'phone' => 40, 'email' => 120, 'city' => 80, 'address' => 200, 'website' => 150, 'linkedin' => 150, 'summary' => 1500] as $k => $max) {
        $d['personal'][$k] = $str($p[$k] ?? '', $max);
    }
    $d['education'] = $list($in['education'] ?? [], ['degree' => 150, 'institute' => 150, 'location' => 80, 'start' => 20, 'end' => 20, 'grade' => 40, 'details' => 600], 10);
    $d['experience'] = $list($in['experience'] ?? [], ['role' => 120, 'company' => 150, 'location' => 80, 'start' => 20, 'end' => 20, 'current' => 1, 'details' => 2000], 12);
    $d['projects'] = $list($in['projects'] ?? [], ['name' => 150, 'link' => 150, 'details' => 800], 10);
    $d['certifications'] = $list($in['certifications'] ?? [], ['name' => 150, 'issuer' => 120, 'year' => 20], 15);
    $d['languages'] = $list($in['languages'] ?? [], ['name' => 60, 'level' => 40], 10);
    $d['references'] = $list($in['references'] ?? [], ['name' => 100, 'position' => 100, 'company' => 120, 'phone' => 40, 'email' => 120], 4);
    foreach (['skills' => 40, 'achievements' => 15] as $k => $max) {
        $vals = is_array($in[$k] ?? null) ? $in[$k] : preg_split('/\r?\n/', (string) ($in[$k] ?? ''));
        $d[$k] = array_values(array_filter(array_map(fn($v) => $str($v, $k === 'skills' ? 60 : 300), array_slice($vals, 0, $max)), 'strlen'));
    }
    $d['references_on_request'] = !empty($in['references_on_request']);
    return $d;
}

function cv_clean_order($order): array
{
    $order = is_array($order) ? $order : (json_decode((string) $order, true) ?: []);
    $known = array_keys(cv_section_labels());
    $out = array_values(array_unique(array_intersect(array_map('strval', $order), $known)));
    return $out ?: $known;
}

function cv_template(int $id, bool $activeOnly = true): ?array
{
    return db_row('SELECT * FROM cv_templates WHERE id = ? AND deleted_at IS NULL' . ($activeOnly ? ' AND is_active = 1' : ''), [$id]);
}

function cv_templates_public(): array
{
    return array_map(fn($t) => ['id' => (int) $t['id'], 'key' => $t['template_key'], 'name' => $t['name'], 'name_ur' => $t['name_ur'], 'category' => $t['category'],
        'description' => $t['description'], 'default_color' => $t['default_color'], 'supports_photo' => (bool) $t['supports_photo'], 'ats_friendly' => (bool) $t['ats_friendly'],
        'is_featured' => (bool) $t['is_featured'], 'preview_url' => $t['preview_path'] ? url('media/' . $t['preview_path']) : url('cv/template-preview/' . $t['id'])],
        db_all('SELECT * FROM cv_templates WHERE is_active = 1 AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order, id'));
}

function cv_by_token(string $token, ?array $session): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $cv = db_row('SELECT * FROM customer_cvs WHERE public_token = ? AND deleted_at IS NULL', [$token]);
    return $cv && $session && (int) $cv['session_id'] === (int) $session['id'] ? $cv : null;
}

/** Creates or updates a CV for the session. Returns the CV row or an error string. */
function cv_save(array $session, array $in, ?array $existing = null): array|string
{
    $tpl = cv_template((int) ($in['template_id'] ?? ($existing['template_id'] ?? 0)));
    if (!$tpl) {
        return 'Please choose a CV template.';
    }
    $data = cv_clean(is_array($in['data'] ?? null) ? $in['data'] : (json_decode((string) ($in['data'] ?? ''), true) ?: []));
    $row = ['template_id' => (int) $tpl['id'], 'title' => mb_substr(trim((string) ($in['title'] ?? '')), 0, 120) ?: ($data['personal']['full_name'] ? $data['personal']['full_name'] . ' — CV' : 'My CV'),
        'accent_color' => valid_color($in['accent_color'] ?? null, $tpl['default_color']), 'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        'section_order' => json_encode(cv_clean_order($in['section_order'] ?? [])), 'language' => 'en', 'direction' => 'ltr'];
    if ($existing) {
        db_update('customer_cvs', $row, 'id = ?', [(int) $existing['id']]);
        return db_row('SELECT * FROM customer_cvs WHERE id = ?', [(int) $existing['id']]);
    }
    if ((int) db_val('SELECT COUNT(*) FROM customer_cvs WHERE session_id = ? AND deleted_at IS NULL', [(int) $session['id']]) >= 20) {
        return 'You have reached the limit of 20 saved CVs. Delete one to create another.';
    }
    $row += ['public_token' => random_token(16), 'session_id' => (int) $session['id'], 'customer_id' => $session['customer_id'] ? (int) $session['customer_id'] : null];
    $id = db_insert('customer_cvs', $row);
    return db_row('SELECT * FROM customer_cvs WHERE id = ?', [$id]);
}

/** Square 480 px JPEG, metadata stripped. Returns the private storage path. */
function cv_photo_store(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 8 * 1048576) {
        throw new RuntimeException('Please choose a photo up to 8 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png' => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP photo.');
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $s = min($w, $h);
    $dst = imagecreatetruecolor(480, 480);
    imagecopyresampled($dst, $src, 0, 0, (int) (($w - $s) / 2), (int) (($h - $s) / 3), 480, 480, $s, $s);
    [$rel, $full] = storage_new_path('cv', 'jpg');
    imagejpeg($dst, $full, 88);
    imagedestroy($src);
    imagedestroy($dst);
    return $rel;
}

// ---------------------------------------------------------------- rendering
function cv_lines(string $text): array
{
    return array_values(array_filter(array_map(fn($l) => trim(ltrim(trim($l), "-•*·")), preg_split('/\r?\n/', $text)), 'strlen'));
}

function cv_dates(array $r): string
{
    $end = !empty($r['current']) ? 'Present' : ($r['end'] ?? '');
    return trim(($r['start'] ?? '') . (($r['start'] ?? '') && $end ? ' – ' : '') . $end);
}

/** HTML for one section in a template-neutral structure; templates style the classes. */
function cv_section_html(string $key, array $d, array $o = []): string
{
    $label = $o['labels'][$key] ?? cv_section_labels()[$key];
    $h = '<h2 class="sec-title">' . e($label) . '</h2>';
    switch ($key) {
        case 'summary':
            return $d['personal']['summary'] !== '' ? '<div class="sec sec-summary">' . $h . '<p class="summary">' . nl2br(e($d['personal']['summary'])) . '</p></div>' : '';
        case 'experience':
            if (!$d['experience']) return '';
            $html = '';
            foreach ($d['experience'] as $r) {
                $lines = cv_lines($r['details']);
                $html .= '<div class="entry"><table class="entry-head"><tr><td class="entry-title"><b>' . e($r['role']) . '</b>' . ($r['company'] ? '<span class="entry-org"> — ' . e($r['company']) . '</span>' : '') . '</td><td class="entry-date">' . e(cv_dates($r)) . '</td></tr></table>'
                    . ($r['location'] ? '<div class="entry-sub">' . e($r['location']) . '</div>' : '')
                    . ($lines ? '<ul>' . implode('', array_map(fn($l) => '<li>' . e($l) . '</li>', $lines)) . '</ul>' : '') . '</div>';
            }
            return '<div class="sec sec-experience">' . $h . $html . '</div>';
        case 'education':
            if (!$d['education']) return '';
            $html = '';
            foreach ($d['education'] as $r) {
                $html .= '<div class="entry"><table class="entry-head"><tr><td class="entry-title"><b>' . e($r['degree']) . '</b>' . ($r['institute'] ? '<span class="entry-org"> — ' . e($r['institute']) . '</span>' : '') . '</td><td class="entry-date">' . e(cv_dates($r)) . '</td></tr></table>'
                    . (($r['location'] || $r['grade']) ? '<div class="entry-sub">' . e(implode(' · ', array_filter([$r['location'], $r['grade'] ? 'Grade: ' . $r['grade'] : '']))) . '</div>' : '')
                    . ($r['details'] ? '<p class="entry-text">' . nl2br(e($r['details'])) . '</p>' : '') . '</div>';
            }
            return '<div class="sec sec-education">' . $h . $html . '</div>';
        case 'skills':
            if (!$d['skills']) return '';
            return '<div class="sec sec-skills">' . $h . (!empty($o['skill_list'])
                ? '<ul class="plain">' . implode('', array_map(fn($s) => '<li>' . e($s) . '</li>', $d['skills'])) . '</ul>'
                : '<p class="chips">' . implode(' ', array_map(fn($s) => '<span class="chip">' . e($s) . '</span>', $d['skills'])) . '</p>') . '</div>';
        case 'projects':
            if (!$d['projects']) return '';
            return '<div class="sec sec-projects">' . $h . implode('', array_map(fn($r) => '<div class="entry"><b>' . e($r['name']) . '</b>' . ($r['link'] ? ' <span class="entry-sub">' . e($r['link']) . '</span>' : '')
                . ($r['details'] ? '<p class="entry-text">' . nl2br(e($r['details'])) . '</p>' : '') . '</div>', $d['projects'])) . '</div>';
        case 'certifications':
            if (!$d['certifications']) return '';
            return '<div class="sec sec-certifications">' . $h . '<ul class="plain">' . implode('', array_map(fn($r) => '<li><b>' . e($r['name']) . '</b>' . (($r['issuer'] || $r['year']) ? ' — ' . e(implode(', ', array_filter([$r['issuer'], $r['year']]))) : '') . '</li>', $d['certifications'])) . '</ul></div>';
        case 'achievements':
            if (!$d['achievements']) return '';
            return '<div class="sec sec-achievements">' . $h . '<ul>' . implode('', array_map(fn($a) => '<li>' . e($a) . '</li>', $d['achievements'])) . '</ul></div>';
        case 'languages':
            if (!$d['languages']) return '';
            return '<div class="sec sec-languages">' . $h . '<ul class="plain">' . implode('', array_map(fn($r) => '<li>' . e($r['name']) . ($r['level'] ? ' <span class="muted">(' . e($r['level']) . ')</span>' : '') . '</li>', $d['languages'])) . '</ul></div>';
        case 'references':
            if (!$d['references'] && !$d['references_on_request']) return '';
            if (!$d['references']) return '<div class="sec sec-references">' . $h . '<p>Available on request.</p></div>';
            return '<div class="sec sec-references">' . $h . '<table class="refs"><tr>' . implode('', array_map(fn($r) => '<td><b>' . e($r['name']) . '</b><br>' . e(implode(', ', array_filter([$r['position'], $r['company']])))
                . ($r['phone'] ? '<br>' . e($r['phone']) : '') . ($r['email'] ? '<br>' . e($r['email']) : '') . '</td>', array_slice($d['references'], 0, 3))) . '</tr></table></div>';
    }
    return '';
}

/** Contact details as an array of strings. */
function cv_contacts(array $d): array
{
    $p = $d['personal'];
    return array_values(array_filter([$p['phone'], $p['email'], trim($p['city'] . ($p['address'] ? ', ' . $p['address'] : ''), ', '), $p['linkedin'], $p['website']]));
}

/**
 * Full HTML document for a CV. $cv: row from customer_cvs (or an unsaved array with the same keys).
 * $sample = true renders demonstration text (template previews in the gallery).
 */
function cv_render_html(array $cv, bool $sample = false): string
{
    $tpl = cv_template((int) $cv['template_id'], false) ?? db_row('SELECT * FROM cv_templates ORDER BY id LIMIT 1');
    $key = preg_match('/^[a-z0-9_]+$/', $tpl['template_key']) && is_file(PH_ROOT . '/core/cv_templates/' . $tpl['template_key'] . '.php') ? $tpl['template_key'] : 'professional';
    $d = $sample ? cv_sample_data() : (is_array($cv['data']) ? $cv['data'] : (json_decode((string) $cv['data'], true) ?: cv_default_data()));
    $d = array_replace_recursive(cv_default_data(), $d);
    $order = cv_clean_order($cv['section_order'] ?? []);
    $accent = valid_color($cv['accent_color'] ?? null, $tpl['default_color']);
    $photo = null;
    if ((int) $tpl['supports_photo'] && !empty($cv['photo_path']) && ($f = storage_file($cv['photo_path']))) {
        $photo = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($f));
    } elseif ($sample && (int) $tpl['supports_photo']) {
        $photo = null;
    }
    $dir = ($cv['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
    $lang = preg_match('/^[a-z]{2}$/', (string) ($cv['language'] ?? '')) ? $cv['language'] : 'en';
    $name = $d['personal']['full_name'] !== '' ? $d['personal']['full_name'] : 'Your Name';
    ob_start();
    include PH_ROOT . '/core/cv_templates/' . $key . '.php';
    $body = (string) ob_get_clean();
    return '<!doctype html><html lang="' . $lang . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($name) . ' — CV</title><style>'
        . '@page { size: A4; margin: 14mm 14mm 16mm; } * { box-sizing: border-box; } body { margin: 0; font-size: 10pt; line-height: 1.45; color: #1f2937; }'
        . 'h1, h2, h3, p { margin: 0; } ul { margin: 3px 0 0; padding-left: 16px; } li { margin: 1px 0; } table { border-collapse: collapse; width: 100%; }'
        . '.entry { margin: 0 0 8px; page-break-inside: avoid; } .entry-head td { padding: 0; vertical-align: top; } .entry-date { text-align: right; white-space: nowrap; width: 30%; color: #6b7280; font-size: 9pt; }'
        . '.entry-sub, .muted { color: #6b7280; font-size: 9pt; } .entry-text { margin-top: 2px; } .sec { margin-bottom: 12px; } ul.plain { list-style: none; padding: 0; }'
        . '.chip { display: inline-block; padding: 1px 7px; margin: 0 3px 4px 0; border-radius: 9px; font-size: 9pt; } .refs td { width: 33%; vertical-align: top; padding-right: 8px; font-size: 9pt; }'
        . '@media screen { body { background: #e5e7eb; } .page { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); } }'
        . '@media screen and (max-width: 820px) { .page { width: 100%; min-height: 0; margin: 0; padding: 18px; box-shadow: none; } }'
        . '@media print { .page { padding: 0; } }</style></head><body><div class="page">' . $body . '</div></body></html>';
}

function cv_pdf(array $cv): string
{
    $html = cv_render_html($cv);
    $html = str_replace('<div class="page">', '<div class="page pdf">', $html);
    $opt = new Options();
    $opt->set('isRemoteEnabled', false);
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->setChroot([PH_ROOT]);
    $pdf = new Dompdf($opt);
    $pdf->loadHtml(preg_replace('/@media screen[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $html), 'UTF-8');
    $pdf->setPaper('A4');
    $pdf->render();
    return (string) $pdf->output();
}

function cv_sample_data(): array
{
    return [
        'personal' => ['full_name' => 'Ayesha Khan', 'title' => 'Business Analyst', 'phone' => '+92 300 1234567', 'email' => 'ayesha.khan@email.com', 'city' => 'Lahore', 'address' => '',
            'website' => '', 'linkedin' => 'linkedin.com/in/ayeshakhan', 'summary' => 'Detail-oriented business analyst with 4 years of experience turning data into clear decisions for retail and telecom teams.'],
        'experience' => [
            ['role' => 'Business Analyst', 'company' => 'Retail Group Pvt Ltd', 'location' => 'Lahore', 'start' => '2022', 'end' => '', 'current' => true,
                'details' => "Built weekly sales dashboards used by 40 store managers\nCut reporting time by 60% by automating Excel reports"],
            ['role' => 'Junior Analyst', 'company' => 'Telecom Services', 'location' => 'Islamabad', 'start' => '2020', 'end' => '2022', 'current' => false,
                'details' => "Analysed customer churn and proposed retention offers"],
        ],
        'education' => [['degree' => 'BBA (Hons)', 'institute' => 'University of the Punjab', 'location' => 'Lahore', 'start' => '2016', 'end' => '2020', 'grade' => '3.6 CGPA', 'details' => '']],
        'skills' => ['Excel', 'SQL', 'Power BI', 'Communication', 'Problem solving'],
        'languages' => [['name' => 'English', 'level' => 'Fluent'], ['name' => 'Urdu', 'level' => 'Native']],
        'certifications' => [['name' => 'Google Data Analytics', 'issuer' => 'Coursera', 'year' => '2023']],
        'projects' => [['name' => 'Store performance dashboard', 'link' => '', 'details' => 'Power BI dashboard comparing 25 stores.']],
        'achievements' => ['Employee of the Quarter, Q2 2023'], 'references' => [], 'references_on_request' => true,
    ];
}
