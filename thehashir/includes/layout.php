<?php
/**
 * Page shell: <head>, header, footer.
 * Usage: page_start(array('title' => ..., 'description' => ...)); ...content... page_end();
 */

function nav_data()
{
    static $d = null;
    if ($d === null) {
        $d = array(
            'roles' => get_org_roles(false),
            'programs' => get_categories(2, false),
            'services' => get_categories(3, false),
            'events' => get_categories(1, false),
        );
    }
    return $d;
}

function page_start($meta = array())
{
    global $SOCIAL_LINKS, $TICKER_ITEMS;
    $defaults = array(
        'title' => setting('seo_title'),
        'description' => setting('seo_description'),
        'canonical' => current_path(),
        'image' => setting('seo_image') !== '' ? media(setting('seo_image')) : media('images/logos/hashirLogosite.png'),
        'type' => 'website',
        'noindex' => false,
        'jsonld' => array(),
        'body_class' => '',
        'active' => '',
    );
    $m = array_merge($defaults, $meta);
    $GLOBALS['PAGE_META'] = $m;
    $canonical = abs_url($m['canonical']);
    $ogImage = $m['image'];
    if ($ogImage !== '' && !preg_match('#^https?://#', $ogImage)) {
        $ogImage = abs_url($ogImage);
    }
    $nav = nav_data();
    $cityName = isset($_COOKIE['th_city_name']) ? mb_substr(clean_text($_COOKIE['th_city_name']), 0, 40, 'UTF-8') : '';
    $active = $m['active'];
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($m['title']) ?></title>
<meta name="description" content="<?= e($m['description']) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (BETA_MODE): ?><meta name="robots" content="noindex, nofollow">
<?php elseif ($m['noindex']): ?><meta name="robots" content="noindex, follow">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:type" content="<?= e($m['type']) ?>">
<meta property="og:title" content="<?= e($m['title']) ?>">
<meta property="og:description" content="<?= e($m['description']) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#005957">
<link rel="icon" href="<?= e(setting('favicon_url') !== '' ? media(setting('favicon_url')) : media('images/logos/hashirLogosite.png')) ?>">
<?php if (setting('head_code_verification') !== ''): ?><meta name="google-site-verification" content="<?= e(setting('head_code_verification')) ?>">
<?php endif; ?>
<?php if (setting('ga4_id') !== '' && !BETA_MODE): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(setting('ga4_id')) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e(setting('ga4_id')) ?>');</script>
<?php endif; ?>
<link rel="preload" href="<?= e(url('/assets/vendor/fonts/inter-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/select2/select2.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<?php
    $ld = array_merge(array(array(
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'url' => SITE_URL,
        'logo' => abs_url('/images/logos/hashirLogo.png'),
        'email' => CONTACT_EMAIL,
        'telephone' => CONTACT_PHONE_E164,
        'sameAs' => array_values($SOCIAL_LINKS),
    )), $m['jsonld']);
    foreach ($ld as $block) {
        echo '<script type="application/ld+json">' . json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
?>
</head>
<body class="<?= e($m['body_class']) ?>" data-base="<?= e(BASE_PATH) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php if (BETA_MODE) { beta_bar(); } ?>
<?php $ann = setting('announcement'); if (!empty($ann['enabled']) && trim($ann['text']) !== ''): $annHref = $ann['link'] !== '' ? (preg_match('#^/#', $ann['link']) ? url($ann['link']) : safe_url($ann['link'])) : ''; ?>
<div class="site-announcement is-<?= e($ann['style']) ?>" role="region" aria-label="Announcement"><i class="bi bi-megaphone-fill" aria-hidden="true"></i><span><?= e($ann['text']) ?></span><?php if ($annHref): ?><a href="<?= e($annHref) ?>">Learn more <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?></div>
<?php endif; ?>

<div class="topbar">
  <button type="button" class="loc-pill" data-bs-toggle="modal" data-bs-target="#cityModal" aria-label="Choose your city">
    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
    <span data-city-label><?= $cityName !== '' ? e($cityName) : 'Choose your city' ?></span>
  </button>
  <div class="ticker" role="region" aria-label="Highlights">
    <div class="ticker-track">
      <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <ul class="ticker-list"<?= $copy ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($TICKER_ITEMS as $t): ?>
        <li><a href="<?= e(url($t[1])) ?>"<?= $copy ? ' tabindex="-1"' : '' ?>><?= e($t[0]) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endfor; ?>
    </div>
  </div>
</div>

<header class="site-header">
  <nav class="navbar navbar-expand-xl" aria-label="Main">
    <div class="container-xl">
      <a class="navbar-brand" href="<?= e(url('/')) ?>" aria-label="<?= e(SITE_NAME) ?> home">
        <?php logo_img(); ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Open menu">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <div class="offcanvas offcanvas-end" tabindex="-1" id="mainNav" aria-labelledby="mainNavLabel">
        <div class="offcanvas-header">
          <span class="offcanvas-title" id="mainNavLabel"><?php logo_img(); ?></span>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
        </div>
        <div class="offcanvas-body">
          <ul class="navbar-nav main-nav mx-xl-auto">
            <li class="nav-item"><a class="nav-link<?= $active === 'about' ? ' active' : '' ?>" href="<?= e(url('/about')) ?>">About</a></li>
            <li class="nav-item"><a class="nav-link<?= $active === 'mosques' ? ' active' : '' ?>" href="<?= e(url('/mosques')) ?>">Find a Mosque</a></li>
            <li class="nav-item dropdown">
              <button class="nav-link dropdown-toggle<?= $active === 'orgs' ? ' active' : '' ?>" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">Organizations</button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item fw-semibold" href="<?= e(url('/organizations-catalog')) ?>">All organizations</a></li>
                <li><hr class="dropdown-divider"></li>
                <?php foreach ($nav['roles'] as $r): ?>
                <li><a class="dropdown-item" href="<?= e(url(org_role_url($r))) ?>"><?= e($r['role_title']) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </li>
            <li class="nav-item dropdown dropdown-mega">
              <button class="nav-link dropdown-toggle<?= $active === 'community' ? ' active' : '' ?>" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">Community Services</button>
              <div class="dropdown-menu mega-menu">
                <div class="mega-grid">
                  <div>
                    <a class="mega-title" href="<?= e(url('/program-catalog')) ?>"><i class="bi bi-journal-bookmark" aria-hidden="true"></i> Programs</a>
                    <ul class="list-unstyled">
                      <?php foreach ($nav['programs'] as $c): ?>
                      <li><a class="dropdown-item" href="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></a></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <div>
                    <a class="mega-title" href="<?= e(url('/services-catalog')) ?>"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Services</a>
                    <ul class="list-unstyled">
                      <?php foreach ($nav['services'] as $c): ?>
                      <li><a class="dropdown-item" href="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></a></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <div>
                    <a class="mega-title" href="<?= e(url('/events-catalog')) ?>"><i class="bi bi-calendar-event" aria-hidden="true"></i> Events</a>
                    <ul class="list-unstyled">
                      <?php foreach ($nav['events'] as $c): ?>
                      <li><a class="dropdown-item" href="<?= e(url(category_url($c))) ?>"><?= e($c['name']) ?></a></li>
                      <?php endforeach; ?>
                    </ul>
                    <a class="mega-title mt-3" href="<?= e(url('/prayer-times')) ?>"><i class="bi bi-clock" aria-hidden="true"></i> Prayer Times</a>
                    <a class="mega-title" href="<?= e(url('/blogs')) ?>"><i class="bi bi-newspaper" aria-hidden="true"></i> City Guides</a>
                  </div>
                </div>
              </div>
            </li>
            <li class="nav-item"><a class="nav-link<?= $active === 'solutions' ? ' active' : '' ?>" href="<?= e(url('/solution-mosque')) ?>">Solutions for Mosques</a></li>
          </ul>
          <div class="dropdown account-menu">
            <button class="account-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
              <i class="bi bi-person-circle" aria-hidden="true"></i><span class="d-xl-none ms-2">Account</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e(url(PORTAL_PATH . '/login')) ?>"><i class="bi bi-building-gear me-2" aria-hidden="true"></i>Mosque &amp; organization login</a></li>
              <li><a class="dropdown-item" href="<?= e(url('/mosque-map')) ?>"><i class="bi bi-geo-alt me-2" aria-hidden="true"></i>Mosques near me</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= e(url('/solution-mosque#get-started')) ?>"><i class="bi bi-building-add me-2" aria-hidden="true"></i>Add or claim your mosque</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </nav>
</header>
<main id="main">
<?php
}

/** Slim bar shown only in beta mode: feedback + link to the same page on the current site. */
function beta_bar()
{
    $path = current_path();
    $newOnly = array('/search', '/setup-check');
    $live = in_array($path, $newOnly, true) ? SITE_URL . '/' : SITE_URL . $path . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
    $feedback = url('/support?topic=suggestion&about=' . rawurlencode('Beta feedback: ' . $path) . '#contact');
    ?>
<div class="beta-bar" data-beta-bar role="region" aria-label="Beta notice">
  <span class="beta-tag">Beta</span>
  <span class="beta-text">You're previewing the new The Hashir. Something not right?</span>
  <a href="<?= e($feedback) ?>">Send feedback</a>
  <span aria-hidden="true">·</span>
  <a href="<?= e($live) ?>">Switch to the current site</a>
  <button type="button" class="beta-close" data-beta-close aria-label="Hide this notice"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
<?php
}

function logo_img($class = 'site-logo')
{
    $src = function_exists('setting') && setting('logo_url') !== '' ? media(setting('logo_url')) : media('images/logos/hashirLogo.png');
    echo '<img class="' . e($class) . '" src="' . e($src) . '" alt="' . e(SITE_NAME) . '" width="170" height="40"'
        . ' onerror="this.onerror=null;this.src=\'' . e(asset('img/logo.svg')) . '\'">';
}

function social_icon_class($key)
{
    $map = array('facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'youtube' => 'bi-youtube', 'tiktok' => 'bi-tiktok', 'x' => 'bi-twitter-x', 'linkedin' => 'bi-linkedin');
    return isset($map[$key]) ? $map[$key] : 'bi-link-45deg';
}

function legal_pages()
{
    $out = array();
    foreach (page_docs() as $slug => $d) {
        if ($d['status'] === 'published' && !empty($d['in_footer'])) {
            $out[$slug] = $d['title'];
        }
    }
    return $out;
}

function page_end($extraScripts = '')
{
    global $SOCIAL_LINKS;
    ?>
</main>

<footer class="site-footer">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-4">
        <a href="<?= e(url('/')) ?>" class="d-inline-block mb-3" aria-label="<?= e(SITE_NAME) ?> home"><?php logo_img('site-logo footer-logo'); ?></a>
        <p class="footer-about"><?= e(setting('footer_about')) ?></p>
        <p class="footer-label">For queries and questions contact</p>
        <ul class="list-unstyled footer-contact">
          <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li>
          <?php if (setting('contact_phone') !== ''): ?><li><i class="bi bi-telephone" aria-hidden="true"></i><a href="<?= e(tel_href(setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
        </ul>
        <div class="social-row">
          <?php foreach ($SOCIAL_LINKS as $k => $u): ?>
          <a class="social-circle" href="<?= e($u) ?>" target="_blank" rel="noopener" aria-label="The Hashir on <?= e(ucfirst($k)) ?>"><i class="bi <?= e(social_icon_class($k)) ?>" aria-hidden="true"></i></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3 offset-lg-1">
        <h2 class="footer-heading">Quick link</h2>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= e(url('/about')) ?>">About Us</a></li>
          <li><a href="<?= e(url('/blogs')) ?>">Blogs</a></li>
          <li><a href="<?= e(url('/program-catalog')) ?>">Programs</a></li>
          <li><a href="<?= e(url('/services-catalog')) ?>">Services</a></li>
          <li><a href="<?= e(url('/events-catalog')) ?>">Events</a></li>
          <li><a href="<?= e(url('/mosques')) ?>">Mosques</a></li>
          <li><a href="<?= e(url('/prayer-times')) ?>">Prayer Times</a></li>
          <li><a href="<?= e(url('/support')) ?>">Support</a></li>
          <li><a href="<?= e(url('/solution-mosque')) ?>">Marketing</a></li>
        </ul>
      </div>
      <div class="col-lg-4">
        <div class="footer-form-card">
          <span class="accent-bar" aria-hidden="true"></span>
          <h2 class="footer-form-title" id="contactTitle">Drop a suggestion or ask a question.</h2>
          <p class="text-muted small mb-4">Get in touch</p>
          <?php contact_form('footer', 'general', '', true); ?>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p class="mb-0">&copy; <?= date('Y') ?> <a href="<?= e(COPYRIGHT_URL) ?>" target="_blank" rel="noopener"><?= e(COPYRIGHT_HOLDER) ?></a> All rights reserved.</p>
      <?php $legal = legal_pages(); if ($legal): ?>
      <ul class="list-inline mb-0">
        <?php foreach ($legal as $slug => $label): ?>
        <li class="list-inline-item"><a href="<?= e(url('/' . $slug)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <p class="footer-disclaimer">Prayer times, programs and contact details are provided by each mosque or organization and can change. Please confirm with the mosque before travelling.</p>
  </div>
</footer>

<!-- City picker -->
<div class="modal fade" id="cityModal" tabindex="-1" aria-labelledby="cityModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="cityModalTitle">Choose your city</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div data-city-step="pick">
          <button type="button" class="btn btn-outline-primary w-100 mb-3" data-use-location>
            <i class="bi bi-crosshair me-2" aria-hidden="true"></i>Use my current location
          </button>
          <p class="small text-muted mb-2" data-location-status role="status"></p>
          <label for="citySearchInput" class="form-label">Or search for a city</label>
          <input type="search" id="citySearchInput" class="form-control" placeholder="e.g. Mississauga, Houston" autocomplete="off" data-city-search>
          <div class="list-group mt-2 city-results" data-city-results></div>
        </div>
        <div data-city-step="done" hidden>
          <p class="mb-3">Showing listings for <strong data-city-done-name></strong>.</p>
          <div class="d-grid gap-2">
            <a class="btn btn-primary" data-city-link="mosques" href="#">Mosques in this city</a>
            <a class="btn btn-outline-primary" data-city-link="prayer" href="#">Prayer times in this city</a>
            <button type="button" class="btn btn-link" data-city-reset>Choose a different city</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Share -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="shareModalTitle">Share this page</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-grid gap-2">
        <a class="btn btn-light text-start" data-share="whatsapp" target="_blank" rel="noopener" href="#"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>WhatsApp</a>
        <a class="btn btn-light text-start" data-share="facebook" target="_blank" rel="noopener" href="#"><i class="bi bi-facebook me-2" aria-hidden="true"></i>Facebook</a>
        <a class="btn btn-light text-start" data-share="x" target="_blank" rel="noopener" href="#"><i class="bi bi-twitter-x me-2" aria-hidden="true"></i>X</a>
        <a class="btn btn-light text-start" data-share="email" href="#"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Email</a>
        <button type="button" class="btn btn-light text-start" data-share="copy"><i class="bi bi-link-45deg me-2" aria-hidden="true"></i>Copy link</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/select2/select2.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= $extraScripts ?>
</body>
</html>
<?php
}

function contact_form($id, $defaultTopic = '', $prefill = '', $simple = false)
{
    $topics = array(
        'general' => 'General question',
        'correction' => 'Report incorrect information',
        'listing' => 'Add or claim a mosque / organization',
        'demo' => 'Book a demo of Hashir for mosques',
        'suggestion' => 'Suggestion or feedback',
    );
    ?>
<form class="contact-form" method="post" action="<?= e(url('/api/contact')) ?>" novalidate data-contact-form>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="page" value="<?= e(current_path()) ?>">
  <div class="hp-field" aria-hidden="true"><label for="<?= e($id) ?>-website">Leave this empty</label><input type="text" id="<?= e($id) ?>-website" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="row g-3">
    <div class="col-sm-6">
      <label class="form-label" for="<?= e($id) ?>-name">Your name</label>
      <input class="form-control line-input" id="<?= e($id) ?>-name" name="name" required maxlength="100" autocomplete="name" placeholder="e.g. Hashir">
    </div>
    <div class="col-sm-6">
      <label class="form-label" for="<?= e($id) ?>-email">Email address</label>
      <input class="form-control line-input" id="<?= e($id) ?>-email" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="you@example.com">
    </div>
    <?php if ($simple): ?><input type="hidden" name="topic" value="<?= e($defaultTopic ?: 'general') ?>"><?php else: ?>
    <div class="col-12">
      <label class="form-label" for="<?= e($id) ?>-topic">Topic</label>
      <select class="form-select line-input" id="<?= e($id) ?>-topic" name="topic">
        <?php foreach ($topics as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $k === $defaultTopic ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-12">
      <label class="form-label" for="<?= e($id) ?>-message">Message</label>
      <textarea class="form-control line-input" id="<?= e($id) ?>-message" name="message" rows="3" required maxlength="3000" placeholder="Tell us more…"><?= e($prefill) ?></textarea>
    </div>
  </div>
  <div class="d-flex justify-content-end mt-3">
    <button class="btn btn-primary px-4" type="submit">Send message</button>
  </div>
</form>
<?php
}
