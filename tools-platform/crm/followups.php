<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    tp_require_csrf();
    $fid = (int) ($_POST['followup_id'] ?? 0);
    tp_execute('UPDATE crm_followups SET completed_at = NOW() WHERE id = ? AND business_id = ?', 'ii', [$fid, $bid]);
    tp_flash_set('success', 'Marked as done.');
    header('Location: ' . tp_url('crm/followups.php'));
    exit;
}

$followups = tp_query(
    "SELECT f.*, l.name lead_name, c.name customer_name, c.id customer_id_link
     FROM crm_followups f
     LEFT JOIN crm_leads l ON l.id = f.lead_id
     LEFT JOIN crm_customers c ON c.id = f.customer_id
     WHERE f.business_id = ? AND f.completed_at IS NULL
     ORDER BY f.due_at ASC",
    'i',
    [$bid]
);

$overdue = array_filter($followups, fn($f) => strtotime($f['due_at']) < strtotime('today'));
$today = array_filter($followups, fn($f) => date('Y-m-d', strtotime($f['due_at'])) === date('Y-m-d') && strtotime($f['due_at']) >= strtotime('today'));
$upcoming = array_filter($followups, fn($f) => strtotime($f['due_at']) >= strtotime('tomorrow'));

$crmPageTitle = 'Follow-up Reminders';
$crmActive = 'followups';
require __DIR__ . '/includes/crm-header.php';

function crm_render_followup_group(array $items, string $emptyMsg): void
{
    if (!$items) {
        echo '<p class="text-muted small mb-0">' . e($emptyMsg) . '</p>';
        return;
    }
    foreach ($items as $f):
        $overdue = strtotime($f['due_at']) < time();
        $channelIcon = ['call' => 'telephone', 'whatsapp' => 'whatsapp', 'meeting' => 'calendar-event', 'email' => 'envelope'][$f['channel']] ?? 'telephone';
        ?>
        <div class="crm-followup-item <?= $overdue ? 'overdue' : '' ?>">
          <span class="channel-icon"><i class="bi bi-<?= $channelIcon ?>"></i></span>
          <div class="flex-grow-1">
            <div><?= e($f['note']) ?></div>
            <div class="small text-muted">
              <?= e($f['lead_name'] ?? $f['customer_name'] ?? 'General') ?> — due <?= date('M j, Y g:ia', strtotime($f['due_at'])) ?>
              <?php if ($f['customer_id_link']): ?> · <a href="<?= tp_url('crm/customer-view.php?id=' . $f['customer_id_link']) ?>">View customer</a><?php endif; ?>
            </div>
          </div>
          <form method="post" class="m-0">
            <?= tp_csrf_field() ?>
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="followup_id" value="<?= (int) $f['id'] ?>">
            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check-lg"></i> Done</button>
          </form>
        </div>
    <?php endforeach;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0 small">Automatic overdue detection — anything past its due date shows here in red until you mark it done.</p>
  <a href="<?= tp_url('crm/followup-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> Add Follow-up</a>
</div>

<div class="admin-card mb-3">
  <h3 class="h6 fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle"></i> Overdue (<?= count($overdue) ?>)</h3>
  <?php crm_render_followup_group($overdue, 'Nothing overdue.'); ?>
</div>

<div class="admin-card mb-3">
  <h3 class="h6 fw-bold mb-2">Today (<?= count($today) ?>)</h3>
  <?php crm_render_followup_group($today, 'Nothing due today.'); ?>
</div>

<div class="admin-card">
  <h3 class="h6 fw-bold mb-2">Upcoming (<?= count($upcoming) ?>)</h3>
  <?php crm_render_followup_group($upcoming, 'Nothing scheduled yet.'); ?>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
