<?php
/**
 * Alteration service: price list with estimated price ranges and turnaround, customer requests,
 * quotes and status tracking.
 */

function alteration_statuses(): array
{
    return [
        'new' => 'Request received', 'quoted' => 'Quote sent', 'confirmed' => 'Confirmed', 'received' => 'Garment received',
        'in_progress' => 'In progress', 'ready' => 'Ready for collection / delivery', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
}

function alteration_status_badge(string $s): string
{
    $map = ['new' => 'warning', 'quoted' => 'info', 'confirmed' => 'primary', 'received' => 'indigo', 'in_progress' => 'indigo', 'ready' => 'success', 'completed' => 'success', 'cancelled' => 'danger'];
    return '<span class="uc-status uc-status-' . ($map[$s] ?? 'secondary') . '">' . e(alteration_statuses()[$s] ?? $s) . '</span>';
}

function alteration_services(bool $activeOnly = true): array
{
    return db_all('SELECT * FROM alteration_services' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY FIELD(category, "Men", "Women", "Bridal", "General"), category, sort_order, garment, id');
}

/** Services grouped as [category => [garment => [service, ...]]] */
function alteration_services_grouped(): array
{
    $out = [];
    foreach (alteration_services() as $s) {
        $out[$s['category']][$s['garment']][] = $s;
    }
    return $out;
}

function alteration_price_range(float $min, float $max): string
{
    if ($max <= 0 && $min <= 0) {
        return 'Quote on inspection';
    }
    return $max > $min ? money($min) . ' – ' . money($max) : money($min);
}

function alteration_days_range(int $min, int $max): string
{
    if ($max <= 0) {
        return 'Same day';
    }
    return ($max > $min ? $min . '–' . $max : $max) . ' working day' . ($max === 1 ? '' : 's');
}

/**
 * Estimate for a list of [service_id => qty]. Items run in parallel, so the turnaround
 * is the longest single service, not the sum.
 */
function alteration_estimate(array $items, string $method = 'drop_off'): array
{
    $lines = [];
    $min = $max = 0.0;
    $dMin = $dMax = 0;
    foreach ($items as $sid => $qty) {
        $qty = max(1, min(10, (int) $qty));
        $s = db_row('SELECT * FROM alteration_services WHERE id = ? AND is_active = 1', [(int) $sid]);
        if (!$s) {
            continue;
        }
        $lines[] = ['service' => $s, 'qty' => $qty];
        $min += (float) $s['price_min'] * $qty;
        $max += (float) $s['price_max'] * $qty;
        $dMin = max($dMin, (int) $s['days_min']);
        $dMax = max($dMax, (int) $s['days_max']);
    }
    $pickup = $method === 'pickup' ? (float) setting('alteration_pickup_fee', 0) : 0.0;
    return ['lines' => $lines, 'min' => $min, 'max' => $max, 'pickup_fee' => $pickup, 'days_min' => $dMin, 'days_max' => $dMax,
        'ready_by' => $dMax ? delivery_estimate_label($dMin, $dMax) : null];
}

function alteration_generate_reference(): string
{
    do {
        $ref = 'ALT' . date('ymd') . random_int(1000, 9999);
    } while (db_val('SELECT 1 FROM alteration_requests WHERE reference = ?', [$ref]));
    return $ref;
}

function alteration_history_add(int $requestId, ?string $status, ?string $note, bool $visible = true, ?int $adminId = null): void
{
    db_insert('alteration_status_history', ['request_id' => $requestId, 'status' => $status, 'note' => $note, 'is_customer_visible' => $visible ? 1 : 0, 'admin_id' => $adminId]);
}

/** Request visible to the current visitor (owner customer, private link token, or created in this session). */
function alteration_for_viewer(string $ref, ?string $token): ?array
{
    $r = db_row('SELECT * FROM alteration_requests WHERE reference = ?', [$ref]);
    if (!$r) {
        return null;
    }
    if (($r['customer_id'] && (int) $r['customer_id'] === (int) customer_id()) || ($token && hash_equals($r['access_token'], $token))
        || in_array($ref, $_SESSION['my_alterations'] ?? [], true)) {
        return $r;
    }
    return null;
}

function alteration_link(array $r): string
{
    return url('alterations/request/' . $r['reference'] . '?t=' . $r['access_token']);
}

function mail_alteration_update(array $r, string $title, string $message): void
{
    if (empty($r['email'])) {
        return;
    }
    $html = mail_layout($title, '<p>Hi ' . e(strtok($r['name'], ' ')) . ',</p><p>' . nl2br(e($message)) . '</p>'
        . ($r['quoted_price'] !== null ? '<p>Quoted price: <strong>' . e(money($r['quoted_price'])) . '</strong>' . ($r['ready_date'] ? '<br>Ready by: <strong>' . e(format_date($r['ready_date'])) . '</strong>' : '') . '</p>' : '')
        . '<p><a href="' . e(alteration_link($r)) . '" style="background:#111;color:#fff;padding:12px 22px;text-decoration:none;display:inline-block">View your request</a></p>');
    send_mail($r['email'], $title . ' — ' . $r['reference'], $html);
}
