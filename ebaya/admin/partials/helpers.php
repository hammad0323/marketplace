<?php
/** Admin form & table helpers. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

/** Admin menu: [label, file, icon, permission] grouped. */
function admin_menu(): array
{
    return [
        'Overview' => [['Dashboard', 'index', 'speedometer2', 'dashboard.view']],
        'Sales' => [
            ['Orders', 'orders', 'bag-check', 'orders.view'],
            ['Customers', 'customers', 'people', 'customers.manage'],
            ['Coupons', 'coupons', 'ticket-perforated', 'coupons.manage'],
            ['Payment log', 'payments', 'credit-card', 'payments.manage'],
        ],
        'Catalogue' => [
            ['Products', 'products', 'box-seam', 'products.manage'],
            ['Categories', 'categories', 'diagram-3', 'categories.manage'],
            ['Collections', 'collections', 'collection', 'collections.manage'],
            ['Inventory', 'inventory', 'boxes', 'inventory.manage'],
            ['Sizes, colours & attributes', 'attributes', 'rulers', 'attributes.manage'],
            ['Reviews & testimonials', 'reviews', 'star', 'reviews.manage'],
        ],
        'Storefront' => [
            ['Homepage builder', 'homepage', 'layout-text-window', 'homepage.manage'],
            ['Hero slides', 'slides', 'images', 'homepage.manage'],
            ['Navigation', 'navigation', 'menu-button-wide', 'homepage.manage'],
            ['Content pages', 'pages', 'file-earmark-text', 'content.manage'],
            ['Theme', 'theme', 'palette', 'theme.manage'],
            ['Newsletter & messages', 'newsletter', 'envelope-paper', 'newsletter.manage'],
        ],
        'Settings' => [
            ['Store settings', 'settings', 'gear', 'settings.manage'],
            ['Shipping & delivery', 'shipping', 'truck', 'shipping.manage'],
            ['Payment methods', 'payment-methods', 'wallet2', 'payments.manage'],
            ['SEO & redirects', 'seo', 'search', 'seo.manage'],
            ['Admin users & roles', 'admins', 'shield-lock', 'admins.manage'],
            ['Audit log', 'audit-log', 'journal-text', 'audit.view'],
        ],
    ];
}

function f_text(string $name, string $label, $value = '', array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $type = $o['type'] ?? 'text';
    $attrs = '';
    foreach (['maxlength', 'min', 'max', 'step', 'placeholder', 'pattern'] as $a) if (isset($o[$a])) $attrs .= " $a=\"" . e($o[$a]) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    if (!empty($o['readonly'])) $attrs .= ' readonly';
    $h = '<div class="' . e($o['wrap'] ?? 'mb-3') . '"><label class="form-label" for="' . $id . '">' . e($label) . (!empty($o['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    if ($type === 'textarea') {
        $h .= '<textarea class="form-control' . (!empty($o['rich']) ? ' rich-editor' : '') . '" id="' . $id . '" name="' . e($name) . '" rows="' . (int)($o['rows'] ?? 3) . '"' . $attrs . '>' . e($value) . '</textarea>';
    } elseif ($type === 'color') {
        $h .= '<div class="input-group"><input type="color" class="form-control form-control-color" value="' . e($value ?: '#000000') . '" data-sync="#' . $id . '"><input class="form-control" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '" pattern="#[0-9A-Fa-f]{6}"' . $attrs . '></div>';
    } else {
        $h .= '<input class="form-control" type="' . e($type) . '" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>';
    }
    if (!empty($o['help'])) $h .= '<div class="form-text">' . e($o['help']) . '</div>';
    return $h . '</div>';
}

function f_select(string $name, string $label, array $options, $value = '', array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $multi = !empty($o['multiple']);
    $h = '<div class="' . e($o['wrap'] ?? 'mb-3') . '"><label class="form-label" for="' . $id . '">' . e($label) . '</label><select class="form-select' . ($multi ? ' select-multi' : '') . '" id="' . $id . '" name="' . e($name) . ($multi ? '[]" multiple size="' . (int)($o['size'] ?? 6) : '') . '"' . (!empty($o['required']) ? ' required' : '') . '>';
    if (isset($o['empty'])) $h .= '<option value="">' . e($o['empty']) . '</option>';
    $vals = array_map('strval', (array)$value);
    foreach ($options as $k => $v) {
        $h .= '<option value="' . e($k) . '"' . (in_array((string)$k, $vals, true) ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    $h .= '</select>';
    if (!empty($o['help'])) $h .= '<div class="form-text">' . e($o['help']) . '</div>';
    return $h . '</div>';
}

function f_toggle(string $name, string $label, $checked, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    return '<div class="' . e($o['wrap'] ?? 'mb-3') . '"><div class="form-check form-switch"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
        . '<label class="form-check-label" for="' . $id . '">' . e($label) . '</label></div>' . (!empty($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '') . '</div>';
}

/** Image upload with preview; posts file field $name and checkbox remove_$name. */
function f_image(string $name, string $label, ?string $current, array $o = []): string
{
    $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $h = '<div class="' . e($o['wrap'] ?? 'mb-3') . '"><label class="form-label" for="' . $id . '">' . e($label) . '</label><div class="img-field">';
    $h .= '<img src="' . e($current ? img_url($current) : url('assets/img/placeholder.svg')) . '" alt="" class="img-preview" data-preview-for="' . $id . '">';
    $h .= '<div class="flex-grow-1"><input class="form-control" type="file" id="' . $id . '" name="' . e($o['field'] ?? $name) . '" accept="image/jpeg,image/png,image/webp' . (!empty($o['ico']) ? ',image/x-icon' : '') . '">';
    if ($current) $h .= '<label class="form-check mt-2 small"><input class="form-check-input" type="checkbox" name="' . e($o['remove'] ?? 'remove_' . $name) . '" value="1"> <span class="form-check-label">Remove current image</span></label>';
    $h .= '<div class="form-text">' . e($o['help'] ?? 'JPG, PNG or WebP, up to 8 MB. Large images are resized automatically.') . '</div></div></div></div>';
    return $h;
}

/** Process an image field from f_image(): returns new path, '' for removed, or current. */
function f_image_value(string $field, ?string $current, string $dir, ?string $removeKey = null): ?string
{
    $new = upload_optional($field, $dir);
    if ($new) return $new;
    if (!empty($_POST[$removeKey ?? 'remove_' . $field])) return null;
    return $current;
}

function admin_pager(array $pg): string
{
    if ($pg['pages'] <= 1) return '';
    $h = '<nav><ul class="pagination pagination-sm">';
    for ($i = max(1, $pg['page'] - 4); $i <= min($pg['pages'], $pg['page'] + 4); $i++) {
        $h .= '<li class="page-item' . ($i === $pg['page'] ? ' active' : '') . '"><a class="page-link" href="' . e(query_with(['page' => $i])) . '">' . $i . '</a></li>';
    }
    return $h . '</ul></nav>';
}

function admin_post_guard(?string $perm = null): void
{
    if (!is_post()) return;
    csrf_check();
    if ($perm && !can($perm)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/** Unique slug within a table. */
function unique_slug(string $table, string $slug, int $exceptId = 0): string
{
    $base = $slug = slugify($slug);
    $i = 2;
    while (db_val("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $exceptId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function nn($v)
{
    return ($v === '' || $v === null) ? null : $v;
}

/** Render fields of a settings group (settings_schema()). */
function settings_group_form(string $group, bool $draftable = false): string
{
    [$label, $perm, , $fields] = settings_schema()[$group];
    $vals = settings_defaults();
    foreach (db_all('SELECT setting_key, setting_value, draft_value FROM site_settings WHERE setting_group = ?', [$group]) as $r) {
        $vals[$r['setting_key']] = ($draftable && $r['draft_value'] !== null) ? $r['draft_value'] : $r['setting_value'];
    }
    $h = '<div class="row">';
    foreach ($fields as $k => $f) {
        [$fl, $type, $def] = $f;
        $v = $vals[$k] ?? $def;
        $n = 'set[' . $k . ']';
        $col = in_array($type, ['textarea', 'image'], true) ? 'col-md-12' : 'col-md-6';
        $h .= '<div class="' . $col . '">';
        $h .= match ($type) {
            'toggle' => f_toggle($n, $fl, $v),
            'select' => f_select($n, $fl, $f[3], $v),
            'image' => f_image('setimg_' . $k, $fl, $v ?: null, ['remove' => 'setremove[' . $k . ']', 'ico' => $k === 'favicon']),
            'textarea' => f_text($n, $fl, $v, ['type' => 'textarea', 'rows' => 3]),
            'color' => f_text($n, $fl, $v, ['type' => 'color']),
            'number' => f_text($n, $fl, $v, ['type' => 'number']),
            'secret' => f_text($n, $fl, '', ['type' => 'password', 'placeholder' => $v ? '•••••••• saved — leave blank to keep' : 'Not set']),
            default => f_text($n, $fl, $v),
        };
        $h .= '</div>';
    }
    return $h . '</div>';
}

/** Validate & save a settings group from POST. */
function settings_group_save(string $group, bool $draft = false): void
{
    $fields = settings_schema()[$group][3];
    foreach ($fields as $k => $f) {
        [$fl, $type, $def] = $f;
        $raw = $_POST['set'][$k] ?? null;
        switch ($type) {
            case 'toggle': $v = !empty($raw) ? '1' : '0'; break;
            case 'number': $v = is_numeric($raw) ? (string)max(0, (float)$raw) : (string)$def; break;
            case 'color': $v = is_string($raw) && v_hex($raw) ? strtoupper($raw) : (string)$def; break;
            case 'select': $v = is_string($raw) && array_key_exists($raw, $f[3]) ? $raw : (string)$def; break;
            case 'email': $v = is_string($raw) && ($raw === '' || v_email($raw)) ? trim($raw) : (string)$def; break;
            case 'url': $v = is_string($raw) ? clean_url($raw) : ''; break;
            case 'secret':
                if (!is_string($raw) || $raw === '') continue 2;
                $v = encrypt_secret($raw);
                break;
            case 'image':
                $cur = db_val('SELECT ' . ($draft ? 'COALESCE(draft_value, setting_value)' : 'setting_value') . ' FROM site_settings WHERE setting_key = ?', [$k]);
                $new = upload_optional('setimg_' . $k, 'branding');
                if ($new) $v = $new;
                elseif (!empty($_POST['setremove'][$k])) $v = '';
                else continue 2;
                break;
            case 'textarea': $v = is_string($raw) ? mb_substr(trim($raw), 0, 10000) : ''; break;
            default: $v = is_string($raw) ? mb_substr(trim(strip_tags($raw)), 0, 500) : '';
        }
        setting_set($k, $v, $group, $draft);
    }
}
