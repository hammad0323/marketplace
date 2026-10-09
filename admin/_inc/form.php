<?php
/**
 * Small form-rendering helpers for the admin panel (Bootstrap 5 markup).
 */

function f_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $out .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    return $out;
}

function f_help(?string $help): string
{
    return $help ? '<div class="form-text">' . e($help) . '</div>' : '';
}

function f_text(string $name, string $label, $value = '', array $attrs = [], ?string $help = null, string $type = 'text'): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" class="form-control" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . f_attrs($attrs) . '>'
        . f_help($help) . '</div>';
}

function f_number(string $name, string $label, $value = '', array $attrs = [], ?string $help = null): string
{
    return f_text($name, $label, $value, $attrs + ['step' => 'any'], $help, 'number');
}

function f_textarea(string $name, string $label, $value = '', array $attrs = [], ?string $help = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>'
        . '<textarea class="form-control" id="' . $id . '" name="' . e($name) . '"' . f_attrs($attrs + ['rows' => 4]) . '>' . e($value) . '</textarea>'
        . f_help($help) . '</div>';
}

function f_select(string $name, string $label, array $options, $value = '', array $attrs = [], ?string $help = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $multiple = !empty($attrs['multiple']);
    $values = $multiple ? array_map('strval', (array) $value) : [(string) $value];
    $html = '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><select class="form-select" id="' . $id . '" name="' . e($name) . ($multiple ? '[]' : '') . '"' . f_attrs($attrs) . '>';
    foreach ($options as $k => $v) {
        $html .= '<option value="' . e($k) . '"' . (in_array((string) $k, $values, true) ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html . '</select>' . f_help($help) . '</div>';
}

function f_check(string $name, string $label, $checked = false, ?string $help = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="form-check form-switch mb-3"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label>' . f_help($help) . '</div>';
}

function f_color(string $name, string $label, $value = '', ?string $help = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $v = valid_hex((string) $value) ? $value : '';
    return '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label><div class="input-group color-input">'
        . '<input type="color" class="form-control form-control-color" value="' . e($v ?: '#ffffff') . '" data-color-for="' . $id . '" aria-label="Pick colour">'
        . '<input type="text" class="form-control" id="' . $id . '" name="' . e($name) . '" value="' . e($v) . '" pattern="#[0-9A-Fa-f]{6}" placeholder="#RRGGBB">'
        . '</div>' . f_help($help) . '</div>';
}

function f_image(string $name, string $label, ?string $current, ?string $help = null): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $html = '<div class="mb-3"><label class="form-label" for="' . $id . '">' . e($label) . '</label>';
    if ($current) {
        $html .= '<div class="img-current"><img src="' . e(media_url($current)) . '" alt=""><label class="form-check small"><input type="checkbox" class="form-check-input" name="' . e($name) . '_remove" value="1"> Remove</label></div>';
    }
    $html .= '<input type="file" class="form-control" id="' . $id . '" name="' . e($name) . '" accept="image/jpeg,image/png,image/webp,image/gif" data-preview>';
    return $html . f_help($help ?? 'JPG, PNG, WebP or GIF, up to 5 MB.') . '</div>';
}

function f_submit(string $label = 'Save changes', string $class = 'btn btn-primary'): string
{
    return '<button type="submit" class="' . e($class) . '">' . e($label) . '</button>';
}

function admin_pager(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = max(1, $pg['page'] - 3); $i <= min($pg['pages'], $pg['page'] + 3); $i++) {
        $html .= '<li class="page-item' . ($i === $pg['page'] ? ' active' : '') . '"><a class="page-link" href="' . e(query_with(['page' => $i])) . '">' . $i . '</a></li>';
    }
    return $html . '</ul><small class="text-muted">' . (int) $pg['total'] . ' total</small></nav>';
}

function badge(string $status): string
{
    return '<span class="badge text-bg-' . e(status_color($status)) . '">' . e(status_label($status)) . '</span>';
}
