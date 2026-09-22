<?php
require __DIR__ . '/../config/config.php';
require_role_page('patient');

$patientId = current_profile_id();

$tickets = mysqli_query(db(), '
    SELECT t.*, u.full_name AS doctor_name, d.slug AS doctor_slug,
        c.current_serving
    FROM doctor_tickets t
    JOIN doctors d ON d.id = t.doctor_id
    JOIN users u ON u.id = d.user_id
    LEFT JOIN doctor_ticket_counters c ON c.doctor_id = t.doctor_id AND c.ticket_date = t.ticket_date
    WHERE t.patient_id = ' . (int) $patientId . '
    ORDER BY t.ticket_date DESC, t.ticket_number DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My Tickets';
$heading = 'My Tickets';
require __DIR__ . '/includes/header.php';
?>
<?php if (!$tickets): ?>
<div class="empty-state card"><i class="ri-ticket-2-line"></i><p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No tickets yet</p><p>Doctors using ticket/token booking will show a "Get a Ticket" option on their profile. <a href="/doctors">Find a doctor</a></p></div>
<?php else: ?>
<div class="grid grid-3 stagger">
    <?php foreach ($tickets as $t):
        $isToday = $t['ticket_date'] === date('Y-m-d');
        $isPast = $t['ticket_date'] < date('Y-m-d');
        $statusLabel = ['waiting' => 'Waiting', 'serving' => 'Being Served', 'completed' => 'Completed', 'no_show' => 'Missed', 'cancelled' => 'Cancelled'][$t['status']] ?? $t['status'];
        $statusClass = ['waiting' => 'pending', 'serving' => 'active', 'completed' => 'verified', 'no_show' => 'rejected', 'cancelled' => 'suspended'][$t['status']] ?? 'pending';
    ?>
    <div class="card" style="padding:24px;" data-reveal>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
            <div>
                <div style="font-size:12px;color:var(--color-text-muted);text-transform:uppercase;font-weight:700;">Ticket</div>
                <div style="font-size:36px;font-weight:800;color:var(--color-primary);">#<?= (int) $t['ticket_number'] ?></div>
            </div>
            <span class="status-pill status-<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
        </div>
        <p style="font-weight:600;margin-bottom:2px;"><a href="<?= e(doctor_url($t['doctor_slug'])) ?>"><?= e($t['doctor_name']) ?></a></p>
        <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:10px;"><?= format_date($t['ticket_date']) ?></p>
        <?php if ($isToday && in_array($t['status'], ['waiting', 'serving'], true)): ?>
        <p style="font-size:13px;color:var(--color-text-muted);">Now serving: <strong style="color:var(--color-text);"><?= (int) ($t['current_serving'] ?? 0) ?></strong></p>
        <?php elseif (!$isPast && !$isToday && $t['status'] === 'waiting'): ?>
        <p style="font-size:13px;color:var(--color-text-muted);">Upcoming</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
