<?php
/**
 * PROPOSAL ACTIONS (AJAX): duplicate, delete, change status, restore a version,
 * new public link, send by email.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Method not allowed'], 405);
csrf_check();

$action = post('action');
$id = (int) post('id', 0);

try {
    switch ($action) {
        case 'duplicate':
            $new = pm_duplicate($id);
            if (!$new) json_out(['ok' => false, 'message' => 'Proposal not found.'], 404);
            json_out(['ok' => true, 'id' => $new]);

        case 'delete':
            $p = pm_get($id);
            if (!$p) json_out(['ok' => false, 'message' => 'Proposal not found.'], 404);
            db_query('DELETE FROM proposals WHERE id = ?', [$id]);
            json_out(['ok' => true]);

        case 'status':
            $status = post('status');
            if (!isset(status_meta()[$status])) json_out(['ok' => false, 'message' => 'Invalid status.'], 422);
            $upd = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
            if ($status === 'sent') $upd['sent_at'] = date('Y-m-d H:i:s');
            db_update('proposals', $upd, 'id = ?', [$id]);
            log_activity($id, 'status_changed', 'Status changed to ' . $status);
            json_out(['ok' => true]);

        case 'restore_version':
            $pid = pm_restore_version((int) post('version_id'));
            if (!$pid) json_out(['ok' => false, 'message' => 'Version not found.'], 404);
            log_activity($pid, 'version_restored', 'Restored version #' . (int) post('version_id'));
            json_out(['ok' => true, 'id' => $pid]);

        case 'regenerate_link':
            db_update('proposals', ['public_token' => pm_new_token()], 'id = ?', [$id]);
            log_activity($id, 'link_regenerated', 'Public link regenerated');
            $p = pm_get($id);
            json_out(['ok' => true, 'link' => public_url($p['public_token'])]);

        case 'send_email':
            require_once APP_ROOT . '/includes/mailer.php';
            $p = pm_get($id);
            if (!$p) json_out(['ok' => false, 'message' => 'Proposal not found.'], 404);
            $to = post('to');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'message' => 'Please enter a valid recipient email.'], 422);
            // CC: comma separated list, keep only valid emails
            $cc = [];
            foreach (explode(',', (string) post('cc')) as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) $cc[] = $email;
            }
            $subject = mb_substr(post('subject'), 0, 250);
            $body = (string) post('body');
            $attach = post('attach_pdf') === '1';
            pm_send_proposal_email($p, $to, $cc, $subject, $body, $attach);
            $upd = ['sent_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            if ($p['status'] === 'draft') $upd['status'] = 'sent';
            db_update('proposals', $upd, 'id = ?', [$id]);
            log_activity($id, 'emailed', 'Sent to ' . $to);
            json_out(['ok' => true]);

        default:
            json_out(['ok' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 500);
}
