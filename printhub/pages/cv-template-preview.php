<?php
/** Sample rendering of a CV template (gallery thumbnails when no preview image is uploaded). ?thumb=1 renders a fixed A4 page for scaling. */
$tpl = cv_template((int) $params['id'], false);
if (!$tpl) {
    http_response_code(404);
    exit;
}
header('Cache-Control: public, max-age=3600');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
$html = cv_render_html(['template_id' => $tpl['id'], 'accent_color' => $tpl['default_color'], 'data' => [], 'section_order' => [], 'photo_path' => null], true);
echo str_replace('</head>', '<style>html,body{background:#fff!important;overflow:hidden;margin:0}.page{width:794px!important;min-height:1123px!important;margin:0!important;padding:53px!important;box-shadow:none!important}</style></head>', $html);
