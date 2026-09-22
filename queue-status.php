<?php
/** Public, no login required — "which ticket number is the doctor on right now?" Shareable link shown to the doctor on their queue dashboard. */
require __DIR__ . '/config/config.php';

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stmt = mysqli_prepare(db(), "SELECT d.id, d.slug, d.clinic_name, u.full_name, u.avatar
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.id = ? AND d.booking_mode = 'tickets' AND d.verification_status = 'verified' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$doctor) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$counter = mysqli_fetch_assoc(mysqli_query(db(), '
    SELECT last_number, current_serving FROM doctor_ticket_counters
    WHERE doctor_id = ' . (int) $doctorId . " AND ticket_date = '" . mysqli_real_escape_string(db(), $date) . "' LIMIT 1
")) ?: ['last_number' => 0, 'current_serving' => 0];

$pageTitle = $doctor['full_name'] . ' — Live Queue Status | ' . SITE_NAME;
$metaDescription = 'Check the current ticket number being served by ' . $doctor['full_name'] . ' on ' . SITE_NAME . '.';
$metaRobots = 'noindex, follow';
$extraScripts = '<script>document.addEventListener("DOMContentLoaded",function(){
    var doctorId = ' . (int) $doctorId . ', date = ' . json_encode($date) . ';
    function refresh() {
        fetch("/ajax/check-ticket-day.php?doctor_id=" + doctorId + "&date=" + date).then(function(r){return r.json();}).then(function(res){
            if (res.success && res.open) {
                document.getElementById("qs-serving").textContent = res.current_serving;
                document.getElementById("qs-last").textContent = res.last_number;
            }
        });
    }
    refresh();
    setInterval(refresh, 10000);
});</script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container" style="max-width:520px;">
        <div class="card" style="padding:36px;text-align:center;" data-reveal>
            <img src="<?= e(avatar_url($doctor['avatar'], $doctor['full_name'])) ?>" alt="<?= e($doctor['full_name']) ?>" width="72" height="72" style="width:72px;height:72px;border-radius:18px;object-fit:cover;margin:0 auto 16px;">
            <h1 style="font-size:20px;margin-bottom:4px;"><?= e($doctor['full_name']) ?></h1>
            <?php if ($doctor['clinic_name']): ?><p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:20px;"><?= e($doctor['clinic_name']) ?></p><?php endif; ?>
            <p style="font-size:12.5px;font-weight:700;text-transform:uppercase;color:var(--color-text-muted);margin-bottom:8px;">Now Serving — <?= e(format_date($date)) ?></p>
            <div style="font-size:72px;font-weight:800;color:var(--color-primary);line-height:1;" id="qs-serving"><?= (int) $counter['current_serving'] ?></div>
            <p style="color:var(--color-text-muted);margin-top:16px;">Total tickets today: <strong id="qs-last"><?= (int) $counter['last_number'] ?></strong></p>
            <p style="margin-top:24px;"><a href="<?= e(doctor_url($doctor['slug'])) ?>" class="btn btn-primary">Get a Ticket for Another Day</a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
