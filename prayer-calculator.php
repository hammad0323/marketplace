<?php
/**
 * Manual prayer time calculator — pure astronomical math, no external
 * API of any kind. Given a date, a location (lat/lng/elevation), a
 * timezone offset and a set of angle/method parameters, this works out
 * the sun's position for that day and derives each prayer time from the
 * angle the sun sits below the horizon (the same approach published
 * astronomy references and every major prayer-time authority use).
 *
 * Everything here is a plain function, prefixed pt_ to avoid colliding
 * with the site's mp_ helpers. Required once by prayer-times.php.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

// =====================================================================
// Small trig helpers — everything here works in degrees, PHP's math
// functions work in radians, so every angle crosses this boundary.
// =====================================================================

function pt_dtr(float $deg): float
{
    return $deg * M_PI / 180;
}

function pt_rtd(float $rad): float
{
    return $rad * 180 / M_PI;
}

function pt_dsin(float $deg): float { return sin(pt_dtr($deg)); }
function pt_dcos(float $deg): float { return cos(pt_dtr($deg)); }
function pt_dtan(float $deg): float { return tan(pt_dtr($deg)); }
function pt_darcsin(float $x): float { return pt_rtd(asin(max(-1, min(1, $x)))); }
function pt_darccos(float $x): float { return pt_rtd(acos(max(-1, min(1, $x)))); }
function pt_darccot(float $x): float { return pt_rtd(atan2(1, $x)); }

/** Wraps an hour value into the 0-24 range. */
function pt_fix_hour(float $hour): float
{
    $hour = fmod($hour, 24);
    return $hour < 0 ? $hour + 24 : $hour;
}

/** Wraps a degree value into the 0-360 range. */
function pt_fix_angle(float $angle): float
{
    $angle = fmod($angle, 360);
    return $angle < 0 ? $angle + 360 : $angle;
}

/** Forward difference from $a to $b on a 24h clock (always 0-24). */
function pt_time_diff(float $a, float $b): float
{
    return pt_fix_hour($b - $a);
}

// =====================================================================
// Sun position
// =====================================================================

/**
 * Julian Day Number at 00:00 UT for a Gregorian calendar date.
 * Standard algorithm (Meeus, "Astronomical Algorithms").
 */
function pt_julian_date(int $year, int $month, int $day): float
{
    if ($month <= 2) {
        $year -= 1;
        $month += 12;
    }

    $a = floor($year / 100);
    $b = 2 - $a + floor($a / 4);

    return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $b - 1524.5;
}

/**
 * Low-precision solar coordinates (accurate to well under a minute of
 * prayer-time purposes) for a given Julian Day instant. Returns the
 * sun's declination and the equation of time, both in degrees/hours as
 * noted, which is all the hour-angle formula below needs.
 */
function pt_sun_position(float $jd): array
{
    $d = $jd - 2451545.0;

    $g = pt_fix_angle(357.529 + 0.98560028 * $d);   // mean anomaly
    $q = pt_fix_angle(280.459 + 0.98564736 * $d);   // mean longitude
    $l = pt_fix_angle($q + 1.915 * pt_dsin($g) + 0.020 * pt_dsin(2 * $g)); // apparent longitude

    $e = 23.439 - 0.00000036 * $d; // obliquity of the ecliptic

    $ra = pt_rtd(atan2(pt_dcos($e) * pt_dsin($l), pt_dcos($l))) / 15; // right ascension, hours
    $eqt = $q / 15 - pt_fix_hour($ra);                                // equation of time, hours
    $decl = pt_darcsin(pt_dsin($e) * pt_dsin($l));                    // declination, degrees

    return ['declination' => $decl, 'equation' => $eqt];
}

/**
 * The hour angle (in hours from local solar noon) at which the sun sits
 * at $angle degrees below the horizon, for a place at latitude $lat when
 * the sun's declination is $decl. Returns null if the sun never reaches
 * that angle on that day at that latitude (permanent day/night case).
 */
function pt_hour_angle(float $angle, float $lat, float $decl): ?float
{
    $denominator = pt_dcos($lat) * pt_dcos($decl);
    if ($denominator == 0.0) {
        return null;
    }

    $ratio = (-pt_dsin($angle) - pt_dsin($lat) * pt_dsin($decl)) / $denominator;
    if ($ratio > 1 || $ratio < -1) {
        return null;
    }

    return pt_darccos($ratio) / 15;
}

// =====================================================================
// Preset calculation methods. Each defines the twilight angles a
// particular authority publishes. "Custom" lets every value be typed in
// by hand. These are starting points the form pre-fills — every angle
// stays fully editable regardless of which method is picked.
// =====================================================================

function pt_methods(): array
{
    return [
        'mwl' => [
            'label' => 'Muslim World League',
            'fajr_angle' => 18.0, 'isha_angle' => 17.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'isna' => [
            'label' => 'Islamic Society of North America (ISNA)',
            'fajr_angle' => 15.0, 'isha_angle' => 15.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'egypt' => [
            'label' => 'Egyptian General Authority of Survey',
            'fajr_angle' => 19.5, 'isha_angle' => 17.5, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'karachi' => [
            'label' => 'University of Islamic Sciences, Karachi',
            'fajr_angle' => 18.0, 'isha_angle' => 18.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'makkah' => [
            'label' => 'Umm al-Qura University, Makkah',
            'fajr_angle' => 18.5, 'isha_angle' => null, 'isha_minutes' => 90,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'tehran' => [
            'label' => 'Institute of Geophysics, University of Tehran',
            'fajr_angle' => 17.7, 'isha_angle' => 14.0, 'isha_minutes' => null,
            'maghrib_angle' => 4.5, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'jafari' => [
            'label' => 'Shia Ithna Ashari, Jafari (Leva Institute)',
            'fajr_angle' => 16.0, 'isha_angle' => 14.0, 'isha_minutes' => null,
            'maghrib_angle' => 4.0, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'gulf' => [
            'label' => 'Gulf Region',
            'fajr_angle' => 19.5, 'isha_angle' => null, 'isha_minutes' => 90,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'kuwait' => [
            'label' => 'Kuwait',
            'fajr_angle' => 18.0, 'isha_angle' => 17.5, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'qatar' => [
            'label' => 'Qatar',
            'fajr_angle' => 18.0, 'isha_angle' => null, 'isha_minutes' => 90,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'singapore' => [
            'label' => 'Singapore (MUIS)',
            'fajr_angle' => 20.0, 'isha_angle' => 18.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'france' => [
            'label' => 'France (UOIF)',
            'fajr_angle' => 12.0, 'isha_angle' => 12.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'turkey' => [
            'label' => 'Turkey (Diyanet)',
            'fajr_angle' => 18.0, 'isha_angle' => 17.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'russia' => [
            'label' => 'Russia (Spiritual Administration of Muslims)',
            'fajr_angle' => 16.0, 'isha_angle' => 15.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'moonsighting' => [
            'label' => 'Moonsighting Committee Worldwide (angle approximation)',
            'fajr_angle' => 18.0, 'isha_angle' => 18.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
        'custom' => [
            'label' => 'Custom — I will set every angle myself',
            'fajr_angle' => 18.0, 'isha_angle' => 18.0, 'isha_minutes' => null,
            'maghrib_angle' => 0.833, 'maghrib_minutes' => null, 'asr_factor' => 1,
        ],
    ];
}

// =====================================================================
// Main entry point
// =====================================================================

/**
 * Computes every prayer time for one day at one location.
 *
 * $p (all floats/ints/strings, already validated by the caller):
 *   year, month, day
 *   lat, lng                degrees, lng East-positive
 *   elevation                metres above sea level
 *   timezone, dst             hours from UTC; dst adds on top
 *   fajr_angle                degrees below horizon
 *   isha_angle | isha_minutes  degrees below horizon, OR fixed minutes after Maghrib
 *   maghrib_angle | maghrib_minutes  degrees below horizon (0.833 = standard sunset), OR fixed minutes after sunset
 *   asr_factor                1 = Standard (Shafi'i/Maliki/Hanbali), 2 = Hanafi
 *   imsak_minutes              minutes before Fajr
 *   high_lat_rule              'none' | 'angle_based' | 'one_seventh' | 'middle_of_night'
 *
 * Returns ['times' => [...], 'warnings' => [...]] where each time is
 * either a float hour (0-24, local clock) or null if it genuinely
 * cannot occur (permanent day/night at that latitude with 'none' rule).
 */
function pt_calculate(array $p): array
{
    $lat = $p['lat'];
    $lng = $p['lng'];
    $warnings = [];

    $jd0 = pt_julian_date($p['year'], $p['month'], $p['day']);

    // Initial guesses (hours) for when in the day to sample the sun's
    // position. Declination and the equation of time barely move over a
    // few hours, so a sensible guess is all a single pass needs.
    $guess = [
        'imsak' => 5, 'fajr' => 5, 'sunrise' => 6, 'noon' => 12,
        'asr' => 13, 'sunset' => 18, 'maghrib' => 18, 'isha' => 18,
    ];

    $baseNoon = function (float $t) use ($jd0): float {
        $eqt = pt_sun_position($jd0 + $t / 24)['equation'];
        return pt_fix_hour(12 - $eqt);
    };

    // Local-mean-solar-time offset from a sun angle, measured from that
    // day's base (pre-timezone) solar noon. $ccw = before noon.
    $angleTime = function (float $angle, float $t, bool $ccw) use ($jd0, $lat, $baseNoon): ?float {
        $decl = pt_sun_position($jd0 + $t / 24)['declination'];
        $ha = pt_hour_angle($angle, $lat, $decl);
        if ($ha === null) {
            return null;
        }
        $noon = $baseNoon($t);
        return $ccw ? $noon - $ha : $noon + $ha;
    };

    $asrTime = function (float $factor, float $t) use ($jd0, $lat, $angleTime): ?float {
        $decl = pt_sun_position($jd0 + $t / 24)['declination'];
        $angle = -pt_darccot($factor + pt_dtan(abs($lat - $decl)));
        return $angleTime($angle, $t, false);
    };

    // Horizon dip from elevation (metres above sea level pushes the
    // visible horizon down) — applies to sunrise/sunset and to an
    // angle-based Maghrib, since standard Maghrib angle (0.833°) IS
    // sunset. Not applied to Fajr/Isha twilight angles, which are about
    // atmospheric scattering rather than the visible horizon.
    $horizonDip = 0.0347 * sqrt(max(0.0, $p['elevation']));
    $riseSetAngle = 0.833 + $horizonDip;

    $dhuhr = $baseNoon($guess['noon']);
    $sunrise = $angleTime($riseSetAngle, $guess['sunrise'], true);
    $sunset = $angleTime($riseSetAngle, $guess['sunset'], false);
    $fajr = $angleTime($p['fajr_angle'], $guess['fajr'], true);
    $asr = $asrTime($p['asr_factor'], $guess['asr']);

    $maghrib = $p['maghrib_minutes'] !== null
        ? ($sunset !== null ? $sunset + $p['maghrib_minutes'] / 60 : null)
        : $angleTime($p['maghrib_angle'] + $horizonDip, $guess['maghrib'], false);

    $isha = $p['isha_minutes'] !== null
        ? ($maghrib !== null ? $maghrib + $p['isha_minutes'] / 60 : null)
        : $angleTime($p['isha_angle'], $guess['isha'], false);

    $imsak = $fajr !== null ? $fajr - $p['imsak_minutes'] / 60 : null;

    // High-latitude fallback: past roughly the polar circles, or during
    // long summer/winter days approaching them, the sun may never reach
    // the Fajr/Isha angle at all. Clamp to a fraction of the night
    // instead of leaving the time undefined.
    if ($p['high_lat_rule'] !== 'none' && $sunrise !== null && $sunset !== null) {
        $night = pt_time_diff($sunset, $sunrise); // sunset today -> sunrise tomorrow, approximated

        $portionOf = function (float $angle) use ($p, $night): float {
            switch ($p['high_lat_rule']) {
                case 'angle_based':
                    $portion = $angle / 60;
                    break;
                case 'one_seventh':
                    $portion = 1 / 7;
                    break;
                default: // middle_of_night
                    $portion = 1 / 2;
            }
            return $portion * $night;
        };

        $clampBefore = function (?float $time, float $base, float $angle) use ($portionOf): float {
            $portion = $portionOf($angle);
            if ($time === null || pt_time_diff($time, $base) > $portion) {
                return $base - $portion;
            }
            return $time;
        };
        $clampAfter = function (?float $time, float $base, float $angle) use ($portionOf): float {
            $portion = $portionOf($angle);
            if ($time === null || pt_time_diff($base, $time) > $portion) {
                return $base + $portion;
            }
            return $time;
        };

        $fajr = $clampBefore($fajr, $sunrise, $p['fajr_angle']);
        $isha = $clampAfter($isha, $sunset, $p['isha_angle'] ?? 18.0);
        $maghrib = $clampAfter($maghrib, $sunset, $p['maghrib_angle'] ?? 0.833);
        $imsak = $fajr - $p['imsak_minutes'] / 60;
    } else {
        foreach (['fajr' => $fajr, 'sunrise' => $sunrise, 'sunset' => $sunset, 'maghrib' => $maghrib, 'isha' => $isha] as $name => $value) {
            if ($value === null) {
                $warnings[] = ucfirst($name) . ' does not occur at this latitude/date with the chosen angle (sun stays above or below the horizon all day). '
                    . ($p['high_lat_rule'] === 'none' ? 'Try a high-latitude adjustment rule.' : 'Even the high-latitude rule could not resolve it — this location is in polar day/night.');
            }
        }
    }

    // Islamic midnight — midpoint between Maghrib and the *next* day's
    // Fajr. We only compute a single day here, so the next Fajr is
    // approximated from today's Fajr time plus 24h (accurate to well
    // under a minute, since Fajr drifts slowly day to day).
    $midnight = ($maghrib !== null && $fajr !== null)
        ? pt_fix_hour($maghrib + pt_time_diff($maghrib, $fajr + 24) / 2)
        : null;

    // Shift everything from "local mean solar time referenced to this
    // day's base noon" into the requested civil clock: apply the
    // timezone/DST offset and the standard longitude correction.
    $shift = $p['timezone'] + $p['dst'] - $lng / 15;
    $apply = fn (?float $t) => $t === null ? null : pt_fix_hour($t + $shift);

    return [
        'times' => [
            'imsak' => $apply($imsak),
            'fajr' => $apply($fajr),
            'sunrise' => $apply($sunrise),
            'dhuhr' => $apply($dhuhr),
            'asr' => $apply($asr),
            'sunset' => $apply($sunset),
            'maghrib' => $apply($maghrib),
            'isha' => $apply($isha),
            'midnight' => $apply($midnight),
        ],
        'warnings' => $warnings,
    ];
}

/** Formats a 0-24 float hour as H:MM AM/PM (or 24h, if requested). */
function pt_format_time(?float $hour, bool $twentyFourHour = false): string
{
    if ($hour === null) {
        return '—';
    }

    $hour = pt_fix_hour($hour);
    $totalMinutes = (int) round($hour * 60);
    $totalMinutes %= 1440;
    $h = intdiv($totalMinutes, 60);
    $m = $totalMinutes % 60;

    if ($twentyFourHour) {
        return sprintf('%02d:%02d', $h, $m);
    }

    $period = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return sprintf('%d:%02d %s', $h12, $m, $period);
}
