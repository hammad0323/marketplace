<?php
/**
 * Which templates a user may use.
 *   GET  ?user_id=                                → {mode, category_ids, template_ids}
 *   POST {user_id, mode: all|selected, category_ids?: [], template_ids?: []}
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$admin = require_admin();
$in = input();
$u = admin_user_row(v_int($in, 'user_id', true, 1));
if (request_method() === 'POST') {
    $mode = v_enum($in, 'mode', ['all', 'selected'], true);
    db_begin();
    set_template_access((int)$u['user_id'], $mode, v_array($in, 'category_ids') ?? [], v_array($in, 'template_ids') ?? []);
    log_activity((int)$admin['user_id'], 'admin.user.template_access', 'user', (int)$u['user_id'], $mode === 'all' ? 'All templates' : 'Restricted templates');
    db_commit();
    ok(template_access((int)$u['user_id']), $mode === 'all' ? 'User can use all templates' : 'Template access updated');
}
ok(template_access((int)$u['user_id']));
