<?php
/** Shared helpers for single-widget endpoints. */
function widget_with_screen(int $widgetId, array $u): array
{
    $w = db_one('SELECT * FROM screen_widgets WHERE widget_id = ?', [$widgetId]);
    if (!$w) not_found('Widget');
    $s = owned_row('screens', 'screen_id', (int)$w['screen_id'], $u);
    return [$w, $s];
}
function touch_screen(int $screenId): void
{
    db_run('UPDATE screens SET layout_version = layout_version + 1, has_unpublished_changes = 1 WHERE screen_id = ?', [$screenId]);
}
function present_widget(array $w): array
{
    return shape_row($w, ['configuration_json'], ['position_x', 'position_y', 'width', 'height', 'rotation']);
}
function record_recent_widget(int $uid, string $key): void
{
    db_run('INSERT INTO user_recent_widgets (user_id, widget_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE use_count = use_count + 1, last_used_at = UTC_TIMESTAMP()', [$uid, $key]);
}
