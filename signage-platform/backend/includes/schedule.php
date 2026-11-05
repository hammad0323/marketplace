<?php
/**
 * Schedule resolver — server mirror of the player's algorithm
 * (docs/ARCHITECTURE.md §4). Used by schedules/resolve.php for previews.
 */

function schedule_effective_priority(array $s): int
{
    $p = (int)$s['priority'];
    if (($s['schedule_type'] ?? '') === 'special_event') $p += 1000;
    if (($s['schedule_type'] ?? '') === 'one_time') $p += 500;
    return $p;
}

/** Is a schedule active at $at (DateTimeImmutable UTC)? $deviceTz fallback zone. Returns window length in minutes or null. */
function schedule_active(array $s, DateTimeImmutable $at, string $deviceTz): ?int
{
    if (($s['status'] ?? 'active') !== 'active') return null;
    $tzName = $s['timezone'] ?: $deviceTz;
    try { $tz = new DateTimeZone($tzName); } catch (Exception) { $tz = new DateTimeZone('UTC'); }
    $local = $at->setTimezone($tz);
    $date = $local->format('Y-m-d');
    if (!empty($s['start_date']) && $date < $s['start_date']) return null;
    if (!empty($s['end_date']) && $date > $s['end_date']) return null;

    $days = $s['days_of_week'] ?? jdecode($s['days_of_week_json'] ?? null);
    $start = $s['start_time'] ?: '00:00:00';
    $end = $s['end_time'] ?: '23:59:59';
    $now = $local->format('H:i:s');
    $dow = (int)$local->format('N');

    if ($end >= $start) {
        if ($now < $start || $now > $end) return null;
        if (is_array($days) && $days && !in_array($dow, array_map('intval', $days), true)) return null;
        return (int)((strtotime("1970-01-01 {$end} UTC") - strtotime("1970-01-01 {$start} UTC")) / 60);
    }
    // crosses midnight: active if now >= start (day = today) or now <= end (day = yesterday)
    if ($now >= $start) $dayCheck = $dow;
    elseif ($now <= $end) $dayCheck = $dow === 1 ? 7 : $dow - 1;
    else return null;
    if (is_array($days) && $days && !in_array($dayCheck, array_map('intval', $days), true)) return null;
    return (int)((86400 - strtotime("1970-01-01 {$start} UTC") + strtotime("1970-01-01 {$end} UTC")) / 60);
}

/**
 * Decide which playlist a device plays at $at.
 * $candidates: [['playlist_id', 'priority' (playlist), 'is_default', 'schedules' => [...]]]
 * Returns ['playlist_id' => ?int, 'reason' => string, 'schedule_id' => ?int]
 */
function resolve_playlist(array $candidates, DateTimeImmutable $at, string $deviceTz): array
{
    $best = null;
    foreach ($candidates as $c) {
        foreach ($c['schedules'] as $s) {
            $len = schedule_active($s, $at, $deviceTz);
            if ($len === null) continue;
            $key = [schedule_effective_priority($s), (int)$c['priority'], -$len, -(int)$s['schedule_id']];
            if ($best === null || $key > $best['key']) {
                $best = ['key' => $key, 'playlist_id' => (int)$c['playlist_id'], 'schedule_id' => (int)$s['schedule_id'],
                    'reason' => 'Scheduled: ' . ($s['schedule_name'] ?: 'schedule #' . $s['schedule_id'])];
            }
        }
    }
    if ($best) return ['playlist_id' => $best['playlist_id'], 'schedule_id' => $best['schedule_id'], 'reason' => $best['reason']];
    foreach ($candidates as $c) {
        if ($c['is_default']) return ['playlist_id' => (int)$c['playlist_id'], 'schedule_id' => null, 'reason' => 'Default playlist'];
    }
    // No default: an assigned playlist without any schedules plays as "always on".
    foreach ($candidates as $c) {
        if (!$c['schedules']) return ['playlist_id' => (int)$c['playlist_id'], 'schedule_id' => null, 'reason' => 'Unscheduled playlist'];
    }
    return ['playlist_id' => null, 'schedule_id' => null, 'reason' => 'Nothing scheduled — idle screen'];
}
