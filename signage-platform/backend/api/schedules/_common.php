<?php
function schedule_from_input(array $in, ?array $cur = null): array
{
    $days = null;
    if (array_key_exists('days_of_week', $in) || array_key_exists('days_of_week_json', $in)) {
        $raw = $in['days_of_week'] ?? $in['days_of_week_json'];
        if ($raw !== null && $raw !== '') {
            $arr = is_string($raw) ? json_decode($raw, true) : $raw;
            if (!is_array($arr)) v_error('days_of_week', 'days_of_week must be a list of 1-7');
            $days = array_values(array_unique(array_map('intval', $arr)));
            sort($days);
            foreach ($days as $d) if ($d < 1 || $d > 7) v_error('days_of_week', 'Days must be 1 (Mon) … 7 (Sun)');
            if (!$days) v_error('days_of_week', 'Pick at least one day');
            if (count($days) === 7) $days = null;   // every day
        }
    } elseif ($cur) {
        $days = jdecode($cur['days_of_week_json']);
    }
    $out = [
        'schedule_name' => array_key_exists('schedule_name', $in) ? v_str($in, 'schedule_name', false, 150) : ($cur['schedule_name'] ?? null),
        'schedule_type' => v_enum($in, 'schedule_type', ['recurring', 'one_time', 'special_event'], false, $cur['schedule_type'] ?? 'recurring'),
        'start_date' => array_key_exists('start_date', $in) ? v_date($in, 'start_date') : ($cur['start_date'] ?? null),
        'end_date' => array_key_exists('end_date', $in) ? v_date($in, 'end_date') : ($cur['end_date'] ?? null),
        'start_time' => array_key_exists('start_time', $in) ? v_time($in, 'start_time') : ($cur['start_time'] ?? null),
        'end_time' => array_key_exists('end_time', $in) ? v_time($in, 'end_time') : ($cur['end_time'] ?? null),
        'days' => $days,
        'timezone' => array_key_exists('timezone', $in) ? v_timezone($in, 'timezone') : ($cur['timezone'] ?? null),
        'priority' => v_int($in, 'priority', false, -1000, 1000, $cur ? (int)$cur['priority'] : 10),
        'status' => v_enum($in, 'status', ['active', 'inactive'], false, $cur['status'] ?? 'active'),
    ];
    if ($out['start_date'] && $out['end_date'] && $out['end_date'] < $out['start_date']) v_error('end_date', 'End date must be on or after the start date');
    if ($out['start_time'] && $out['end_time'] && $out['start_time'] === $out['end_time']) v_error('end_time', 'Start and end time cannot be the same');
    return $out;
}
