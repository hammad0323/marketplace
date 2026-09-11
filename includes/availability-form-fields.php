<?php
/**
 * Weekly availability editor — one card per day, each holding two independent
 * timing blocks (online / physical) so a doctor can run different hours for
 * live video consultations vs. an in-person clinic or hospital.
 *
 * Expects in scope:
 *   $availByDay      — from get_doctor_availability_by_day() (may be [])
 *   $availContainerId — optional wrapper id (defaults below), pass a unique
 *                        one when the partial is rendered more than once on
 *                        the same page (e.g. inside an admin modal).
 */
$availContainerId = $availContainerId ?? 'availability-days';
?>
<div id="<?= e($availContainerId) ?>">
    <?php for ($d = 0; $d <= 6; $d++):
        $blocks = [
            'online' => ['label' => 'Online / Live Consultation', 'icon' => 'ri-vidicon-line', 'row' => $availByDay[$d]['online'] ?? null, 'defaultStart' => '09:00', 'defaultEnd' => '13:00'],
            'physical' => ['label' => 'Physical Clinic / Hospital', 'icon' => 'ri-hospital-line', 'row' => $availByDay[$d]['physical'] ?? null, 'defaultStart' => '16:00', 'defaultEnd' => '20:00'],
        ];
    ?>
    <div class="card availability-day-card" data-day="<?= $d ?>" style="padding:18px 20px;margin-bottom:12px;">
        <strong style="display:block;margin-bottom:12px;font-size:14.5px;"><?= day_name($d) ?></strong>
        <div class="grid grid-2" style="gap:14px;">
            <?php foreach ($blocks as $type => $b): $row = $b['row']; ?>
            <div class="avail-block" data-type="<?= $type ?>" style="border:1.5px solid var(--color-border);border-radius:var(--radius-sm);padding:14px;">
                <label class="checkbox-row" style="margin-bottom:10px;font-weight:600;font-size:13.5px;">
                    <input type="checkbox" class="avail-enabled" <?= $row ? 'checked' : '' ?>>
                    <i class="<?= e($b['icon']) ?>"></i> <?= e($b['label']) ?>
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="time" class="form-control avail-start" value="<?= $row ? substr($row['start_time'], 0, 5) : e($b['defaultStart']) ?>" style="width:118px;">
                    <span style="color:var(--color-text-muted);font-size:12.5px;">to</span>
                    <input type="time" class="form-control avail-end" value="<?= $row ? substr($row['end_time'], 0, 5) : e($b['defaultEnd']) ?>" style="width:118px;">
                    <select class="form-control avail-duration" style="width:100px;">
                        <?php foreach ([15, 20, 30, 45, 60] as $mins): ?>
                        <option value="<?= $mins ?>" <?= ($row['slot_duration_mins'] ?? 30) == $mins ? 'selected' : '' ?>><?= $mins ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endfor; ?>
</div>
