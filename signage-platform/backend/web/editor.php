<?php
/** Screen editor (?screen_id=) or, for the super admin, template editor (?template_id= | ?template=new&orientation=). */
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
$isAdmin = $u['role_key'] === ROLE_SUPER_ADMIN;
$cfg = ['is_admin' => $isAdmin];
if (isset($_GET['template_id']) || isset($_GET['template'])) {
    if (!$isAdmin) { header('Location: ' . web_url('screens.php')); exit; }
    $cfg['mode'] = 'template';
    $cfg['template_id'] = isset($_GET['template_id']) ? (int)$_GET['template_id'] : null;
    $cfg['orientation'] = in_array($_GET['orientation'] ?? '', ['landscape', 'portrait', 'square'], true) ? $_GET['orientation'] : 'landscape';
    $title = 'Template editor';
} else {
    $cfg['mode'] = 'screen';
    $cfg['screen_id'] = (int)($_GET['screen_id'] ?? 0);
    if ($cfg['screen_id'] < 1) { header('Location: ' . web_url('screens.php')); exit; }
    $title = 'Screen editor';
}
web_page_start($title, $cfg['mode'] === 'template' ? 'admin-templates' : 'screens', $u, ['bare' => true, 'body_class' => 'editor-page', 'scripts' => []]);
?>
<div id="editor"></div>
<script>window.EDITOR = <?= json_encode($cfg) ?>;</script>
<script src="<?= h(web_base()) ?>/web/assets/editor.js?v=3"></script>
<?php web_page_end();
