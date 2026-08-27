<?php
/**
 * Tool Frequency / Deadline Engine - run every 15-30 minutes via system cron:
 *   php-time-limit; php /path/to/fmcg-qms/cron/daily-deadline-check.php
 * Handles: reminder -> final reminder -> missed (for daily / per_shift tools).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$now = new DateTime();
$companies = db_all("SELECT id FROM companies WHERE status='active'", []);
$processed = 0;

foreach ($companies as $company) {
    $cid = (int)$company['id'];
    $reminderTime = get_company_setting($cid, 'reminder_time', '15:00');
    $finalReminderTime = get_company_setting($cid, 'final_reminder_time', '16:30');
    $deadline = get_company_setting($cid, 'submission_deadline', '17:00');

    $today = $now->format('Y-m-d');
    $reminderAt = DateTime::createFromFormat('Y-m-d H:i', "$today $reminderTime");
    $finalAt = DateTime::createFromFormat('Y-m-d H:i', "$today $finalReminderTime");
    $deadlineAt = DateTime::createFromFormat('Y-m-d H:i', "$today $deadline");

    $assignments = db_all(
        "SELECT ta.*, u.name AS user_name, u.email, t.name AS tool_name, t.frequency
         FROM tool_assignments ta JOIN users u ON u.id=ta.user_id JOIN tools t ON t.id=ta.tool_id
         WHERE ta.company_id=? AND ta.status='active' AND u.status='active' AND t.frequency IN ('daily','per_shift')",
        [$cid]
    );

    foreach ($assignments as $a) {
        $periodKey = tool_period_key($a['frequency']);
        $submitted = db_one("SELECT id FROM tool_submissions WHERE tool_id=? AND user_id=? AND period_key=? AND status='submitted'",
            [$a['tool_id'], $a['user_id'], $periodKey]);
        if ($submitted) continue;

        if ($now >= $deadlineAt) {
            $alreadyMissed = db_one("SELECT id FROM tool_submissions WHERE tool_id=? AND user_id=? AND period_key=? AND status='missed'",
                [$a['tool_id'], $a['user_id'], $periodKey]);
            if ($alreadyMissed) continue;
            db_exec("INSERT INTO tool_submissions (company_id, tool_id, user_id, period_key, status, created_at) VALUES (?,?,?,?, 'missed', NOW())",
                [$cid, $a['tool_id'], $a['user_id'], $periodKey]);
            notify($cid, $a['user_id'], 'missed_submission', 'Missed Submission', $a['tool_name'] . ' was not submitted by the deadline.', base_url('employee/dashboard.php'), 'danger');
            notify_company_managers($cid, 'missed_submission', 'Employee Missed Submission', $a['user_name'] . ' missed ' . $a['tool_name'] . '.', base_url('manager/submissions.php'), 'warning');
            send_event_email($cid, $a['email'], $a['user_name'], 'missed_submission', [
                'employee_name' => $a['user_name'], 'tool_name' => $a['tool_name'], 'date' => $today, 'deadline' => $deadline,
            ]);
            log_activity($cid, null, 'update', 'tool_assignment', $a['id'], "Marked missed: {$a['tool_name']} for {$a['user_name']}");
            $processed++;
        } elseif ($now >= $finalAt) {
            $alreadySent = db_one("SELECT id FROM notifications WHERE company_id=? AND user_id=? AND type='daily_reminder_final' AND DATE(created_at)=?", [$cid, $a['user_id'], $today]);
            if ($alreadySent) continue;
            notify($cid, $a['user_id'], 'daily_reminder_final', 'Final Reminder', $a['tool_name'] . ' is due by ' . $deadline . ' today.', base_url('employee/dashboard.php'), 'warning');
            send_event_email($cid, $a['email'], $a['user_name'], 'missed_submission', ['employee_name' => $a['user_name'], 'tool_name' => $a['tool_name'], 'deadline' => $deadline]);
        } elseif ($now >= $reminderAt) {
            $alreadySent = db_one("SELECT id FROM notifications WHERE company_id=? AND user_id=? AND type='daily_reminder' AND DATE(created_at)=?", [$cid, $a['user_id'], $today]);
            if ($alreadySent) continue;
            notify($cid, $a['user_id'], 'daily_reminder', 'Submission Reminder', $a['tool_name'] . ' is due by ' . $deadline . ' today.', base_url('employee/dashboard.php'), 'info');
            send_event_email($cid, $a['email'], $a['user_name'], 'daily_reminder', ['employee_name' => $a['user_name'], 'tool_name' => $a['tool_name'], 'deadline' => $deadline]);
        }
    }
}

process_pending_emails(200);
echo "Deadline check complete. $processed submission(s) marked missed.\n";
