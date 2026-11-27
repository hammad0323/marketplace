<?php
/**
 * Profile, program/service/event and announcement editing — shared by the Super Admin and the portal.
 *
 * Portal rules: the venue id always comes from the session; every query that changes a detail or
 * announcement also checks `venue_id = <signed-in venue>`, so editing another venue's record by
 * changing an id is impossible.
 *
 * Approval workflow (Super Admin → Settings → Workflow):
 *   - portal profile changes are stored in storage/pending/venue-{id}.json until approved
 *   - portal programs/services/events are saved with th_details.status_verify = 0 until approved
 */
require_once __DIR__ . '/uploads.php';

function venue_text_fields()
{
    return array(
        'venue_title' => array('Name', 500), 'venue_aka' => array('Also known as (AKA)', 500), 'street_address' => array('Street address', 500),
        'postal_code' => array('Postal / ZIP code', 200), 'phone_number' => array('Phone', 100), 'email_address' => array('Email', 200),
        'website_link' => array('Website', 200), 'meta_description' => array('Short description (used in search results)', 2000),
        'facebook_link' => array('Facebook', 500), 'instagram_link' => array('Instagram', 500), 'youtube_link' => array('YouTube', 500),
        'twitter_link' => array('X (Twitter)', 500), 'tiktok_link' => array('TikTok', 500), 'linkedin_link' => array('LinkedIn', 500),
        'whatsapp_link' => array('WhatsApp group / chat link', 200), 'linktree_link' => array('Linktree', 200), 'spotify_link' => array('Spotify', 300),
        'snapchat_link' => array('Snapchat', 500), 'other_link' => array('Other link', 500),
    );
}

/** Read and validate the profile form. Returns array(data, errors). */
function venue_form_read($isAdmin)
{
    $data = array();
    $errors = array();
    foreach (venue_text_fields() as $k => $info) {
        $val = mb_substr(post($k), 0, $info[1], 'UTF-8');
        $data[$k] = $val;
    }
    if ($data['venue_title'] === '') {
        $errors[] = 'The name is required.';
    }
    if ($data['email_address'] !== '' && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    }
    foreach (array('website_link', 'facebook_link', 'instagram_link', 'youtube_link', 'twitter_link', 'tiktok_link', 'linkedin_link', 'whatsapp_link', 'linktree_link', 'spotify_link', 'snapchat_link', 'other_link') as $k) {
        if ($data[$k] !== '' && safe_url($data[$k]) === '') {
            $errors[] = venue_text_fields()[$k][0] . ' must be a web address.';
        } elseif ($data[$k] !== '') {
            $data[$k] = safe_url($data[$k]);
        }
    }
    $data['about_details'] = safe_rich_html(post('about_details'));
    $data['city_id'] = post_int('city_id');
    if (!$data['city_id'] || !db_val('SELECT city_id FROM th_city WHERE city_id = ?', 'i', array($data['city_id']))) {
        $errors[] = 'Choose a city.';
    }
    $lat = post('lat');
    $lng = post('lng');
    if ($lat !== '' || $lng !== '') {
        if (!valid_lat_lng($lat, $lng)) {
            $errors[] = 'The map location is not valid.';
        } else {
            $data['__coords'] = array((float)$lat, (float)$lng);
        }
    }
    if ($isAdmin) {
        $data['role_id'] = post_int('role_id', 1);
        if (!db_val('SELECT role_id FROM organization_roles WHERE role_id = ?', 'i', array($data['role_id']))) {
            $errors[] = 'Choose a type.';
        }
        $data['verify_status'] = post('verify_status') === '1' ? 1 : 0;
        $data['url'] = mb_substr(post('url'), 0, 500, 'UTF-8');
        $data['comment'] = mb_substr(post('comment'), 0, 400, 'UTF-8');
        $data['cover_alt_status'] = post('cover_alt_status') === '1' ? 1 : 0;
    }
    return array($data, $errors);
}

/** Write profile data to th_venue. $data may contain __coords and image file names. */
function venue_apply($venueId, $data)
{
    $old = db_one('SELECT * FROM th_venue WHERE venue_id = ?', 'i', array((int)$venueId));
    if (isset($data['__coords'])) {
        $prev = $old ? coords_from_iframe($old['address_iframe']) : null;
        if (!$prev || abs($prev['lat'] - $data['__coords'][0]) > 0.000001 || abs($prev['lng'] - $data['__coords'][1]) > 0.000001) {
            $data['address_iframe'] = coords_iframe($data['__coords'][0], $data['__coords'][1]);
        }
        coords_cache_clear();
    }
    unset($data['__coords']);
    $allowed = array_merge(array_keys(venue_text_fields()), array('about_details', 'city_id', 'role_id', 'verify_status', 'url', 'comment', 'cover_alt_status', 'profile_image', 'back_image', 'address_iframe'));
    $sets = array();
    $types = '';
    $params = array();
    foreach ($data as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        $sets[] = $k . ' = ?';
        $types .= in_array($k, array('city_id', 'role_id', 'verify_status', 'cover_alt_status'), true) ? 'i' : 's';
        $params[] = $v;
    }
    if (!$sets) {
        return;
    }
    $sets[] = 'update_date = CURDATE()';
    $types .= 'i';
    $params[] = (int)$venueId;
    db_exec('UPDATE th_venue SET ' . implode(', ', $sets) . ' WHERE venue_id = ?', $types, $params);
}

function venue_create($data)
{
    $url = $data['url'] !== '' ? $data['url'] : $data['venue_title'];
    db_exec('INSERT INTO th_venue (role_id, verify_status, url, venue_title, city_id, update_date) VALUES (?, ?, ?, ?, ?, CURDATE())',
        'iissi', array($data['role_id'], $data['verify_status'], $url, $data['venue_title'], $data['city_id']));
    $id = db_last_id();
    $data['url'] = $url;
    venue_apply($id, $data);
    return $id;
}

function pending_profile($venueId)
{
    return storage_read('pending/venue-' . (int)$venueId . '.json', null);
}

/** Profile form HTML. $v = existing row (or empty array), $isAdmin toggles admin-only fields. */
function venue_form($v, $isAdmin, $action)
{
    $isNew = empty($v['venue_id']);
    $coords = !$isNew ? venue_coords($v) : null;
    $roles = db_all('SELECT role_id, role_title FROM organization_roles ORDER BY role_id');
    $f = function ($k) use ($v) { return isset($v[$k]) ? (string)$v[$k] : ''; };
    ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="venue-form" novalidate data-venue-form>
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Basic information</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label><input class="form-control" name="venue_title" value="<?= e($f('venue_title')) ?>" maxlength="500" required></div>
          <?php if ($isAdmin): ?>
          <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="role_id"><?php foreach ($roles as $r): ?><option value="<?= (int)$r['role_id'] ?>"<?= sel($r['role_id'], $f('role_id') !== '' ? $f('role_id') : (isset($_GET['type']) && $_GET['type'] === 'org' ? 2 : 1)) ?>><?= e($r['role_id'] == 1 ? 'Mosque' : $r['role_title']) ?></option><?php endforeach; ?></select></div>
          <?php endif; ?>
          <div class="col-md-<?= $isAdmin ? '6' : '4' ?>"><label class="form-label">Also known as (AKA)</label><input class="form-control" name="venue_aka" value="<?= e($f('venue_aka')) ?>" maxlength="500"></div>
          <?php if ($isAdmin): ?><div class="col-md-6"><label class="form-label">URL name <span class="text-muted small">(used in the profile link)</span></label><input class="form-control" name="url" value="<?= e($f('url')) ?>" maxlength="500" placeholder="Same as the name if empty"></div><?php endif; ?>
          <div class="col-12"><label class="form-label">Short description</label><textarea class="form-control" name="meta_description" rows="2" maxlength="2000"><?= e($f('meta_description')) ?></textarea><div class="form-text">One or two sentences. Shown in Google results and on cards.</div></div>
          <div class="col-12"><label class="form-label">About</label><?php rich_editor('about_details', $f('about_details'), 'about_details', 260); ?></div>
        </div>
      </div>

      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Address &amp; map location</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-8"><label class="form-label">Street address</label><input class="form-control" name="street_address" value="<?= e($f('street_address')) ?>" maxlength="500" data-geo-street></div>
          <div class="col-md-4"><label class="form-label">Postal / ZIP code</label><input class="form-control" name="postal_code" value="<?= e($f('postal_code')) ?>" maxlength="200" data-geo-postal></div>
          <div class="col-md-8"><label class="form-label">City <span class="text-danger">*</span></label><?php bo_city_select('city_id', (int)$f('city_id'), true); ?></div>
          <div class="col-12">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="form-label m-0">Map pin</span>
              <button type="button" class="btn btn-sm btn-outline-primary" data-geo-lookup><i class="bi bi-search me-1"></i>Find from address</button>
              <span class="small text-muted" data-geo-status><?= $coords ? 'Location confirmed. Drag the pin to correct it.' : 'No confirmed location yet. Find it from the address or click the map, then check the pin.' ?></span>
            </div>
            <div class="geo-map" data-geo-map style="height:300px"></div>
            <div class="row g-2 mt-1">
              <div class="col-6 col-md-3"><input class="form-control form-control-sm" name="lat" value="<?= e($coords ? $coords['lat'] : '') ?>" placeholder="Latitude" data-geo-lat aria-label="Latitude"></div>
              <div class="col-6 col-md-3"><input class="form-control form-control-sm" name="lng" value="<?= e($coords ? $coords['lng'] : '') ?>" placeholder="Longitude" data-geo-lng aria-label="Longitude"></div>
              <div class="col-md-6 small text-muted d-flex align-items-center">Saved as a Google Maps location on the profile.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Contact &amp; social media</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone_number" value="<?= e($f('phone_number')) ?>" maxlength="100" type="tel"></div>
          <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" name="email_address" value="<?= e($f('email_address')) ?>" maxlength="200" type="email"></div>
          <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website_link" value="<?= e($f('website_link')) ?>" maxlength="200" placeholder="https://"></div>
          <?php foreach (array('facebook_link', 'instagram_link', 'youtube_link', 'twitter_link', 'tiktok_link', 'linkedin_link', 'whatsapp_link', 'linktree_link', 'spotify_link', 'snapchat_link', 'other_link') as $k): ?>
          <div class="col-md-6"><label class="form-label small"><?= e(venue_text_fields()[$k][0]) ?></label><input class="form-control form-control-sm" name="<?= $k ?>" value="<?= e($f($k)) ?>" placeholder="https://"></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Images</h2></div>
        <div class="card-body">
          <label class="form-label">Logo</label>
          <div class="img-preview img-preview-logo mb-2"><?php img_or_initials(!$isNew ? venue_logo($v) : '', 'Logo', $f('venue_title') ?: 'H'); ?></div>
          <input class="form-control form-control-sm mb-3" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" data-img-input>
          <label class="form-label">Cover image</label>
          <div class="img-preview img-preview-cover mb-2"><?php $cv = !$isNew ? venue_cover($v) : ''; if ($cv !== ''): ?><img src="<?= e($cv) ?>" alt="Cover" onerror="this.remove()"><?php endif; ?></div>
          <input class="form-control form-control-sm" type="file" name="back_image" accept="image/jpeg,image/png,image/webp,image/gif" data-img-input>
          <div class="form-text">JPG, PNG or WebP, up to <?= MAX_UPLOAD_MB ?> MB. Cover: wide image, at least 1600 × 600 px.</div>
        </div>
      </div>
      <?php if ($isAdmin): ?>
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Status</h2></div>
        <div class="card-body">
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="verify_status" value="1" id="vs"<?= chk($isNew || (int)$f('verify_status') === 1) ?>><label class="form-check-label" for="vs">Verified &amp; visible on the website</label></div>
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="cover_alt_status" value="1" id="cas"<?= chk((int)$f('cover_alt_status') === 1) ?>><label class="form-check-label" for="cas">Cover image alternative flag (existing setting)</label></div>
          <label class="form-label small">Internal comment</label><textarea class="form-control form-control-sm" name="comment" rows="2" maxlength="400"><?= e($f('comment')) ?></textarea>
        </div>
      </div>
      <?php endif; ?>
      <div class="bo-sticky-actions">
        <button class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i><?= $isNew ? 'Create profile' : ($isAdmin || !setting('approve_profile') ? 'Save changes' : 'Submit changes for approval') ?></button>
        <?php if (!$isAdmin && setting('approve_profile')): ?><p class="small text-muted mt-2 mb-0">Changes are reviewed by The Hashir team before they appear on your public profile.</p><?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php
}

/**
 * Handle profile POST. Returns venue id on success (redirects are done by the caller), or null with flash errors.
 */
function venue_form_submit($venueId, $isAdmin)
{
    list($data, $errors) = venue_form_read($isAdmin);
    foreach (array('profile_image' => 'venue-images', 'back_image' => 'venue-cover') as $field => $folder) {
        $up = upload_image($field, $folder);
        if (!$up['ok']) {
            $errors[] = ($field === 'profile_image' ? 'Logo: ' : 'Cover: ') . $up['error'];
        } elseif ($up['file'] !== '') {
            $data[$field] = $up['file'];
        }
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        $_SESSION['form_old'] = $_POST;
        return null;
    }
    if (!$venueId) {
        $id = venue_create($data);
        log_me('venue.create', 'venue', $id, null, $data);
        flash('success', 'Profile created.');
        return $id;
    }
    $old = db_one('SELECT * FROM th_venue WHERE venue_id = ?', 'i', array((int)$venueId));
    if (!$isAdmin && setting('approve_profile')) {
        $pending = pending_profile($venueId);
        $pending = is_array($pending) ? $pending : array();
        $pending = array_merge($pending, array('venue_id' => (int)$venueId, 'submitted_at' => date('Y-m-d H:i:s'), 'data' => $data));
        storage_write('pending/venue-' . (int)$venueId . '.json', $pending);
        log_me('venue.submit_changes', 'venue', $venueId, null, $data);
        flash('success', 'Thank you. Your changes were sent for review and will appear once approved.');
        return (int)$venueId;
    }
    venue_apply($venueId, $data);
    log_me('venue.update', 'venue', $venueId, venue_log_subset($old), venue_log_subset($data));
    flash('success', 'Profile saved.');
    return (int)$venueId;
}

function venue_log_subset($d)
{
    if (!is_array($d)) {
        return null;
    }
    unset($d['password'], $d['username'], $d['reset_token'], $d['token_expiry']);
    if (isset($d['about_details'])) {
        $d['about_details'] = excerpt($d['about_details'], 200);
    }
    return $d;
}

// ---------------------------------------------------------------------------
// Programs / services / events (th_details)
// ---------------------------------------------------------------------------
function detail_form_read()
{
    $errors = array();
    $gc = post_int('grand_category_id');
    if (!in_array($gc, array(1, 2, 3), true)) {
        $errors[] = 'Choose Program, Service or Event.';
    }
    $cats = array();
    foreach (post_ids('category_ids') as $cid) {
        if ((int)db_val('SELECT grand_category_id FROM th_categories WHERE category_id = ?', 'i', array($cid)) === $gc) {
            $cats[] = $cid;
        }
    }
    if (!$cats) {
        $errors[] = 'Choose at least one category.';
    }
    $title = mb_substr(post('details_title'), 0, 200, 'UTF-8');
    if ($title === '') {
        $errors[] = 'The title is required.';
    }
    $start = post('start_date');
    $end = post('end_date');
    $expire = post('expire_date');
    foreach (array('start' => $start, 'end' => $end, 'expire' => $expire) as $k => $d) {
        if ($d !== '' && !valid_ymd($d)) {
            $errors[] = 'Check the ' . $k . ' date.';
        }
    }
    if ($start !== '' && $end !== '' && $end < $start) {
        $errors[] = 'The end date can\'t be before the start date.';
    }
    if ($gc === 1 && $start === '') {
        $errors[] = 'Events need a start date.';
    }
    $days = array_intersect((array)(isset($_POST['days']) ? $_POST['days'] : array()), array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'));
    $dayText = $days ? implode(', ', $days) : mb_substr(post('detail_day'), 0, 200, 'UTF-8');
    $time = mb_substr(post('detail_time'), 0, 200, 'UTF-8');
    if (post('time_start') !== '') {
        $ts = to_hm_simple(post('time_start'));
        $te = to_hm_simple(post('time_end'));
        if ($ts) {
            $time = fmt_time($ts) . ($te ? ' – ' . fmt_time($te) : '');
        }
    }
    $reg = post('registration_link');
    $desc = safe_rich_html(post('description'));
    if ($reg !== '') {
        $regUrl = safe_url($reg);
        if ($regUrl === '') {
            $errors[] = 'The registration link must be a web address.';
        } elseif (strpos($desc, $regUrl) === false) {
            $desc .= '<p><a href="' . e($regUrl) . '">Register here</a></p>';
        }
    }
    $data = array(
        'details_title' => $title,
        'details_url' => mb_substr(post('details_url') !== '' ? post('details_url') : $title, 0, 500, 'UTF-8'),
        'grand_category_id' => $gc,
        'category_id' => implode(',', $cats),
        'gender_id' => implode(',', array_intersect(post_ids('gender_ids'), array(1, 2, 3))),
        'age_group_id' => implode(',', post_ids('age_ids')),
        'detail_date' => mb_substr(post('detail_date'), 0, 200, 'UTF-8'),
        'start_date' => $start !== '' ? $start : '0000-00-00',
        'end_date' => $end !== '' ? $end : ($start !== '' && $gc === 1 ? $start : '0000-00-00'),
        'detail_day' => $dayText,
        'detail_time' => $time,
        'description' => $desc,
        'event_location' => mb_substr(post('event_location'), 0, 2000, 'UTF-8'),
        'expire_date' => $expire !== '' ? $expire : ($gc === 1 && $end !== '' ? $end : '0000-00-00'),
    );
    return array($data, $errors);
}

function to_hm_simple($s)
{
    return preg_match('/^(\d{1,2}):(\d{2})$/', trim((string)$s), $m) && (int)$m[1] < 24 && (int)$m[2] < 60 ? sprintf('%02d:%02d', $m[1], $m[2]) : '';
}

/** Save a detail. $venueId is forced by the portal. Returns detail id or null. */
function detail_save($detailId, $venueId, $isAdmin)
{
    list($data, $errors) = detail_form_read();
    if ($isAdmin) {
        $venueId = post_int('venue_id');
    }
    $venue = $venueId ? db_one('SELECT venue_id, venue_title, city_id FROM th_venue WHERE venue_id = ?', 'i', array((int)$venueId)) : null;
    if (!$venue) {
        $errors[] = 'Choose the mosque or organization.';
    }
    $old = null;
    if ($detailId) {
        $old = $isAdmin ? db_one('SELECT * FROM th_details WHERE detail_id = ?', 'i', array((int)$detailId))
            : db_one('SELECT * FROM th_details WHERE detail_id = ? AND venue_id = ?', 'ii', array((int)$detailId, (int)$venueId));
        if (!$old) {
            flash('danger', 'Record not found.');
            return null;
        }
    }
    $up = upload_image('profile_image_1', 'details_picture');
    if (!$up['ok']) {
        $errors[] = 'Image: ' . $up['error'];
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        $_SESSION['form_old'] = $_POST;
        return null;
    }
    if ($up['file'] !== '') {
        $data['profile_image_1'] = $up['file'];
    } elseif (post('remove_image') === '1') {
        $data['profile_image_1'] = '';
    }
    $data['venue_id'] = (int)$venue['venue_id'];
    $data['venue_title_c'] = mb_substr(trim($venue['venue_title']), 0, 500, 'UTF-8');
    $data['event_city'] = (int)$venue['city_id'];
    if ($isAdmin) {
        $data['status_verify'] = post('status_verify') === '1' ? 1 : 0;
    } else {
        $data['status_verify'] = setting('approve_details') ? 0 : 1;
    }
    $types = array('grand_category_id' => 'i', 'venue_id' => 'i', 'status_verify' => 'i', 'event_city' => 'i');
    if ($old) {
        $sets = array();
        $t = '';
        $p = array();
        foreach ($data as $k => $v) {
            $sets[] = $k . ' = ?';
            $t .= isset($types[$k]) ? $types[$k] : 's';
            $p[] = $v;
        }
        $sets[] = 'update_date = CURDATE()';
        $t .= 'i';
        $p[] = (int)$detailId;
        $where = ' WHERE detail_id = ?';
        if (!$isAdmin) {
            $where .= ' AND venue_id = ?';
            $t .= 'i';
            $p[] = (int)$venueId;
        }
        db_exec('UPDATE th_details SET ' . implode(', ', $sets) . $where, $t, $p);
        log_me('detail.update', 'detail', $detailId, venue_log_subset($old), venue_log_subset($data));
        $id = (int)$detailId;
    } else {
        $data += array('profile_image_1' => '', 'profile_image_2' => '');
        $cols = array_keys($data);
        $t = '';
        foreach ($cols as $c) {
            $t .= isset($types[$c]) ? $types[$c] : 's';
        }
        db_exec('INSERT INTO th_details (' . implode(', ', $cols) . ', update_date) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', CURDATE())', $t, array_values($data));
        $id = db_last_id();
        log_me('detail.create', 'detail', $id, null, venue_log_subset($data));
    }
    $g = grand_info($data['grand_category_id']);
    flash('success', $g['single'] . ' saved' . (!$data['status_verify'] ? ' and sent for approval.' : '.'));
    return $id;
}

function detail_form($d, $isAdmin, $action, $gc, $venueId = 0)
{
    $isNew = empty($d['detail_id']);
    $f = function ($k) use ($d) { return isset($d[$k]) ? (string)$d[$k] : ''; };
    $gc = $isNew ? $gc : (int)$d['grand_category_id'];
    $g = grand_info($gc);
    $cats = db_all('SELECT category_id, grand_category_id, TRIM(name) name FROM th_categories ORDER BY grand_category_id, arrangement, name');
    $selCats = array_map('intval', explode(',', $f('category_id')));
    $genders = genders_by_id();
    $ages = age_groups_by_id();
    $selG = array_map('intval', explode(',', $f('gender_id')));
    $selA = array_map('intval', explode(',', $f('age_group_id')));
    $daysSel = array_map('trim', explode(',', $f('detail_day')));
    $allDays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
    $knownDays = !array_diff(array_filter($daysSel), $allDays);
    $dateVal = function ($k) use ($f) { return valid_date($f($k)) ? $f($k) : ''; };
    ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Details</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" name="grand_category_id" data-gc-select><?php foreach (array(2 => 'Program', 3 => 'Service', 1 => 'Event') as $k => $l): ?><option value="<?= $k ?>"<?= sel($k, $gc) ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-md-8"><label class="form-label">Title <span class="text-danger">*</span></label><input class="form-control" name="details_title" value="<?= e($f('details_title')) ?>" maxlength="200" required></div>
          <?php if ($isAdmin): ?>
          <div class="col-md-8"><label class="form-label">Mosque / organization <span class="text-danger">*</span></label><?php bo_venue_select('venue_id', $f('venue_id') !== '' ? (int)$f('venue_id') : $venueId, '', true); ?></div>
          <div class="col-md-4"><label class="form-label">URL name</label><input class="form-control" name="details_url" value="<?= e($f('details_url')) ?>" maxlength="500" placeholder="Same as title if empty"></div>
          <?php endif; ?>
          <div class="col-12"><label class="form-label">Categories <span class="text-danger">*</span></label>
            <select class="form-select" name="category_ids[]" multiple data-cat-select data-placeholder="Choose categories">
              <?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>" data-gc="<?= (int)$c['grand_category_id'] ?>"<?= in_array((int)$c['category_id'], $selCats, true) ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-12"><label class="form-label">Description</label><?php rich_editor('description', $f('description'), 'description', 240); ?></div>
          <div class="col-12"><label class="form-label">Location / venue details</label><textarea class="form-control" name="event_location" rows="2" maxlength="2000" placeholder="e.g. Main hall, 2nd floor — or a different address"><?= e($f('event_location')) ?></textarea></div>
          <div class="col-12"><label class="form-label">Registration or more-info link</label><input class="form-control" name="registration_link" placeholder="https:// (added to the description)"></div>
        </div>
      </div>
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Schedule &amp; audience</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" value="<?= e($dateVal('start_date')) ?>"></div>
          <div class="col-md-4"><label class="form-label">End date</label><input type="date" class="form-control" name="end_date" value="<?= e($dateVal('end_date')) ?>"></div>
          <div class="col-md-4"><label class="form-label">Hide after</label><input type="date" class="form-control" name="expire_date" value="<?= e($dateVal('expire_date')) ?>"><div class="form-text">Events disappear after this date (default: end date).</div></div>
          <div class="col-12"><label class="form-label">Days</label>
            <div class="d-flex flex-wrap gap-2"><?php foreach ($allDays as $dn): ?><input type="checkbox" class="btn-check" name="days[]" value="<?= $dn ?>" id="day<?= $dn ?>"<?= chk($knownDays && in_array($dn, $daysSel, true)) ?>><label class="btn btn-sm btn-outline-secondary" for="day<?= $dn ?>"><?= substr($dn, 0, 3) ?></label><?php endforeach; ?></div>
            <?php if (!$knownDays && $f('detail_day') !== ''): ?><input class="form-control form-control-sm mt-2" name="detail_day" value="<?= e($f('detail_day')) ?>" maxlength="200" aria-label="Days (text)"><?php endif; ?>
          </div>
          <div class="col-md-6"><label class="form-label">Time (text)</label><input class="form-control" name="detail_time" value="<?= e($f('detail_time')) ?>" maxlength="200" placeholder="e.g. 7:00 PM – 8:30 PM, after Isha"></div>
          <div class="col-6 col-md-3"><label class="form-label">or start</label><input type="time" class="form-control" name="time_start"></div>
          <div class="col-6 col-md-3"><label class="form-label">end</label><input type="time" class="form-control" name="time_end"></div>
          <div class="col-md-12"><label class="form-label">Date note</label><input class="form-control" name="detail_date" value="<?= e($f('detail_date')) ?>" maxlength="200" placeholder="e.g. Every weekend during Ramadan"></div>
          <div class="col-md-6"><label class="form-label">Gender</label><div class="d-flex flex-wrap gap-3"><?php foreach ($genders as $gid => $gt): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="gender_ids[]" value="<?= (int)$gid ?>" id="g<?= (int)$gid ?>"<?= chk(in_array((int)$gid, $selG, true)) ?>><label class="form-check-label" for="g<?= (int)$gid ?>"><?= e($gt) ?></label></div><?php endforeach; ?></div></div>
          <div class="col-md-6"><label class="form-label">Age groups</label><div class="d-flex flex-wrap gap-3"><?php foreach ($ages as $aid => $a): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="age_ids[]" value="<?= (int)$aid ?>" id="a<?= (int)$aid ?>"<?= chk(in_array((int)$aid, $selA, true)) ?>><label class="form-check-label" for="a<?= (int)$aid ?>" title="<?= e($a['desc']) ?>"><?= e($a['title']) ?></label></div><?php endforeach; ?></div></div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Image</h2></div>
        <div class="card-body">
          <div class="img-preview img-preview-cover mb-2"><?php $im = !$isNew ? detail_image($d) : ''; if ($im !== ''): ?><img src="<?= e($im) ?>" alt="" onerror="this.remove()"><?php endif; ?></div>
          <input class="form-control form-control-sm" type="file" name="profile_image_1" accept="image/jpeg,image/png,image/webp,image/gif" data-img-input>
          <?php if (!$isNew && $f('profile_image_1') !== ''): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmimg"><label class="form-check-label small" for="rmimg">Remove the current image</label></div><?php endif; ?>
        </div>
      </div>
      <?php if ($isAdmin): ?>
      <div class="card bo-card mb-4"><div class="card-body">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="status_verify" value="1" id="sv"<?= chk($isNew || (int)$f('status_verify') === 1) ?>><label class="form-check-label" for="sv">Approved &amp; visible on the website</label></div>
      </div></div>
      <?php elseif (!$isNew): ?>
      <div class="card bo-card mb-4"><div class="card-body small">Status: <?= status_badge((int)$f('status_verify') === 1, 'Published', 'Waiting for approval') ?></div></div>
      <?php endif; ?>
      <div class="bo-sticky-actions"><button class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i><?= $isAdmin || !setting('approve_details') ? 'Save' : 'Submit for approval' ?></button></div>
    </div>
  </div>
</form>
<?php
}

// ---------------------------------------------------------------------------
// Announcements (th_announcement)
// ---------------------------------------------------------------------------
function announcement_save($id, $venueId)
{
    $title = mb_substr(post('news_title'), 0, 100, 'UTF-8');
    $desc = safe_rich_html(post('news_description'));
    $sched = post('schedule_date') !== '' ? post('schedule_date') : date('Y-m-d');
    $errors = array();
    if ($title === '') {
        $errors[] = 'The title is required.';
    }
    if (!valid_ymd($sched)) {
        $errors[] = 'Check the date.';
    }
    $up = upload_image('news_image', 'announcement');
    if (!$up['ok']) {
        $errors[] = 'Image: ' . $up['error'];
    }
    if ($errors) {
        foreach ($errors as $er) {
            flash('danger', $er);
        }
        return false;
    }
    if ($id) {
        $old = db_one('SELECT * FROM th_announcement WHERE news_id = ? AND venue_id = ?', 'ii', array((int)$id, (int)$venueId));
        if (!$old) {
            flash('danger', 'Announcement not found.');
            return false;
        }
        $img = $up['file'] !== '' ? $up['file'] : (post('remove_image') === '1' ? '' : $old['news_image']);
        db_exec('UPDATE th_announcement SET news_title = ?, news_description = ?, news_image = ?, schedule_date = ? WHERE news_id = ? AND venue_id = ?',
            'ssssii', array($title, $desc, $img, $sched, (int)$id, (int)$venueId));
        log_me('announcement.update', 'announcement', $id, venue_log_subset($old), array('title' => $title));
    } else {
        db_exec('INSERT INTO th_announcement (venue_id, news_title, news_description, news_image, created_date, schedule_date) VALUES (?, ?, ?, ?, CURDATE(), ?)',
            'issss', array((int)$venueId, $title, $desc, $up['file'], $sched));
        log_me('announcement.create', 'announcement', db_last_id(), null, array('title' => $title, 'venue' => $venueId));
    }
    flash('success', 'Announcement saved.');
    return true;
}

function announcement_form($a, $action, $isAdmin, $venueId = 0)
{
    $f = function ($k) use ($a) { return isset($a[$k]) ? (string)$a[$k] : ''; };
    ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card bo-card" novalidate>
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="news_id" value="<?= (int)$f('news_id') ?>">
  <div class="card-header"><h2 class="h6 m-0"><?= $a ? 'Edit announcement' : 'New announcement' ?></h2></div>
  <div class="card-body row g-3">
    <?php if ($isAdmin && !$a): ?><div class="col-12"><label class="form-label">Mosque / organization</label><?php bo_venue_select('venue_id', $venueId, '', true); ?></div><?php endif; ?>
    <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="news_title" value="<?= e($f('news_title')) ?>" maxlength="100" required></div>
    <div class="col-md-4"><label class="form-label">Show from</label><input type="date" class="form-control" name="schedule_date" value="<?= e(valid_date($f('schedule_date')) ? $f('schedule_date') : date('Y-m-d')) ?>"></div>
    <div class="col-12"><label class="form-label">Text</label><?php rich_editor('news_description', $f('news_description'), 'news_description', 160); ?></div>
    <div class="col-md-8"><label class="form-label">Image (optional)</label><input class="form-control form-control-sm" type="file" name="news_image" accept="image/jpeg,image/png,image/webp,image/gif">
      <?php if ($f('news_image') !== ''): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmn"><label class="form-check-label small" for="rmn">Remove the current image</label></div><?php endif; ?></div>
  </div>
  <div class="card-footer"><button class="btn btn-primary">Save announcement</button></div>
</form>
<?php
}
