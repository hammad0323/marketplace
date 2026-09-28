<?php
/**
 * Reusable page sections. Each function echoes one complete section so
 * the home page and inner pages can share them.
 */
if (!defined('ROOT_PATH')) {
    exit;
}

/** Heading with word-by-word reveal. */
function split_heading(string $text, string $tag = 'h2', string $highlight = ''): string
{
    $words = preg_split('~\s+~', trim($text));
    $hl = array_map('strtolower', preg_split('~\s+~', trim($highlight)));
    $out = '';
    // Admins can mark highlighted words themselves: "Grow *faster* online".
    $marked = str_contains($text, '*');
    $inMark = false;
    foreach ($words as $i => $w) {
        if ($marked) {
            $start = str_starts_with($w, '*');
            $isHl = $inMark || $start;
            $inMark = $isHl && !str_ends_with(rtrim($w, '.,!?'), '*');
            $w = str_replace('*', '', $w);
        } else {
            $isHl = $highlight !== '' && in_array(strtolower(trim($w, '.,!?')), $hl, true);
        }
        $cls = $isHl ? ' class="text-grad"' : '';
        $out .= '<span class="w"><span style="--i:' . $i . '"' . $cls . '>' . e($w) . '</span></span> ';
    }
    return '<' . $tag . ' class="split" data-split>' . trim($out) . '</' . $tag . '>';
}

function page_hero(string $title, string $subtitle = '', array $crumbs = [], string $bigWord = ''): void
{
    ?>
    <section class="page-hero">
      <div class="hero-bg" data-parallax="0.35">
        <div class="blob blob-1"></div><div class="blob blob-2"></div>
        <div class="grid-lines"></div><div class="noise"></div>
      </div>
      <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb" data-reveal="down">
          <a href="<?= e(url()) ?>">Home</a>
          <?php foreach ($crumbs as $label => $href): ?>
            <i class="fa-solid fa-chevron-right"></i>
            <?php if ($href): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php else: ?><span><?= e($label) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <?= split_heading($title, 'h1') ?>
        <?php if ($subtitle !== ''): ?><p data-reveal="up" style="--d:.3s"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <div class="big-word" data-parallax="-0.15" aria-hidden="true"><?= e(strtoupper($bigWord ?: $title)) ?></div>
    </section>
    <?php
}

function section_services(?int $limit = null, bool $withHead = true): void
{
    $services = active('services', 'ORDER BY sort_order, id' . ($limit ? ' LIMIT ' . (int) $limit : ''));
    if (!$services) {
        return;
    }
    ?>
    <section class="section" id="services">
      <div class="container">
        <?php if ($withHead): ?>
        <div class="section-head">
          <span class="eyebrow" data-reveal="up">Our Services</span>
          <?= split_heading(setting('services_heading', 'Everything you need to win online'), 'h2', 'win online') ?>
          <p data-reveal="up" style="--d:.2s"><?= e(setting('services_subheading')) ?></p>
        </div>
        <?php endif; ?>
        <div class="grid g-4">
          <?php foreach ($services as $i => $s): ?>
            <a href="<?= e(url('service.php?slug=' . $s['slug'])) ?>" class="svc-card tilt" data-reveal="up" style="--d:<?= ($i % 4) * 0.1 ?>s">
              <span class="svc-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span class="svc-icon"><i class="<?= e($s['icon']) ?>"></i></span>
              <h3><?= e($s['title']) ?></h3>
              <p><?= e($s['short_desc']) ?></p>
              <span class="link-arrow">Learn more <i class="fa-solid fa-arrow-right"></i></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function section_bands(): void
{
    $words = lines(setting('marquee_words'));
    if (!$words) {
        return;
    }
    $row = '';
    foreach ($words as $w) {
        $row .= '<span>' . e($w) . '</span>';
    }
    ?>
    <div class="bands" aria-hidden="true">
      <div class="band band-2"><div class="band-track"><?= $row . $row . $row . $row ?></div></div>
      <div class="band band-1"><div class="band-track"><?= $row . $row . $row . $row ?></div></div>
    </div>
    <?php
}

function section_stats(): void
{
    $stats = active('stats', 'ORDER BY sort_order, id');
    if (!$stats) {
        return;
    }
    ?>
    <section class="stats-band">
      <div class="parallax-bg" data-parallax="0.3"></div>
      <div class="container">
        <div class="grid g-4">
          <?php foreach ($stats as $i => $s): ?>
            <div class="stat" data-reveal="up" style="--d:<?= $i * 0.12 ?>s">
              <?php if ($s['icon']): ?><i class="<?= e($s['icon']) ?>"></i><?php endif; ?>
              <strong><span data-count="<?= (int) $s['value'] ?>">0</span><?= e($s['suffix']) ?></strong>
              <span><?= e($s['label']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function section_about(bool $full = false): void
{
    $img = setting('about_image');
    ?>
    <section class="section" id="about">
      <div class="container about-grid">
        <div class="about-visual" data-reveal="left">
          <div class="av-main" data-parallax-inner>
            <?php if ($img): ?>
              <img src="<?= e(media($img)) ?>" alt="About <?= e(setting('site_name')) ?>" loading="lazy" data-parallax="-0.08">
            <?php else: ?>
              <div class="code-lines" data-parallax="-0.06">
                <div><b>const</b> <em>webanza</em> = {</div>
                <div>&nbsp;&nbsp;design: <u>'pixel-perfect'</u>,</div>
                <div>&nbsp;&nbsp;code: <u>'fast &amp; secure'</u>,</div>
                <div>&nbsp;&nbsp;apps: [<u>'iOS'</u>, <u>'Android'</u>],</div>
                <div>&nbsp;&nbsp;seo: <u>'page #1'</u>,</div>
                <div>&nbsp;&nbsp;support: <b>true</b>,</div>
                <div>};</div>
                <div>&nbsp;</div>
                <div><em>webanza</em>.<b>grow</b>(<u>yourBusiness</u>);</div>
                <div style="color:var(--accent)">// → 🚀 launched</div>
              </div>
            <?php endif; ?>
          </div>
          <div class="rotating-badge">
            <svg viewBox="0 0 140 140" aria-hidden="true">
              <defs><path id="circlePath" d="M70,70 m-52,0 a52,52 0 1,1 104,0 a52,52 0 1,1 -104,0"/></defs>
              <text><textPath href="#circlePath"><?= e(strtoupper(setting('site_name', 'Webanza Tech'))) ?> • DIGITAL AGENCY •</textPath></text>
            </svg>
            <img src="<?= e(media(setting('favicon', 'assets/img/favicon.png'))) ?>" alt="">
          </div>
          <div class="av-card" data-parallax="0.08">
            <?php $first = active('stats', 'ORDER BY sort_order, id LIMIT 1')[0] ?? null; ?>
            <?php if ($first): ?>
              <strong><span data-count="<?= (int) $first['value'] ?>">0</span><?= e($first['suffix']) ?></strong>
              <span><?= e($first['label']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <span class="eyebrow" data-reveal="up"><?= e(setting('about_eyebrow', 'Who We Are')) ?></span>
          <?= split_heading(setting('about_title'), 'h2', 'growth') ?>
          <?php foreach (lines(setting('about_text')) as $i => $para): ?>
            <p class="muted" data-reveal="up" style="--d:<?= 0.1 + $i * 0.1 ?>s"><?= e($para) ?></p>
            <?php if (!$full) { break; } ?>
          <?php endforeach; ?>
          <ul class="check-list">
            <?php foreach (lines(setting('about_points')) as $i => $pt): ?>
              <li data-reveal="left" style="--d:<?= $i * 0.1 ?>s"><i class="fa-solid fa-check"></i><?= e($pt) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($full): ?>
            <div class="mv-grid">
              <div class="mv" data-reveal="up"><h4><i class="fa-solid fa-bullseye"></i> Our Mission</h4><p><?= e(setting('mission')) ?></p></div>
              <div class="mv" data-reveal="up" style="--d:.1s"><h4><i class="fa-solid fa-eye"></i> Our Vision</h4><p><?= e(setting('vision')) ?></p></div>
            </div>
            <a href="<?= e(url('contact.php')) ?>" class="btn magnetic" data-reveal="up">Start Your Project <i class="fa-solid fa-arrow-right"></i></a>
          <?php else: ?>
            <a href="<?= e(url('about.php')) ?>" class="btn magnetic" data-reveal="up">More About Us <i class="fa-solid fa-arrow-right"></i></a>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php
}

function package_card(array $p, int $i = 0): void
{
    $features = lines($p['features']);
    $visible = 7;
    $price = (float) $p['price'];
    $save = ($p['old_price'] && $p['old_price'] > $price && $price > 0) ? round((1 - $price / $p['old_price']) * 100) : 0;
    ?>
    <div class="pkg-card<?= $p['is_featured'] ? ' featured' : '' ?>" data-reveal="up" style="--d:<?= $i * 0.12 ?>s">
      <?php if ($p['badge']): ?><span class="pkg-badge"><?= e($p['badge']) ?></span><?php endif; ?>
      <h3><?= e($p['name']) ?></h3>
      <div class="pkg-tagline"><?= e($p['tagline']) ?></div>
      <div class="pkg-price">
        <?php if ($price > 0): ?>
          <?php if (stripos((string) $p['price_suffix'], 'start') !== false): ?><small style="width:auto">From</small><?php endif; ?>
          <strong><?= e(money($price)) ?></strong>
          <?php if ($p['old_price'] && $p['old_price'] > $price): ?><del><?= e(money($p['old_price'])) ?></del><?php endif; ?>
          <?php if ($save): ?><span class="pkg-save">Save <?= $save ?>%</span><?php endif; ?>
          <small><?= e(stripos((string) $p['price_suffix'], 'start') !== false ? 'Final price depends on scope' : $p['price_suffix']) ?></small>
        <?php else: ?>
          <strong>Custom</strong>
          <small><?= e($p['price_suffix'] ?: 'Tailored quote') ?></small>
        <?php endif; ?>
      </div>
      <?php if ($p['delivery_time']): ?>
        <div class="pkg-meta"><i class="fa-regular fa-clock"></i> Delivery: <?= e($p['delivery_time']) ?></div>
      <?php endif; ?>
      <ul class="pkg-features">
        <?php foreach ($features as $fi => $f): ?>
          <li class="<?= $fi >= $visible ? 'more' : '' ?>"><i class="fa-solid fa-circle-check"></i><span><?= e($f) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($features) > $visible): ?>
        <button type="button" class="pkg-more" data-more="+ <?= count($features) - $visible ?> more features">+ <?= count($features) - $visible ?> more features</button>
      <?php endif; ?>
      <a href="<?= e(url('contact.php?package=' . $p['id'])) ?>" class="btn btn-block"><?= e($p['cta_text'] ?: 'Get Started') ?> <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <?php
}

function section_packages(bool $withHead = true, bool $dark = false): void
{
    $cats = active('package_categories', 'ORDER BY sort_order, id');
    $all = active('packages', 'ORDER BY sort_order, id');
    $byCat = [];
    foreach ($all as $p) {
        $byCat[(int) $p['category_id']][] = $p;
    }
    $cats = array_values(array_filter($cats, fn($c) => !empty($byCat[(int) $c['id']])));
    if (!$cats) {
        return;
    }
    $want = $_GET['cat'] ?? '';
    $activeSlug = $cats[0]['slug'];
    foreach ($cats as $c) {
        if ($c['slug'] === $want) {
            $activeSlug = $want;
        }
    }
    ?>
    <section class="section<?= $dark ? ' dark' : '' ?>" id="packages">
      <div class="container">
        <?php if ($withHead): ?>
        <div class="section-head">
          <span class="eyebrow" data-reveal="up">Packages &amp; Pricing</span>
          <?= split_heading(setting('packages_heading', 'Transparent packages, real results'), 'h2', 'real results') ?>
          <p data-reveal="up" style="--d:.2s"><?= e(setting('packages_subheading')) ?></p>
        </div>
        <?php endif; ?>
        <div class="pkg-tabs" role="tablist" data-reveal="up">
          <?php foreach ($cats as $c): ?>
            <button type="button" role="tab" class="pkg-tab<?= $c['slug'] === $activeSlug ? ' active' : '' ?>" data-tab="<?= e($c['slug']) ?>" aria-selected="<?= $c['slug'] === $activeSlug ? 'true' : 'false' ?>">
              <i class="<?= e($c['icon']) ?>"></i> <?= e($c['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($cats as $c): ?>
          <div class="pkg-panel<?= $c['slug'] === $activeSlug ? ' active' : '' ?>" data-panel="<?= e($c['slug']) ?>" role="tabpanel">
            <?php if ($c['description']): ?><p class="pkg-desc"><?= e($c['description']) ?></p><?php endif; ?>
            <div class="pkg-grid">
              <?php foreach ($byCat[(int) $c['id']] as $i => $p) { package_card($p, $i); } ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="pkg-note" data-reveal="up">
          <i class="fa-solid fa-wand-magic-sparkles"></i>
          <span>Need something different? We build custom packages for every budget.</span>
          <a href="<?= e(url('contact.php')) ?>" class="link-arrow">Request a custom quote <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>
    </section>
    <?php
}

function section_process(): void
{
    $steps = active('process_steps', 'ORDER BY sort_order, id');
    if (!$steps) {
        return;
    }
    ?>
    <section class="section dark" style="overflow:hidden">
      <div class="container">
        <div class="section-head">
          <span class="eyebrow" data-reveal="up">Our Process</span>
          <?= split_heading(setting('process_heading', 'How we deliver, step by step'), 'h2', 'step by step') ?>
        </div>
        <div class="process" data-reveal-self>
          <div class="process-line"><span></span></div>
          <?php foreach ($steps as $i => $s): ?>
            <div class="step" data-reveal="up" style="--d:<?= $i * 0.15 ?>s">
              <div class="step-icon"><i class="<?= e($s['icon'] ?: 'fa-solid fa-circle') ?>"></i><b><?= $i + 1 ?></b></div>
              <h4><?= e($s['title']) ?></h4>
              <p><?= e($s['description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function portfolio_item(array $p, int $i = 0): void
{
    ?>
    <a href="<?= e(url('project.php?slug=' . $p['slug'])) ?>" class="pf-item" data-cat="<?= e(slugify((string) $p['category'])) ?>" data-reveal="up" style="--d:<?= ($i % 2) * 0.15 ?>s">
      <div class="pf-media"><div data-parallax="-0.06" style="height:100%"><?= thumb($p['image'], $p['title'], (string) $p['client'], portfolio_icon((string) $p['category'])) ?></div></div>
      <div class="pf-info">
        <div><small><?= e($p['category']) ?></small><h3><?= e($p['title']) ?></h3></div>
        <span class="pf-go"><i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>
    <?php
}

function portfolio_icon(string $category): string
{
    $c = strtolower($category);
    return match (true) {
        str_contains($c, 'commerce') => 'fa-solid fa-bag-shopping',
        str_contains($c, 'app')      => 'fa-solid fa-mobile-screen-button',
        str_contains($c, 'saas')     => 'fa-solid fa-chart-pie',
        str_contains($c, 'design')   => 'fa-solid fa-palette',
        str_contains($c, 'video')    => 'fa-solid fa-clapperboard',
        str_contains($c, 'seo')      => 'fa-solid fa-ranking-star',
        default                      => 'fa-solid fa-laptop-code',
    };
}

function section_portfolio(int $limit = 4): void
{
    $items = active('portfolio', 'ORDER BY is_featured DESC, sort_order, id LIMIT ' . (int) $limit);
    if (!$items) {
        return;
    }
    ?>
    <section class="section" id="portfolio">
      <div class="container">
        <div class="head-row">
          <div class="section-head left">
            <span class="eyebrow" data-reveal="up">Portfolio</span>
            <?= split_heading(setting('portfolio_heading', 'Selected work we are proud of'), 'h2', 'proud') ?>
          </div>
          <a href="<?= e(url('portfolio.php')) ?>" class="btn btn-outline magnetic" data-reveal="left">View All Projects <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="pf-grid">
          <?php foreach ($items as $i => $p) { portfolio_item($p, $i); } ?>
        </div>
      </div>
    </section>
    <?php
}

function section_ceo(): void
{
    $photo = setting('ceo_photo');
    $name = setting('ceo_name', 'Hammad Jamil');
    ?>
    <section class="section" style="padding-top:0">
      <div class="container">
        <div class="ceo" data-reveal="zoom">
          <div class="ceo-photo" data-reveal="left">
            <?php if ($photo): ?>
              <img src="<?= e(media($photo)) ?>" alt="<?= e($name) ?>" loading="lazy">
            <?php else: ?>
              <div class="initials text-grad"><?= e(initials($name)) ?></div>
            <?php endif; ?>
            <div class="tag">
              <div><strong><?= e($name) ?></strong><small><?= e(setting('ceo_role', 'Founder & CEO')) ?></small></div>
              <?php if (setting('ceo_linkedin')): ?><a href="<?= e(setting('ceo_linkedin')) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a><?php endif; ?>
            </div>
          </div>
          <div>
            <span class="eyebrow" data-reveal="up">Message from our CEO</span>
            <blockquote data-reveal="blur" style="--d:.15s"><?= e(setting('ceo_message')) ?></blockquote>
            <div class="ceo-sign" data-reveal="up" style="--d:.3s">
              <span class="sig"><?= e($name) ?></span>
              <span class="muted">— <?= e(setting('ceo_role', 'Founder & CEO')) ?>, <?= e(setting('site_name')) ?></span>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php
}

function section_team(): void
{
    $team = active('team', 'ORDER BY sort_order, id');
    if (!$team) {
        return;
    }
    ?>
    <section class="section" style="padding-top:40px">
      <div class="container">
        <div class="section-head">
          <span class="eyebrow" data-reveal="up">Our Team</span>
          <?= split_heading(setting('team_heading', 'The people behind your success'), 'h2', 'success') ?>
        </div>
        <div class="grid g-4">
          <?php foreach ($team as $i => $m): ?>
            <div class="team-card" data-reveal="up" style="--d:<?= $i * 0.1 ?>s">
              <div class="team-photo">
                <?php if ($m['photo']): ?><img src="<?= e(media($m['photo'])) ?>" alt="<?= e($m['name']) ?>" loading="lazy"><?php else: ?><div class="initials text-grad"><?= e(initials($m['name'])) ?></div><?php endif; ?>
                <div class="team-social">
                  <?php if ($m['linkedin']): ?><a href="<?= e($m['linkedin']) ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a><?php endif; ?>
                  <?php if ($m['facebook']): ?><a href="<?= e($m['facebook']) ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><?php endif; ?>
                  <?php if ($m['email']): ?><a href="mailto:<?= e($m['email']) ?>" aria-label="Email"><i class="fa-solid fa-envelope"></i></a><?php endif; ?>
                </div>
              </div>
              <h4><?= e($m['name']) ?></h4>
              <small><?= e($m['role']) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function section_clients(): void
{
    $items = active('clients', 'ORDER BY sort_order, id');
    if (!$items) {
        return;
    }
    $chips = '';
    foreach ($items as $c) {
        $inner = $c['logo'] ? '<img src="' . e(media($c['logo'])) . '" alt="' . e($c['name']) . '">' : '<i class="' . e($c['icon'] ?: 'fa-solid fa-star') . '"></i>';
        $chips .= '<div class="logo-chip">' . $inner . '<span>' . e($c['name']) . '</span></div>';
    }
    ?>
    <section class="section-sm">
      <div class="container">
        <div class="section-head" style="margin-bottom:44px">
          <span class="eyebrow" data-reveal="up">Technologies</span>
          <?= split_heading(setting('clients_heading', 'Tools & platforms we master'), 'h2', 'master') ?>
        </div>
      </div>
      <div class="logo-marquee" data-reveal="up">
        <div class="logo-track"><?= $chips . $chips ?></div>
        <div class="logo-track rev"><?= $chips . $chips ?></div>
      </div>
    </section>
    <?php
}

function section_testimonials(): void
{
    $items = active('testimonials', 'ORDER BY sort_order, id');
    if (!$items) {
        return;
    }
    ?>
    <section class="section dark" style="overflow:hidden">
      <div class="container">
        <div class="head-row">
          <div class="section-head left">
            <span class="eyebrow" data-reveal="up">Testimonials</span>
            <?= split_heading(setting('testimonials_heading', 'What our clients say'), 'h2', 'clients') ?>
          </div>
          <div class="slider-nav" data-reveal="left">
            <button type="button" data-slide="-1" aria-label="Previous"><i class="fa-solid fa-arrow-left"></i></button>
            <button type="button" data-slide="1" aria-label="Next"><i class="fa-solid fa-arrow-right"></i></button>
          </div>
        </div>
        <div class="testi-wrap">
          <div class="testi-track" id="testiTrack">
            <?php foreach ($items as $i => $t): ?>
              <div class="testi" data-reveal="up" style="--d:<?= min($i, 3) * 0.12 ?>s">
                <i class="fa-solid fa-quote-right quote-ic"></i>
                <div class="stars"><?= str_repeat('★', max(1, min(5, (int) $t['rating']))) ?></div>
                <p><?= e($t['content']) ?></p>
                <div class="testi-person">
                  <span class="av"><?php if ($t['photo']): ?><img src="<?= e(media($t['photo'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initials($t['name'])) ?><?php endif; ?></span>
                  <div><strong><?= e($t['name']) ?></strong><small><?= e($t['role']) ?></small></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
    <?php
}

function section_faq(): void
{
    $faqs = active('faqs', 'ORDER BY sort_order, id');
    if (!$faqs) {
        return;
    }
    ?>
    <section class="section" id="faq">
      <div class="container faq-grid">
        <div class="faq-side">
          <span class="eyebrow" data-reveal="up">FAQ</span>
          <?= split_heading(setting('faq_heading', 'Frequently asked questions'), 'h2', 'questions') ?>
          <p class="muted" data-reveal="up">Everything you need to know about working with us. Can't find an answer? Just ask.</p>
          <div class="faq-contact" data-reveal="up">
            <h4>Still have questions?</h4>
            <p>Our team usually replies within a few hours.</p>
            <a href="<?= e(url('contact.php')) ?>" class="btn btn-sm">Contact Us <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </div>
        <div>
          <?php foreach ($faqs as $i => $f): ?>
            <div class="faq-item<?= $i === 0 ? ' open' : '' ?>" data-reveal="up" style="--d:<?= $i * 0.06 ?>s">
              <button type="button" class="faq-q" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($f['question']) ?><span class="ic"><i class="fa-solid fa-plus"></i></span></button>
              <div class="faq-a"><div><p><?= nl2br(e($f['answer'])) ?></p></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php
}

function post_card(array $p, int $i = 0): void
{
    ?>
    <article class="post-card" data-reveal="up" style="--d:<?= ($i % 3) * 0.12 ?>s">
      <a href="<?= e(url('post.php?slug=' . $p['slug'])) ?>" class="post-media"><?= thumb($p['image'], $p['title'], '', 'fa-solid fa-newspaper') ?></a>
      <div class="post-body">
        <div class="post-meta"><span class="cat"><?= e($p['category']) ?></span><span><i class="fa-regular fa-calendar"></i> <?= e(nice_date($p['published_at'])) ?></span></div>
        <h3><a href="<?= e(url('post.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
        <p><?= e($p['excerpt'] ?: excerpt($p['content'])) ?></p>
        <a href="<?= e(url('post.php?slug=' . $p['slug'])) ?>" class="link-arrow">Read article <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    </article>
    <?php
}

function section_blog(int $limit = 3): void
{
    $posts = active('posts', 'ORDER BY published_at DESC, id DESC LIMIT ' . (int) $limit);
    if (!$posts) {
        return;
    }
    ?>
    <section class="section" style="padding-top:40px">
      <div class="container">
        <div class="head-row">
          <div class="section-head left">
            <span class="eyebrow" data-reveal="up">Blog</span>
            <?= split_heading(setting('blog_heading', 'Insights & news'), 'h2', 'news') ?>
          </div>
          <a href="<?= e(url('blog.php')) ?>" class="btn btn-outline magnetic" data-reveal="left">All Articles <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="grid g-3">
          <?php foreach ($posts as $i => $p) { post_card($p, $i); } ?>
        </div>
      </div>
    </section>
    <?php
}

function section_cta(): void
{
    $wa = whatsapp_link('Hi, I would like a quote for my project.');
    ?>
    <section class="section" style="padding-top:20px">
      <div class="container">
        <div class="cta" data-reveal="zoom">
          <span class="cta-shape s1" data-parallax="0.2"></span>
          <span class="cta-shape s2" data-parallax="-0.2"></span>
          <span class="cta-shape s3" data-parallax="0.4"></span>
          <?= split_heading(setting('cta_title', 'Ready to build something amazing?')) ?>
          <p><?= e(setting('cta_text')) ?></p>
          <div class="cta-actions">
            <a href="<?= e(url('contact.php')) ?>" class="btn magnetic"><?= e(setting('cta_btn_text', "Let's Talk")) ?> <i class="fa-solid fa-arrow-right"></i></a>
            <?php if ($wa): ?><a href="<?= e($wa) ?>" class="btn btn-ghost magnetic" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp Us</a><?php endif; ?>
          </div>
        </div>
      </div>
    </section>
    <?php
}
