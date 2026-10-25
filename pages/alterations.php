<?php
/** Alteration service: price list with estimates + request form. */
$grouped = alteration_services_grouped();
$errors = [];
$customer = current_customer();
if (is_post()) {
    csrf_check();
    $items = [];
    foreach ((array) ($_POST['svc'] ?? []) as $sid => $qty) {
        if ((int) $qty > 0) {
            $items[(int) $sid] = min(10, (int) $qty);
        }
    }
    $method = input('service_method', 'drop_off', 'post') === 'pickup' ? 'pickup' : 'drop_off';
    $d = [
        'name' => mb_substr(input('name', '', 'post'), 0, 120), 'phone' => mb_substr(input('phone', '', 'post'), 0, 30),
        'email' => strtolower(mb_substr(input('email', '', 'post'), 0, 190)), 'city' => mb_substr(input('city', '', 'post'), 0, 100),
        'address' => mb_substr(input('address', '', 'post'), 0, 255), 'notes' => mb_substr(input('notes', '', 'post'), 0, 2000),
        'preferred_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', input('preferred_date', '', 'post')) ? input('preferred_date', '', 'post') : null,
    ];
    if (input('website', '', 'post') !== '') $errors[] = 'Request rejected.';
    if (!rate_limit('alteration:' . client_ip(), 6, 3600)) $errors[] = 'You have sent several requests recently. Please try again later or call us.';
    if (mb_strlen($d['name']) < 2) $errors[] = 'Please enter your name.';
    if (!valid_phone($d['phone'])) $errors[] = 'Please enter a valid phone number so we can confirm your quote.';
    if ($d['email'] !== '' && !valid_email($d['email'])) $errors[] = 'Please enter a valid email address or leave it empty.';
    if (mb_strlen($d['city']) < 2) $errors[] = 'Please enter your city.';
    if ($method === 'pickup' && mb_strlen($d['address']) < 5) $errors[] = 'Please enter the pickup address.';
    $est = alteration_estimate($items, $method);
    if (!$est['lines']) $errors[] = 'Please choose at least one alteration service.';
    if ($d['preferred_date'] && $d['preferred_date'] < date('Y-m-d')) $d['preferred_date'] = null;
    $photos = [];
    if (!$errors) {
        try {
            $photos = array_slice(upload_images('photos', 1600), 0, 4);
        } catch (RuntimeException $e) {
            $errors[] = 'Photo upload: ' . $e->getMessage();
        }
    }
    if (!$errors) {
        $ref = alteration_generate_reference();
        $token = random_token(20);
        $rid = db_transaction(function () use ($d, $method, $est, $ref, $token, $photos) {
            $rid = db_insert('alteration_requests', [
                'reference' => $ref, 'access_token' => $token, 'customer_id' => customer_id(), 'name' => $d['name'], 'phone' => $d['phone'],
                'email' => nullable($d['email']), 'city' => $d['city'], 'address' => nullable($d['address']), 'service_method' => $method,
                'preferred_date' => $d['preferred_date'], 'notes' => nullable($d['notes']), 'est_min' => $est['min'], 'est_max' => $est['max'],
                'pickup_fee' => $est['pickup_fee'], 'est_days_min' => $est['days_min'], 'est_days_max' => $est['days_max'], 'ip_address' => client_ip(),
            ]);
            foreach ($est['lines'] as $l) {
                db_insert('alteration_request_items', ['request_id' => $rid, 'service_id' => (int) $l['service']['id'], 'garment' => $l['service']['garment'],
                    'service_name' => $l['service']['name'], 'qty' => $l['qty'], 'price_min' => $l['service']['price_min'], 'price_max' => $l['service']['price_max'], 'days_max' => $l['service']['days_max']]);
            }
            foreach ($photos as $ph) {
                db_insert('alteration_request_photos', ['request_id' => $rid, 'image' => $ph]);
            }
            alteration_history_add($rid, 'new', 'Request received. We will contact you to confirm the final price and timing.');
            return $rid;
        });
        $_SESSION['my_alterations'][] = $ref;
        $r = db_row('SELECT * FROM alteration_requests WHERE id = ?', [$rid]);
        mail_alteration_update($r, 'Alteration request received', 'Thank you — we have received your alteration request ' . $ref . '. Estimated price: ' . alteration_price_range($est['min'], $est['max']) . '. We will contact you to confirm.');
        if ($to = setting('alteration_notify_email') ?: setting('order_notify_email')) {
            foreach (array_filter(array_map('trim', explode(',', $to))) as $addr) {
                send_mail($addr, 'New alteration request ' . $ref, mail_layout('New alteration request', '<p>' . e($d['name']) . ' (' . e($d['phone']) . ', ' . e($d['city']) . ')</p><p><a href="' . e(url('admin/alterations/view.php?id=' . $rid)) . '">Open in admin</a></p>'));
            }
        }
        redirect('alterations/request/' . $ref . '?t=' . $token . '&new=1');
    }
    remember_input($_POST);
}
$pre = array_map('intval', (array) ($_SESSION['_old']['svc'] ?? []));
seo_set(['title' => 'Alterations & Tailoring — Suits, Sherwani, Bridal & More', 'description' => 'Get your coat pant, sherwani, prince coat, bridal suit, shalwar qameez or any outfit altered. See estimated prices and turnaround times and request online.',
    'breadcrumbs' => [['Home', url()], ['Alterations', url('alterations')]]]);
$bodyClass = 'page-alterations';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-feature-hero is-alt">
    <div class="uc-container uc-feature-hero-inner">
        <?= breadcrumbs_html([['Home', url()], ['Alterations', null]]) ?>
        <p class="uc-eyebrow">Tailoring &amp; Alterations</p>
        <h1 class="uc-display">Perfect fit for every outfit.</h1>
        <p class="uc-page-intro">Coat pant fitting, sherwani and prince coat alterations, bridal and party wear adjustments, shalwar qameez resizing — bought from us or anywhere else. See the estimated price and time before you send it.</p>
        <div class="d-flex gap-2 flex-wrap"><a class="uc-btn uc-btn--light" href="#request">Request an alteration</a><a class="uc-btn uc-btn--outline-light" href="<?= e(url('alterations/track')) ?>">Track a request</a></div>
    </div>
</section>

<section class="uc-section">
    <div class="uc-container">
        <ol class="uc-steps4">
            <li><i class="bi bi-list-check"></i><strong>Choose services</strong><span>Select what needs altering and see an instant estimate.</span></li>
            <li><i class="bi bi-chat-dots"></i><strong>Get your quote</strong><span>We confirm the final price and ready date by phone / WhatsApp.</span></li>
            <li><i class="bi bi-box-seam"></i><strong>Drop off or pickup</strong><span><?= setting('alteration_pickup_fee', 0) > 0 ? 'Drop at our store, or courier pickup for ' . e(money(setting('alteration_pickup_fee'))) . '.' : 'Drop at our store or request a pickup.' ?></span></li>
            <li><i class="bi bi-stars"></i><strong>Collect your outfit</strong><span>Pay on collection / delivery once it's ready.</span></li>
        </ol>
    </div>
</section>

<section class="uc-section pt-0" id="request">
    <div class="uc-container">
        <?php if ($errors): ?><div class="uc-alert uc-alert--error"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="uc-alt-form" data-alteration-form data-pickup-fee="<?= e((float) setting('alteration_pickup_fee', 0)) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="d-none" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="uc-alt-services">
                <header class="uc-section-head text-left mb-3"><p class="uc-eyebrow">Step 1</p><h2 class="uc-section-title">Choose your alterations</h2>
                    <p class="text-muted small mb-0">Prices are estimates — the final price is confirmed after we see the garment.</p></header>
                <?php if (!$grouped): ?><p class="text-muted">The alteration price list is being updated. Please describe what you need in the notes.</p><?php endif; ?>
                <ul class="nav uc-tabs" role="tablist">
                    <?php $ci = 0; foreach ($grouped as $cat => $_): ?><li class="nav-item"><button type="button" class="nav-link<?= $ci++ === 0 ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#alt-<?= e(slugify($cat)) ?>"><?= e($cat) ?></button></li><?php endforeach; ?>
                </ul>
                <div class="tab-content">
                <?php $ci = 0; foreach ($grouped as $cat => $garmentsList): ?>
                    <div class="tab-pane fade<?= $ci++ === 0 ? ' show active' : '' ?>" id="alt-<?= e(slugify($cat)) ?>">
                        <?php foreach ($garmentsList as $garment => $services): ?>
                            <div class="uc-alt-group">
                                <h3><?= e($garment) ?></h3>
                                <?php foreach ($services as $s): $q = $pre[$s['id']] ?? 0; ?>
                                    <div class="uc-alt-row<?= $q ? ' is-on' : '' ?>" data-alt-row data-min="<?= e((float) $s['price_min']) ?>" data-max="<?= e((float) $s['price_max']) ?>" data-dmin="<?= (int) $s['days_min'] ?>" data-dmax="<?= (int) $s['days_max'] ?>" data-label="<?= e($garment . ' — ' . $s['name']) ?>">
                                        <div class="uc-alt-info"><strong><?= e($s['name']) ?></strong><?php if ($s['description']): ?><small><?= e($s['description']) ?></small><?php endif; ?></div>
                                        <div class="uc-alt-meta"><span><?= e(alteration_price_range((float) $s['price_min'], (float) $s['price_max'])) ?></span><small><i class="bi bi-clock"></i> <?= e(alteration_days_range((int) $s['days_min'], (int) $s['days_max'])) ?></small></div>
                                        <div class="uc-qty uc-qty--sm"><button type="button" data-alt-step="-1" aria-label="Fewer">−</button><input type="number" name="svc[<?= (int) $s['id'] ?>]" value="<?= (int) $q ?>" min="0" max="10" aria-label="Quantity for <?= e($s['name']) ?>"><button type="button" data-alt-step="1" aria-label="More">+</button></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <aside class="uc-alt-side">
                <div class="uc-summary-card">
                    <h2 class="h5">Your estimate</h2>
                    <ul class="uc-alt-chosen" data-alt-chosen><li class="text-muted small">No services selected yet.</li></ul>
                    <dl class="uc-totals">
                        <dt>Estimated price</dt><dd data-alt-price>—</dd>
                        <dt>Pickup</dt><dd data-alt-pickup>—</dd>
                        <dt class="is-total">Estimated ready in</dt><dd class="is-total" data-alt-days>—</dd>
                    </dl>
                    <p class="small text-muted" data-alt-readyby></p>

                    <h3 class="h6 mt-4">Step 2 — Your details</h3>
                    <label class="uc-field"><span>Full name *</span><input type="text" name="name" required value="<?= e(old('name', $customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : '')) ?>"></label>
                    <label class="uc-field"><span>Phone / WhatsApp *</span><input type="tel" name="phone" required value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" placeholder="03XX XXXXXXX"></label>
                    <label class="uc-field"><span>Email (for updates)</span><input type="email" name="email" value="<?= e(old('email', $customer['email'] ?? '')) ?>"></label>
                    <label class="uc-field"><span>City *</span><input type="text" name="city" required value="<?= e(old('city')) ?>"></label>
                    <div class="uc-field"><span>How will you send it?</span>
                        <label class="uc-ship-opt"><input type="radio" name="service_method" value="drop_off"<?= old('service_method', 'drop_off') === 'drop_off' ? ' checked' : '' ?>><span><strong>Drop off at store</strong><small><?= e(setting('alteration_dropoff_note', setting('store_address'))) ?></small></span></label>
                        <label class="uc-ship-opt"><input type="radio" name="service_method" value="pickup"<?= old('service_method') === 'pickup' ? ' checked' : '' ?>><span><strong>Courier pickup &amp; return</strong><small><?= (float) setting('alteration_pickup_fee', 0) > 0 ? e(money(setting('alteration_pickup_fee'))) . ' — ' : '' ?>we collect and return it</small></span></label>
                    </div>
                    <label class="uc-field" data-alt-address<?= old('service_method') === 'pickup' ? '' : ' hidden' ?>><span>Pickup address *</span><input type="text" name="address" value="<?= e(old('address')) ?>"></label>
                    <label class="uc-field"><span>Preferred date</span><input type="date" name="preferred_date" min="<?= date('Y-m-d') ?>" value="<?= e(old('preferred_date')) ?>"></label>
                    <label class="uc-field"><span>Describe what you need</span><textarea name="notes" rows="3" maxlength="2000" placeholder="e.g. Wedding on 15th — take in waist 1 inch, shorten sleeves 1.5 inch"><?= e(old('notes')) ?></textarea></label>
                    <label class="uc-field"><span>Photos (optional, up to 4)</span><input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"></label>
                    <button type="submit" class="uc-btn uc-btn--block uc-btn--lg">Send Request</button>
                    <p class="small text-muted mt-2 mb-0">No payment now — you pay after we confirm the final price.</p>
                </div>
            </aside>
        </form>
    </div>
</section>
<?php clear_old(); require UC_ROOT . '/includes/footer.php';
