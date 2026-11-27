<?php
/** /manage/announcements[?venue=] */
require_admin('announcements');
$venue = int_param('venue');
if (is_post()) {
    require_post_csrf();
    $vid = post_int('venue_id') ?: $venue;
    if (post('action') === 'delete') {
        $a = db_one('SELECT * FROM th_announcement WHERE news_id = ?', 'i', array(post_int('news_id')));
        if ($a) {
            db_exec('DELETE FROM th_announcement WHERE news_id = ?', 'i', array((int)$a['news_id']));
            log_me('announcement.delete', 'announcement', $a['news_id'], venue_log_subset($a), null);
            flash('success', 'Announcement deleted.');
        }
    } else {
        $nid = post_int('news_id');
        if ($nid) {
            $vid = (int)db_val('SELECT venue_id FROM th_announcement WHERE news_id = ?', 'i', array($nid));
        }
        if (!$vid) {
            flash('danger', 'Choose the mosque or organization.');
        } else {
            announcement_save($nid, $vid);
        }
    }
    bo_redirect('admin', 'announcements' . ($venue ? '?venue=' . $venue : ''));
}
$edit = isset($_GET['edit']) ? db_one('SELECT * FROM th_announcement WHERE news_id = ?', 'i', array(int_param('edit'))) : null;
$page = max(1, int_param('page', 1));
$per = 25;
$where = $venue ? 'WHERE a.venue_id = ' . (int)$venue : '';
$total = (int)db_val("SELECT COUNT(*) FROM th_announcement a $where");
$rows = db_all("SELECT a.*, v.venue_title FROM th_announcement a LEFT JOIN th_venue v ON v.venue_id = a.venue_id $where ORDER BY a.created_date DESC, a.news_id DESC LIMIT " . (($page - 1) * $per) . ", $per");
bo_start('admin', 'Announcements', array('editor' => true));
?>
<div class="row g-4">
  <div class="col-xl-5"><?php announcement_form($edit, ADMIN_SELF . ($venue ? '?venue=' . $venue : ''), true, $venue); ?></div>
  <div class="col-xl-7">
    <div class="card bo-card">
      <div class="card-header d-flex align-items-center gap-2"><h2 class="h6 m-0">Announcements</h2><form class="ms-auto" method="get" style="min-width:240px"><?php bo_venue_select('venue', $venue, '', false, 'avenue', 'Filter by mosque…'); ?></form></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <tbody>
        <?php if (!$rows): ?><tr><td class="text-muted small p-3">No announcements.</td></tr><?php endif; ?>
        <?php foreach ($rows as $a): ?>
          <tr>
            <td><strong><?= e(clean_text($a['news_title'])) ?></strong><div class="small text-muted"><?= e(trim((string)$a['venue_title'])) ?> · posted <?= e(fmt_date($a['created_date'])) ?><?= valid_date($a['schedule_date']) && $a['schedule_date'] > date('Y-m-d') ? ' · shows from ' . e(fmt_date($a['schedule_date'])) : '' ?></div></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-light" href="<?= e(ADMIN_SELF . '?' . http_build_query(array_filter(array('venue' => $venue, 'edit' => (int)$a['news_id'])))) ?>"><i class="bi bi-pencil"></i></a>
              <button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete', 'news_id' => (int)$a['news_id']))) ?>' data-confirm-text="Delete this announcement?"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div>
    </div>
  </div>
</div>
<?php bo_end();
