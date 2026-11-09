<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$uid = (int)$u['user_id'];
$in = input();
$where = ["t.status = 'active'", 't.deleted_at IS NULL', '(t.is_public = 1 OR t.created_by = ?)'];
$params = [$uid];
[$accessSql, $accessParams] = template_access_sql($u);
$where[] = $accessSql;
$params = array_merge($params, $accessParams);
if ($c = v_int($in, 'category_id', false, 1)) { $where[] = 't.category_id = ?'; $params[] = $c; }
if ($ck = v_str($in, 'category', false, 60)) { $where[] = 'c.category_key = ?'; $params[] = $ck; }
if ($o = v_enum($in, 'orientation', ORIENTATIONS)) { $where[] = 't.orientation = ?'; $params[] = $o; }
if ($q = v_str($in, 'q', false, 100)) { $where[] = '(t.template_name LIKE ? OR t.tags LIKE ?)'; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
if (v_bool($in, 'favorites', false)) $where[] = 'f.id IS NOT NULL';
if (v_bool($in, 'mine', false)) { $where[] = 't.created_by = ?'; $params[] = $uid; }
$limits = effective_limits($uid);
$canPremium = $limits['can_use_premium_templates'] || has_permission($u, 'templates.premium');
$rows = db_all('SELECT t.*, c.category_key, c.category_name, (f.id IS NOT NULL) AS is_favorite
                  FROM screen_templates t JOIN template_categories c USING (category_id)
             LEFT JOIN template_favorites f ON f.template_id = t.template_id AND f.user_id = ?
                 WHERE ' . implode(' AND ', $where) . ' ORDER BY (f.id IS NOT NULL) DESC, t.sort_order, t.template_id',
    array_merge([$uid], $params));
$out = array_map(function ($t) use ($canPremium) {
    $t = shape_row($t, ['template_json']);
    $t['is_favorite'] = (bool)$t['is_favorite'];
    $t['is_premium'] = (bool)$t['is_premium'];
    $t['is_locked'] = $t['is_premium'] && !$canPremium;
    $t['preview_image_url'] = $t['preview_image'] ? base_url() . '/uploads/' . $t['preview_image'] : null;
    $t['media'] = (object)template_media(is_array($t['template_json']) ? $t['template_json'] : []);
    return $t;
}, $rows);
ok($out);
