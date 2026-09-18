<?php
/**
 * Doctor card — shared by the homepage's "Featured Doctors" section and the
 * /doctors listing page so both always render identically. Expects $d in
 * scope: a doctor row (doctors JOIN users) with a 'specializations' key
 * already attached via get_doctor_specializations().
 */
$fee = (float) $d['consultation_fee_online'];
?>
<div class="card card-hover doctor-card" data-reveal data-tilt>
    <div class="doctor-card-top">
        <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>" alt="<?= e($d['full_name']) ?>">
        <div>
            <h3><?= e($d['full_name']) ?></h3>
            <div class="spec"><?= e(specialization_names($d['specializations']) ?: 'General') ?></div>
            <?php if ((int)$d['rating_count'] > 0): ?>
            <div class="rating"><i class="ri-star-fill"></i> <?= number_format($d['rating_avg'], 1) ?> (<?= (int)$d['rating_count'] ?>)</div>
            <?php else: ?>
            <div class="rating rating-new">New on <?= e(get_setting('site_name', SITE_NAME)) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
        <?php if ($d['is_premium']): ?><span class="badge badge-premium"><i class="ri-vip-crown-fill"></i> Premium</span><?php endif; ?>
        <?php if ($d['free_consultation']): ?><span class="badge badge-free">Free Consult</span><?php endif; ?>
    </div>
    <div class="doctor-card-meta">
        <?php if ((int)$d['experience_years'] > 0): ?><span><i class="ri-briefcase-line"></i> <?= (int)$d['experience_years'] ?> yrs exp</span><?php endif; ?>
        <span><i class="ri-map-pin-line"></i> <?= e($d['clinic_city'] ?: 'Online') ?></span>
    </div>
    <div class="doctor-card-footer">
        <?php if ($fee > 0): ?>
        <div class="fee"><?= format_currency($fee) ?> <small>/ online</small></div>
        <?php elseif ($d['free_consultation']): ?>
        <div class="fee">Free <small>/ online</small></div>
        <?php endif; ?>
        <a href="<?= e(doctor_url($d['slug'])) ?>" class="btn btn-outline btn-sm"<?= (!$fee && !$d['free_consultation']) ? ' style="margin-left:auto;"' : '' ?>>View Profile</a>
    </div>
</div>
