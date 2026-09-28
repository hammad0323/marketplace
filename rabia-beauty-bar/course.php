<?php
require __DIR__ . '/config.php';

$course = q('SELECT * FROM courses WHERE slug = ? AND is_active = 1', [$_GET['slug'] ?? ''])->fetch();
if (!$course) {
    http_response_code(404);
    redirect('courses.php');
}

$errors = [];
$done   = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = post('name');
    $phone = post('phone');
    $email = post('email');
    $city  = post('city');
    $msg   = post('message');

    if (!csrf_ok())                                        $errors[] = 'Your session expired — please submit the form again.';
    if (post('website') !== '')                            $errors[] = 'Spam check failed.';
    if (mb_strlen($name) < 2)                              $errors[] = 'Please enter your full name.';
    if (!preg_match('/^[0-9+\-\s()]{10,20}$/', $phone))    $errors[] = 'Please enter a valid phone number.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if (!$errors) {
        q('INSERT INTO enrollments (course_id, course_title, name, phone, email, city, message) VALUES (?,?,?,?,?,?,?)',
          [$course['id'], $course['title'], $name, $phone, $email, $city, $msg]);
        $done = true;
    }
}

$includes   = array_filter(array_map('trim', preg_split('/\r\n|\r|\n|\\\\n/', (string) $course['includes'])));
$pageTitle  = $course['title'];
$metaDescription = $course['tagline'];
$activeNav  = 'courses';
$heroTitle  = $course['title'];
$heroScript = $course['tagline'];
$heroText   = '';
$heroImage  = $course['image'] ?: 'assets/img/brushes.jpg';
require __DIR__ . '/inc/header.php';
require __DIR__ . '/inc/page-hero.php';
?>
<section class="section">
    <div class="container two-col">
        <div>
            <div class="course-detail-img reveal-zoom"><img src="<?= img($course['image']) ?>" alt="<?= e($course['title']) ?>"></div>
            <div class="reveal">
                <p class="eyebrow">About this course</p>
                <h2 class="section-title" style="font-size:40px"><?= e($course['title']) ?></h2>
                <p><?= nl2br(e($course['description'])) ?></p>
                <?php if ($includes): ?>
                    <h3>What you'll learn</h3>
                    <ul class="includes">
                        <?php foreach ($includes as $inc): ?><li><i class="fa-solid fa-circle-check"></i><?= e($inc) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <aside class="sticky">
            <div class="card reveal-right">
                <h3 style="font-size:28px">Course Details</h3>
                <ul class="facts">
                    <?php foreach ([
                        'Duration'   => $course['duration'],
                        'Classes'    => $course['schedule'],
                        'Timing'     => $course['timing'],
                        'Starting'   => $course['start_date'],
                        'Seats'      => $course['seats'] ? $course['seats'] . ' only' : '',
                        'Includes'   => $course['extras'],
                    ] as $k => $v): if ($v === '' || $v === null) continue; ?>
                        <li><span><?= $k ?></span><span><?= e($v) ?></span></li>
                    <?php endforeach; ?>
                    <li><span>Course fee</span><span class="fee" style="font-size:24px"><?= money($course['fee'], 'Contact us') ?></span></li>
                </ul>

                <div id="enroll"></div>
                <?php if ($done): ?>
                    <div class="success-box">
                        <div class="big-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                        <h3>Thank you, <?= e($name) ?>!</h3>
                        <p>Your enrollment request has been received. Our team will call you shortly to confirm your seat.</p>
                        <a class="btn btn-primary" href="<?= e(whatsapp_link("Hi! I just requested enrollment in \"{$course['title']}\". My name is {$name}.")) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Confirm on WhatsApp</a>
                    </div>
                <?php else: ?>
                    <h3 style="font-size:24px">Enroll Now — Limited Seats</h3>
                    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
                    <form method="post" action="course.php?slug=<?= e($course['slug']) ?>#enroll">
                        <?= csrf_field() ?>
                        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                        <label>Full name *<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
                        <div class="form-row">
                            <label>Phone / WhatsApp *<input name="phone" type="tel" required placeholder="03xx xxxxxxx" value="<?= e($_POST['phone'] ?? '') ?>"></label>
                            <label>City<input name="city" value="<?= e($_POST['city'] ?? 'Karachi') ?>"></label>
                        </div>
                        <label>Email<input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
                        <label>Message<textarea name="message" rows="3" placeholder="Any questions about the course?"><?= e($_POST['message'] ?? '') ?></textarea></label>
                        <button class="btn btn-primary btn-block"><i class="fa-solid fa-paper-plane"></i> Send Enrollment Request</button>
                    </form>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
