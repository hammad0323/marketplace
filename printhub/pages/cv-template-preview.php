<?php
/** Sample rendering of a CV template (used for the template gallery when no preview image is uploaded). */
$tpl = cv_template((int) $params['id'], false);
if (!$tpl) {
    http_response_code(404);
    exit;
}
header('Cache-Control: public, max-age=3600');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
echo cv_render_html(['template_id' => $tpl['id'], 'accent_color' => $tpl['default_color'], 'data' => [], 'section_order' => [], 'photo_path' => null], true);
