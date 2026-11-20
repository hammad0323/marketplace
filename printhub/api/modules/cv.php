<?php
/** CV generator APIs: templates, drafts (per customer session), photo, live preview HTML, PDF. */

function cv_payload(array $cv): array
{
    $tpl = cv_template((int) $cv['template_id'], false);
    return ['token' => $cv['public_token'], 'title' => $cv['title'], 'template_id' => (int) $cv['template_id'], 'template_name' => $tpl['name'] ?? '',
        'accent_color' => $cv['accent_color'], 'language' => $cv['language'], 'direction' => $cv['direction'],
        'data' => array_replace_recursive(cv_default_data(), json_decode((string) $cv['data'], true) ?: []), 'section_order' => cv_clean_order($cv['section_order']),
        'photo_url' => $cv['photo_path'] ? signed_file_url('cvphoto', (int) $cv['id'], 3600) : null,
        'preview_url' => url('cv/' . $cv['public_token'] . '/preview'), 'pdf_url' => url('cv/' . $cv['public_token'] . '/pdf'),
        'updated_at' => $cv['updated_at'] ?? $cv['created_at']];
}

function api_routes_cv(): array
{
    $find = function (string $token): array {
        $cv = cv_by_token($token, api_customer());
        if (!$cv) {
            api_fail('CV not found on this device.', 404);
        }
        return $cv;
    };
    return [
        ['GET', '/cv/templates', function () {
            api_ok(['templates' => cv_templates_public(), 'sections' => cv_section_labels(), 'default_data' => cv_default_data()]);
        }],
        ['GET', '/cv', function () {
            $s = api_customer();
            api_ok(['cvs' => array_map('cv_payload', db_all('SELECT * FROM customer_cvs WHERE session_id = ? AND deleted_at IS NULL ORDER BY COALESCE(updated_at, created_at) DESC', [(int) $s['id']]))]);
        }],
        ['POST', '/cv', function () {
            $s = api_customer();
            api_rate('cv-save:' . $s['id'], 120, 600);
            $r = cv_save($s, request_data());
            is_string($r) ? api_fail($r) : api_ok(cv_payload($r), 'CV saved', 201);
        }],
        ['POST', '/cv/preview', function () {
            $s = api_customer();
            api_rate('cv-preview:' . $s['id'], 300, 600);
            $in = request_data();
            $tpl = cv_template((int) ($in['template_id'] ?? 0));
            if (!$tpl) api_fail('Choose a template.');
            $existing = !empty($in['token']) ? cv_by_token((string) $in['token'], $s) : null;
            $html = cv_render_html(['template_id' => (int) $tpl['id'], 'accent_color' => valid_color($in['accent_color'] ?? null, $tpl['default_color']),
                'data' => cv_clean(is_array($in['data'] ?? null) ? $in['data'] : []), 'section_order' => cv_clean_order($in['section_order'] ?? []), 'photo_path' => $existing['photo_path'] ?? null]);
            api_ok(['html' => $html]);
        }],
        ['GET', '/cv/([a-f0-9]{32})', function ($token) use ($find) {
            api_ok(cv_payload($find($token)));
        }],
        ['POST', '/cv/([a-f0-9]{32})', function ($token) use ($find) {
            $cv = $find($token);
            api_rate('cv-save:' . $cv['session_id'], 120, 600);
            $r = cv_save(api_customer(), request_data(), $cv);
            is_string($r) ? api_fail($r) : api_ok(cv_payload($r), 'CV saved');
        }],
        ['DELETE', '/cv/([a-f0-9]{32})', function ($token) use ($find) {
            $cv = $find($token);
            storage_delete($cv['photo_path']);
            db_update('customer_cvs', ['deleted_at' => now(), 'photo_path' => null], 'id = ?', [(int) $cv['id']]);
            api_ok(null, 'CV deleted');
        }],
        ['POST', '/cv/([a-f0-9]{32})/photo', function ($token) use ($find) {
            $cv = $find($token);
            try {
                $path = cv_photo_store($_FILES['photo'] ?? []);
            } catch (RuntimeException $e) {
                api_fail($e->getMessage());
            }
            storage_delete($cv['photo_path']);
            db_update('customer_cvs', ['photo_path' => $path], 'id = ?', [(int) $cv['id']]);
            api_ok(cv_payload(db_row('SELECT * FROM customer_cvs WHERE id = ?', [(int) $cv['id']])), 'Photo added');
        }],
        ['DELETE', '/cv/([a-f0-9]{32})/photo', function ($token) use ($find) {
            $cv = $find($token);
            storage_delete($cv['photo_path']);
            db_update('customer_cvs', ['photo_path' => null], 'id = ?', [(int) $cv['id']]);
            api_ok(cv_payload(db_row('SELECT * FROM customer_cvs WHERE id = ?', [(int) $cv['id']])), 'Photo removed');
        }],
        ['GET', '/cv/([a-f0-9]{32})/pdf', function ($token) use ($find) {
            $cv = $find($token);
            api_rate('cv-pdf:' . $cv['session_id'], 60, 600);
            $name = preg_replace('/[^A-Za-z0-9]+/', '-', json_decode($cv['data'], true)['personal']['full_name'] ?? 'cv') ?: 'cv';
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . trim($name, '-') . '-CV.pdf"');
            echo cv_pdf($cv);
            exit;
        }],
        ['GET', '/cv/([a-f0-9]{32})/html', function ($token) use ($find) {
            header('Content-Type: text/html; charset=utf-8');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
            echo cv_render_html($find($token));
            exit;
        }],
    ];
}
