<?php
/**
 * Layout document handling (see docs/ARCHITECTURE.md §6).
 * A layout = {schema_version, canvas, background, widgets[]}. Field names = DB columns.
 */

function widget_registry(): array
{
    static $reg = null;
    if ($reg === null) {
        $reg = [];
        foreach (db_all("SELECT widget_key, availability, status, is_premium, default_config_json FROM widget_types") as $r) {
            $r['default_config_json'] = jdecode($r['default_config_json'], []);
            $reg[$r['widget_key']] = $r;
        }
    }
    return $reg;
}

function canvas_for(string $orientation, ?int $w = null, ?int $h = null): array
{
    return match ($orientation) {
        'portrait' => ['portrait', 1080, 1920],
        'square'   => ['square', 1080, 1080],
        'custom'   => ['custom', max(16, min(16384, $w ?? 1920)), max(16, min(16384, $h ?? 1080))],
        default    => ['landscape', 1920, 1080],
    };
}

/** All media ids referenced by a widget configuration. */
function config_media_ids(array $cfg): array
{
    $ids = [];
    if (!empty($cfg['media_id']) && is_numeric($cfg['media_id'])) $ids[] = (int)$cfg['media_id'];
    foreach (['items', 'slides', 'videos'] as $listKey) {
        if (!empty($cfg[$listKey]) && is_array($cfg[$listKey])) {
            foreach ($cfg[$listKey] as $it) {
                if (is_array($it) && !empty($it['media_id']) && is_numeric($it['media_id'])) $ids[] = (int)$it['media_id'];
            }
        }
    }
    return array_values(array_unique($ids));
}

/** Media ids usable by this user (own, or global). Returns id => row. */
function accessible_media(int $userId, array $ids): array
{
    if (!$ids) return [];
    $rows = db_all('SELECT media_id, file_type, user_id, is_global FROM media_library WHERE deleted_at IS NULL AND media_id IN (' . placeholders($ids) . ') AND (user_id = ? OR is_global = 1)',
        array_merge($ids, [$userId]));
    $out = [];
    foreach ($rows as $r) $out[(int)$r['media_id']] = $r;
    return $out;
}

/**
 * Normalise + validate one widget from client/template input.
 * Returns the cleaned widget or null when $skipDisallowed and the widget is not allowed.
 */
function clean_widget(array $w, array $limits, bool $skipDisallowed, int $index): ?array
{
    $reg = widget_registry();
    $type = (string)($w['widget_type'] ?? '');
    if (!isset($reg[$type]) || $reg[$type]['status'] !== 'active') {
        if ($skipDisallowed) return null;
        fail("Widget #" . ($index + 1) . " has an unknown type '{$type}'", 400, 'VALIDATION');
    }
    if ($reg[$type]['availability'] === 'planned' || !widget_allowed($limits, $type)) {
        if ($skipDisallowed) return null;
        fail("The '{$type}' widget is not available on your plan", 403, 'WIDGET_NOT_ALLOWED');
    }
    $num = function ($k, $def, $min, $max) use ($w, $index) {
        $v = $w[$k] ?? $def;
        if (!is_numeric($v)) fail("Widget #" . ($index + 1) . ": {$k} must be numeric", 400, 'VALIDATION');
        return max($min, min($max, (float)$v));
    };
    $uid = (string)($w['widget_uid'] ?? '');
    if (!preg_match('/^[A-Za-z0-9\-]{8,36}$/', $uid)) $uid = uuid4();
    $cfg = $w['configuration'] ?? $w['configuration_json'] ?? [];
    if (is_string($cfg)) $cfg = jdecode($cfg, []);
    if (!is_array($cfg)) $cfg = [];
    $cfg = array_replace($reg[$type]['default_config_json'], $cfg);
    $json = jencode($cfg);
    if (strlen($json) > 200000) fail("Widget #" . ($index + 1) . " configuration is too large", 413, 'VALIDATION');

    return [
        'widget_uid'    => $uid,
        'widget_type'   => $type,
        'widget_name'   => mb_substr((string)($w['widget_name'] ?? ucfirst(str_replace('_', ' ', $type))), 0, 150),
        'position_x'    => round($num('position_x', 0, -1, 2), 6),
        'position_y'    => round($num('position_y', 0, -1, 2), 6),
        'width'         => round($num('width', 0.4, 0.001, 2), 6),
        'height'        => round($num('height', 0.3, 0.001, 2), 6),
        'rotation'      => round($num('rotation', 0, -360, 360), 2),
        'z_index'       => (int)$num('z_index', $index, -10000, 10000),
        'is_locked'     => !empty($w['is_locked']),
        'is_visible'    => !array_key_exists('is_visible', $w) || !empty($w['is_visible']),
        'configuration' => $cfg,
    ];
}

function clean_background(array $bg, int $userId): array
{
    $type = in_array($bg['type'] ?? 'color', BACKGROUND_TYPES, true) ? $bg['type'] : 'color';
    $config = is_array($bg['config'] ?? null) ? $bg['config'] : [];
    $mediaId = null;
    $value = isset($bg['value']) && is_scalar($bg['value']) ? mb_substr((string)$bg['value'], 0, 255) : null;
    if ($type === 'image' || $type === 'video') {
        $mediaId = (int)($bg['media_id'] ?? $value ?? 0);
        $m = accessible_media($userId, $mediaId ? [$mediaId] : []);
        if (!isset($m[$mediaId])) fail('The selected background media was not found', 400, 'VALIDATION');
        $isVideo = $m[$mediaId]['file_type'] === 'video';
        if (($type === 'video') !== $isVideo) fail('Background type does not match the selected media', 400, 'VALIDATION');
        $value = (string)$mediaId;
    } elseif ($type === 'color') {
        if ($value === null || !preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) $value = '#17191C';
    }
    return ['type' => $type, 'value' => $value, 'media_id' => $mediaId, 'config' => $config];
}

/**
 * Replace the whole layout of a screen. Caller must hold a transaction and
 * have verified ownership. Returns list of skipped widget types.
 */
function write_layout(array $screen, array $doc, array $limits, bool $skipDisallowed = false): array
{
    $sid = (int)$screen['screen_id'];
    $uid = (int)$screen['user_id'];
    $widgetsIn = $doc['widgets'] ?? [];
    if (!is_array($widgetsIn)) fail('widgets must be an array', 400, 'VALIDATION');
    if (count($widgetsIn) > 200) fail('A screen can contain at most 200 widgets', 400, 'VALIDATION');

    $skipped = [];
    $clean = [];
    $seen = [];
    foreach (array_values($widgetsIn) as $i => $w) {
        if (!is_array($w)) continue;
        $cw = clean_widget($w, $limits, $skipDisallowed, $i);
        if ($cw === null) { $skipped[] = $w['widget_type'] ?? '?'; continue; }
        if (isset($seen[$cw['widget_uid']])) $cw['widget_uid'] = uuid4();
        $seen[$cw['widget_uid']] = true;
        $clean[] = $cw;
    }

    // Media ownership for every reference.
    $allMedia = [];
    foreach ($clean as $cw) $allMedia = array_merge($allMedia, config_media_ids($cw['configuration']));
    $allMedia = array_values(array_unique($allMedia));
    $ok = accessible_media($uid, $allMedia);
    foreach ($allMedia as $mid) if (!isset($ok[$mid])) fail("Media #{$mid} is not in your library", 400, 'VALIDATION');

    // Background + canvas
    $sets = [];
    $params = [];
    if (isset($doc['background']) && is_array($doc['background'])) {
        $bg = clean_background($doc['background'], $uid);
        $sets[] = 'background_type = ?, background_value = ?, background_media_id = ?, background_config_json = ?';
        array_push($params, $bg['type'], $bg['value'], $bg['media_id'], jencode($bg['config']));
    }
    if (isset($doc['canvas']['orientation'])) {
        [$o, $cw, $ch] = canvas_for((string)$doc['canvas']['orientation'], (int)($doc['canvas']['width'] ?? 0), (int)($doc['canvas']['height'] ?? 0));
        $sets[] = 'orientation = ?, width = ?, height = ?';
        array_push($params, $o, $cw, $ch);
    }
    $sets[] = 'layout_version = layout_version + 1, has_unpublished_changes = 1';
    db_run('UPDATE screens SET ' . implode(', ', $sets) . ' WHERE screen_id = ?', array_merge($params, [$sid]));

    db_run('DELETE FROM screen_widgets WHERE screen_id = ?', [$sid]);
    foreach ($clean as $cw) {
        $wid = db_insert('INSERT INTO screen_widgets (widget_uid, screen_id, widget_type, widget_name, position_x, position_y, width, height, rotation, z_index, configuration_json, is_locked, is_visible)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cw['widget_uid'], $sid, $cw['widget_type'], $cw['widget_name'], $cw['position_x'], $cw['position_y'], $cw['width'], $cw['height'],
                $cw['rotation'], $cw['z_index'], jencode($cw['configuration']), $cw['is_locked'], $cw['is_visible']]);
        sync_widget_assets($wid, $cw['configuration']);
    }
    return array_values(array_unique($skipped));
}

function sync_widget_assets(int $widgetId, array $cfg): void
{
    db_run('DELETE FROM widget_assets WHERE widget_id = ?', [$widgetId]);
    $order = 0;
    if (!empty($cfg['media_id'])) {
        db_run('INSERT INTO widget_assets (widget_id, media_id, asset_order, duration) VALUES (?, ?, ?, ?)', [$widgetId, (int)$cfg['media_id'], $order++, null]);
    }
    foreach (['items', 'slides', 'videos'] as $k) {
        foreach (($cfg[$k] ?? []) as $it) {
            if (is_array($it) && !empty($it['media_id'])) {
                db_run('INSERT INTO widget_assets (widget_id, media_id, asset_order, duration) VALUES (?, ?, ?, ?)',
                    [$widgetId, (int)$it['media_id'], $order++, isset($it['duration']) && is_numeric($it['duration']) ? (int)$it['duration'] : null]);
            }
        }
    }
}

/** Current (draft) layout of a screen as a layout document. */
function screen_layout(array $screen): array
{
    $widgets = db_all('SELECT widget_id, widget_uid, widget_type, widget_name, position_x, position_y, width, height, rotation, z_index, configuration_json, is_locked, is_visible
                         FROM screen_widgets WHERE screen_id = ? AND status = "active" ORDER BY z_index, widget_id', [(int)$screen['screen_id']]);
    $out = [];
    foreach ($widgets as $w) {
        $out[] = [
            'widget_id' => (int)$w['widget_id'], 'widget_uid' => $w['widget_uid'], 'widget_type' => $w['widget_type'], 'widget_name' => $w['widget_name'],
            'position_x' => (float)$w['position_x'], 'position_y' => (float)$w['position_y'], 'width' => (float)$w['width'], 'height' => (float)$w['height'],
            'rotation' => (float)$w['rotation'], 'z_index' => (int)$w['z_index'], 'is_locked' => (bool)$w['is_locked'], 'is_visible' => (bool)$w['is_visible'],
            'configuration' => jdecode($w['configuration_json'], []),
        ];
    }
    return [
        'schema_version' => 1,
        'canvas' => ['orientation' => $screen['orientation'], 'width' => (int)$screen['width'], 'height' => (int)$screen['height']],
        'background' => [
            'type' => $screen['background_type'], 'value' => $screen['background_value'],
            'media_id' => $screen['background_media_id'] !== null ? (int)$screen['background_media_id'] : null,
            'config' => jdecode($screen['background_config_json'], []) ?: (object)[],
        ],
        'widgets' => $out,
    ];
}

/** Media rows (presented) referenced by a layout — used so clients can render without extra calls. */
function layout_media(array $layout): array
{
    $ids = [];
    if (!empty($layout['background']['media_id'])) $ids[] = (int)$layout['background']['media_id'];
    foreach ($layout['widgets'] as $w) $ids = array_merge($ids, config_media_ids($w['configuration'] ?? []));
    $ids = array_values(array_unique($ids));
    if (!$ids) return [];
    $rows = db_all('SELECT * FROM media_library WHERE media_id IN (' . placeholders($ids) . ')', $ids);
    $out = [];
    foreach ($rows as $r) $out[(string)$r['media_id']] = present_media($r);
    return $out;
}

function present_screen(array $s, bool $withLayout = false): array
{
    $out = [
        'screen_id' => (int)$s['screen_id'], 'screen_uid' => $s['screen_uid'], 'user_id' => (int)$s['user_id'],
        'template_id' => $s['template_id'], 'folder_id' => $s['folder_id'], 'screen_name' => $s['screen_name'],
        'description' => $s['description'], 'orientation' => $s['orientation'], 'width' => (int)$s['width'], 'height' => (int)$s['height'],
        'background_type' => $s['background_type'], 'background_value' => $s['background_value'],
        'background_media_id' => $s['background_media_id'], 'background_config_json' => jdecode($s['background_config_json'], []),
        'default_duration' => (int)$s['default_duration'], 'status' => $s['status'], 'layout_version' => (int)$s['layout_version'],
        'published_version' => $s['published_version'], 'has_unpublished_changes' => (bool)$s['has_unpublished_changes'],
        'is_favorite' => (bool)$s['is_favorite'], 'expires_at' => $s['expires_at'], 'created_at' => $s['created_at'],
        'updated_at' => $s['updated_at'], 'published_at' => $s['published_at'],
    ];
    if (isset($s['widget_count'])) $out['widget_count'] = (int)$s['widget_count'];
    if (isset($s['owner_name'])) $out['owner_name'] = $s['owner_name'];
    if ($withLayout) {
        $out['layout'] = screen_layout($s);
        $out['media'] = layout_media($out['layout']);
    }
    return $out;
}
