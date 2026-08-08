<?php
/**
 * Manual prayer time calculator. Pure PHP astronomical math (see
 * prayer-calculator.php) — no third-party prayer-time or geocoding API
 * involved. The visitor supplies their own coordinates, timezone and
 * calculation angles; a browser Geolocation/timezone lookup is offered
 * purely as a local convenience (no network request, no server involved).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/prayer-calculator.php';

$methods = pt_methods();

function pt_old(string $key, string $default = ''): string
{
    return $_GET[$key] ?? $default;
}

$result = null;
$errors = [];
$submitted = isset($_GET['calc']);

if ($submitted) {
    $dateInput = trim($_GET['date'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d', $dateInput);
    if (!$dt || $dt->format('Y-m-d') !== $dateInput) {
        $errors[] = 'Please enter a valid date (YYYY-MM-DD).';
    }

    $lat = filter_var($_GET['lat'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lat < -90 || $lat > 90) {
        $errors[] = 'Latitude must be a number between -90 and 90.';
    }

    $lng = filter_var($_GET['lng'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($lng === false || $lng < -180 || $lng > 180) {
        $errors[] = 'Longitude must be a number between -180 and 180.';
    }

    $elevation = filter_var($_GET['elevation'] ?? '0', FILTER_VALIDATE_FLOAT);
    if ($elevation === false || $elevation < 0 || $elevation > 9000) {
        $errors[] = 'Elevation must be a number between 0 and 9000 metres.';
    }

    $timezone = filter_var($_GET['timezone'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($timezone === false || $timezone < -12 || $timezone > 14) {
        $errors[] = 'Timezone offset must be a number between -12 and +14.';
    }

    $dst = !empty($_GET['dst']) ? 1 : 0;

    $method = $_GET['method'] ?? 'custom';
    if (!isset($methods[$method])) {
        $method = 'custom';
    }

    $fajrAngle = filter_var($_GET['fajr_angle'] ?? '', FILTER_VALIDATE_FLOAT);
    if ($fajrAngle === false || $fajrAngle < 0 || $fajrAngle > 30) {
        $errors[] = 'Fajr angle must be between 0 and 30 degrees.';
    }

    $ishaMode = ($_GET['isha_mode'] ?? 'angle') === 'minutes' ? 'minutes' : 'angle';
    $ishaAngle = null;
    $ishaMinutes = null;
    if ($ishaMode === 'minutes') {
        $ishaMinutes = filter_var($_GET['isha_minutes'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($ishaMinutes === false || $ishaMinutes < 0 || $ishaMinutes > 240) {
            $errors[] = 'Isha minutes-after-Maghrib must be between 0 and 240.';
        }
    } else {
        $ishaAngle = filter_var($_GET['isha_angle'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($ishaAngle === false || $ishaAngle < 0 || $ishaAngle > 30) {
            $errors[] = 'Isha angle must be between 0 and 30 degrees.';
        }
    }

    $maghribMode = ($_GET['maghrib_mode'] ?? 'angle') === 'minutes' ? 'minutes' : 'angle';
    $maghribAngle = null;
    $maghribMinutes = null;
    if ($maghribMode === 'minutes') {
        $maghribMinutes = filter_var($_GET['maghrib_minutes'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($maghribMinutes === false || $maghribMinutes < 0 || $maghribMinutes > 60) {
            $errors[] = 'Maghrib minutes-after-sunset must be between 0 and 60.';
        }
    } else {
        $maghribAngle = filter_var($_GET['maghrib_angle'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($maghribAngle === false || $maghribAngle < 0 || $maghribAngle > 10) {
            $errors[] = 'Maghrib angle must be between 0 and 10 degrees.';
        }
    }

    $asrFactor = filter_var($_GET['asr_factor'] ?? '1', FILTER_VALIDATE_FLOAT);
    if (!in_array($asrFactor, [1.0, 2.0], true)) {
        $asrFactor = 1.0;
    }

    $highLatRule = $_GET['high_lat_rule'] ?? 'angle_based';
    if (!in_array($highLatRule, ['none', 'angle_based', 'one_seventh', 'middle_of_night'], true)) {
        $highLatRule = 'angle_based';
    }

    $imsakMinutes = filter_var($_GET['imsak_minutes'] ?? '10', FILTER_VALIDATE_FLOAT);
    if ($imsakMinutes === false || $imsakMinutes < 0 || $imsakMinutes > 60) {
        $errors[] = 'Imsak minutes-before-Fajr must be between 0 and 60.';
    }

    $timeFormat = ($_GET['time_format'] ?? '12') === '24' ? '24' : '12';

    if (!$errors) {
        $result = pt_calculate([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('n'),
            'day' => (int) $dt->format('j'),
            'lat' => $lat,
            'lng' => $lng,
            'elevation' => $elevation,
            'timezone' => $timezone,
            'dst' => $dst,
            'fajr_angle' => $fajrAngle,
            'isha_angle' => $ishaAngle,
            'isha_minutes' => $ishaMinutes,
            'maghrib_angle' => $maghribAngle,
            'maghrib_minutes' => $maghribMinutes,
            'asr_factor' => $asrFactor,
            'imsak_minutes' => $imsakMinutes,
            'high_lat_rule' => $highLatRule,
        ]);
    }
}

$pageTitle = 'Prayer Time Calculator';
$theme = 'main';
require __DIR__ . '/header.php';
?>

<div class="form-card form-card-wide reveal">
    <h1>Manual Prayer Time Calculator</h1>
    <p class="pt-summary">
        Every prayer time below is worked out on this server from plain astronomical formulas
        (sun position, declination, equation of time) applied to the exact date, coordinates,
        elevation and twilight angles you enter — no prayer-time or geocoding API is called.
        Prayer times are governed entirely by the <strong>sun's</strong> position; there is no
        lunar component to the math. "Moonsighting Committee" below is the name of one twilight-angle
        method, not an actual moon-phase calculation — pick <strong>Custom</strong> if you want to
        type in angles from a specific authority by hand.
    </p>

    <?php if ($errors): ?>
        <div class="flash flash-error">
            <strong>Please fix the following:</strong>
            <ul style="margin:.5rem 0 0 1.1rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= mp_e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($result): ?>
        <?php if ($result['warnings']): ?>
            <div class="flash flash-error">
                <strong>Heads up:</strong>
                <ul style="margin:.5rem 0 0 1.1rem;">
                    <?php foreach ($result['warnings'] as $warning): ?>
                        <li><?= mp_e($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($highLatRule === 'none'): ?>
                    <p style="margin:.5rem 0 0;">Locations inside the polar circles can have days with no real sunrise/sunset
                    at all — no calculation method can derive true twilight angles there. Scholars generally recommend
                    following a high-latitude adjustment rule, or the schedule of the nearest latitude where the sun
                    does rise and set (e.g. ~45&deg;), during those weeks.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="content-panel">
            <p class="pt-summary" style="margin-bottom:.25rem;">
                <?php if (trim($_GET['place'] ?? '')): ?><strong><?= mp_e(trim($_GET['place'])) ?></strong> &middot; <?php endif; ?>
                <?php if (trim($_GET['postal_code'] ?? '')): ?><?= mp_e(trim($_GET['postal_code'])) ?> &middot; <?php endif; ?>
                Lat <?= mp_e((string) $lat) ?>, Lng <?= mp_e((string) $lng) ?>
                &middot; <?= mp_e($dt->format('Y-m-d')) ?>
                &middot; UTC<?= $timezone >= 0 ? '+' : '' ?><?= mp_e((string) $timezone) ?><?= $dst ? ' (+DST)' : '' ?>
                &middot; <?= mp_e($methods[$method]['label']) ?>
            </p>
            <div class="pt-results-grid">
                <?php
                $labels = [
                    'imsak' => 'Imsak', 'fajr' => 'Fajr', 'sunrise' => 'Sunrise', 'dhuhr' => 'Dhuhr',
                    'asr' => 'Asr', 'sunset' => 'Sunset', 'maghrib' => 'Maghrib', 'isha' => 'Isha',
                    'midnight' => 'Islamic Midnight',
                ];
                $highlight = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];
                foreach ($labels as $key => $label):
                ?>
                    <div class="pt-time-card<?= in_array($key, $highlight, true) ? ' pt-highlight' : '' ?>">
                        <div class="pt-label"><?= mp_e($label) ?></div>
                        <div class="pt-value"><?= mp_e(pt_format_time($result['times'][$key], $timeFormat === '24')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="get" action="/prayer-times.php" id="pt-form">
        <input type="hidden" name="calc" value="1">

        <fieldset class="pt-fieldset">
            <legend>Date &amp; place</legend>
            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= mp_e(pt_old('date', date('Y-m-d'))) ?>" required>
            </div>
            <div class="pt-row">
                <div class="form-group">
                    <label for="place">Place name <small>(optional, for display only)</small></label>
                    <input type="text" id="place" name="place" value="<?= mp_e(pt_old('place')) ?>" placeholder="e.g. Blue Mosque, Istanbul">
                </div>
                <div class="form-group">
                    <label for="postal_code">Postal / ZIP code <small>(optional, for display only)</small></label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= mp_e(pt_old('postal_code')) ?>">
                </div>
            </div>
            <div class="pt-row">
                <div class="form-group">
                    <label for="lat">Latitude (&deg;, North positive)</label>
                    <input type="number" id="lat" name="lat" step="0.000001" min="-90" max="90" value="<?= mp_e(pt_old('lat')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="lng">Longitude (&deg;, East positive)</label>
                    <input type="number" id="lng" name="lng" step="0.000001" min="-180" max="180" value="<?= mp_e(pt_old('lng')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="elevation">Elevation above sea level (metres)</label>
                    <input type="number" id="elevation" name="elevation" step="1" min="0" max="9000" value="<?= mp_e(pt_old('elevation', '0')) ?>">
                </div>
            </div>
            <button type="button" class="btn btn-secondary pt-inline-btn" onclick="ptUseMyLocation()">Use my device location</button>
            <p class="pt-summary" style="margin-top:.6rem;margin-bottom:0;">
                No geocoding API is used to turn an address into coordinates — enter the exact
                latitude/longitude yourself (from a map long-press, GPS device, or survey data),
                or use the button above, which reads your browser's own location, nothing external.
            </p>
        </fieldset>

        <fieldset class="pt-fieldset">
            <legend>Timezone</legend>
            <div class="pt-row">
                <div class="form-group">
                    <label for="timezone">UTC offset (hours)</label>
                    <input type="number" id="timezone" name="timezone" step="0.25" min="-12" max="14" value="<?= mp_e(pt_old('timezone')) ?>" required>
                </div>
                <div class="form-group">
                    <label style="visibility:hidden;">DST</label>
                    <label><input type="checkbox" id="dst" name="dst" value="1" <?= pt_old('dst') === '1' ? 'checked' : '' ?>> Daylight Saving Time in effect (+1 hour)</label>
                </div>
            </div>
            <button type="button" class="btn btn-secondary pt-inline-btn" onclick="ptDetectTimezone()">Detect from this browser</button>
        </fieldset>

        <fieldset class="pt-fieldset">
            <legend>Calculation method</legend>
            <div class="form-group">
                <label for="method">Starting preset <small>(fills in the angles below — every value stays editable)</small></label>
                <select id="method" name="method" onchange="ptApplyMethod(this.value)">
                    <?php foreach ($methods as $key => $m): ?>
                        <option value="<?= mp_e($key) ?>" <?= pt_old('method', 'mwl') === $key ? 'selected' : '' ?>><?= mp_e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pt-row">
                <div class="form-group">
                    <label for="fajr_angle">Fajr angle (&deg; below horizon)</label>
                    <input type="number" id="fajr_angle" name="fajr_angle" step="0.1" min="0" max="30" value="<?= mp_e(pt_old('fajr_angle', '18')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="asr_factor">Asr juristic method</label>
                    <select id="asr_factor" name="asr_factor">
                        <option value="1" <?= pt_old('asr_factor', '1') === '1' ? 'selected' : '' ?>>Standard (Shafi'i / Maliki / Hanbali)</option>
                        <option value="2" <?= pt_old('asr_factor') === '2' ? 'selected' : '' ?>>Hanafi</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Isha</label>
                <div class="pt-radio-row">
                    <label><input type="radio" name="isha_mode" value="angle" <?= pt_old('isha_mode', 'angle') === 'angle' ? 'checked' : '' ?> onchange="ptToggleModes()"> By angle</label>
                    <label><input type="radio" name="isha_mode" value="minutes" <?= pt_old('isha_mode') === 'minutes' ? 'checked' : '' ?> onchange="ptToggleModes()"> Fixed minutes after Maghrib</label>
                </div>
                <div id="isha_angle_wrap">
                    <input type="number" id="isha_angle" name="isha_angle" step="0.1" min="0" max="30" value="<?= mp_e(pt_old('isha_angle', '17')) ?>" placeholder="Angle in degrees">
                </div>
                <div id="isha_minutes_wrap">
                    <input type="number" id="isha_minutes" name="isha_minutes" step="1" min="0" max="240" value="<?= mp_e(pt_old('isha_minutes', '90')) ?>" placeholder="Minutes after Maghrib">
                    <small>Umm al-Qura / Gulf / Qatar move this to 120 minutes during Ramadan &mdash; just change the number.</small>
                </div>
            </div>

            <div class="form-group">
                <label>Maghrib</label>
                <div class="pt-radio-row">
                    <label><input type="radio" name="maghrib_mode" value="angle" <?= pt_old('maghrib_mode', 'angle') === 'angle' ? 'checked' : '' ?> onchange="ptToggleModes()"> By angle (0.833&deg; = standard sunset)</label>
                    <label><input type="radio" name="maghrib_mode" value="minutes" <?= pt_old('maghrib_mode') === 'minutes' ? 'checked' : '' ?> onchange="ptToggleModes()"> Fixed minutes after sunset</label>
                </div>
                <div id="maghrib_angle_wrap">
                    <input type="number" id="maghrib_angle" name="maghrib_angle" step="0.1" min="0" max="10" value="<?= mp_e(pt_old('maghrib_angle', '0.833')) ?>" placeholder="Angle in degrees">
                </div>
                <div id="maghrib_minutes_wrap">
                    <input type="number" id="maghrib_minutes" name="maghrib_minutes" step="1" min="0" max="60" value="<?= mp_e(pt_old('maghrib_minutes', '3')) ?>" placeholder="Minutes after sunset">
                </div>
            </div>
        </fieldset>

        <fieldset class="pt-fieldset">
            <legend>Fine-tuning</legend>
            <div class="pt-row">
                <div class="form-group">
                    <label for="high_lat_rule">High-latitude adjustment</label>
                    <select id="high_lat_rule" name="high_lat_rule">
                        <option value="none" <?= pt_old('high_lat_rule', 'angle_based') === 'none' ? 'selected' : '' ?>>None (leave undefined if the sun never reaches the angle)</option>
                        <option value="angle_based" <?= pt_old('high_lat_rule', 'angle_based') === 'angle_based' ? 'selected' : '' ?>>Angle-based (recommended)</option>
                        <option value="one_seventh" <?= pt_old('high_lat_rule') === 'one_seventh' ? 'selected' : '' ?>>One-seventh of the night</option>
                        <option value="middle_of_night" <?= pt_old('high_lat_rule') === 'middle_of_night' ? 'selected' : '' ?>>Middle of the night</option>
                    </select>
                    <small>Only matters near/inside the polar circles, where twilight angles can fail to occur in summer.</small>
                </div>
                <div class="form-group">
                    <label for="imsak_minutes">Imsak (minutes before Fajr)</label>
                    <input type="number" id="imsak_minutes" name="imsak_minutes" step="1" min="0" max="60" value="<?= mp_e(pt_old('imsak_minutes', '10')) ?>">
                </div>
                <div class="form-group">
                    <label for="time_format">Time format</label>
                    <select id="time_format" name="time_format">
                        <option value="12" <?= pt_old('time_format', '12') === '12' ? 'selected' : '' ?>>12-hour (AM/PM)</option>
                        <option value="24" <?= pt_old('time_format') === '24' ? 'selected' : '' ?>>24-hour</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <button type="submit" class="btn">Calculate Prayer Times</button>
    </form>
</div>

<script>
const PT_METHODS = <?= json_encode($methods, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;

function ptApplyMethod(key) {
    const m = PT_METHODS[key];
    if (!m) return;

    document.getElementById('fajr_angle').value = m.fajr_angle;

    if (m.isha_minutes !== null) {
        document.querySelector('input[name="isha_mode"][value="minutes"]').checked = true;
        document.getElementById('isha_minutes').value = m.isha_minutes;
    } else {
        document.querySelector('input[name="isha_mode"][value="angle"]').checked = true;
        document.getElementById('isha_angle').value = m.isha_angle;
    }

    if (m.maghrib_minutes !== null) {
        document.querySelector('input[name="maghrib_mode"][value="minutes"]').checked = true;
        document.getElementById('maghrib_minutes').value = m.maghrib_minutes;
    } else {
        document.querySelector('input[name="maghrib_mode"][value="angle"]').checked = true;
        document.getElementById('maghrib_angle').value = m.maghrib_angle;
    }

    document.getElementById('asr_factor').value = String(m.asr_factor);

    ptToggleModes();
}

function ptToggleModes() {
    const ishaMode = document.querySelector('input[name="isha_mode"]:checked').value;
    document.getElementById('isha_angle_wrap').style.display = ishaMode === 'angle' ? '' : 'none';
    document.getElementById('isha_minutes_wrap').style.display = ishaMode === 'minutes' ? '' : 'none';

    const maghribMode = document.querySelector('input[name="maghrib_mode"]:checked').value;
    document.getElementById('maghrib_angle_wrap').style.display = maghribMode === 'angle' ? '' : 'none';
    document.getElementById('maghrib_minutes_wrap').style.display = maghribMode === 'minutes' ? '' : 'none';
}

function ptUseMyLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by this browser.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('lat').value = pos.coords.latitude.toFixed(6);
        document.getElementById('lng').value = pos.coords.longitude.toFixed(6);
        if (pos.coords.altitude && pos.coords.altitude > 0) {
            document.getElementById('elevation').value = Math.round(pos.coords.altitude);
        }
    }, function (err) {
        alert('Could not get your location: ' + err.message);
    });
}

function ptDetectTimezone() {
    document.getElementById('timezone').value = -new Date().getTimezoneOffset() / 60;
}

document.addEventListener('DOMContentLoaded', function () {
    ptToggleModes();
    if (!document.getElementById('timezone').value) {
        ptDetectTimezone();
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
