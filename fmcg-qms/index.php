<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect(app_url_for_role(current_role())); }

$features = [
    ['bi-diagram-3', 'Problem Solving', 'Root Cause & Problem Solving', '5 Whys, Fishbone, FTA, 8D, A3 and Pareto analysis - built as interactive digital workflows, not paper forms.'],
    ['bi-graph-up', 'SPC Analytics', 'Statistical Process Control', 'Control charts (X-bar/R, p, np, c, u), process capability (Cp/Cpk/Pp/Ppk), histograms, scatter diagrams and Gauge R&R.'],
    ['bi-shield-check', 'Food Safety', 'HACCP & Food Safety', 'HACCP plans, CCP monitoring, GMP audits, allergen control, full traceability and mock recall exercises.'],
    ['bi-truck', 'Supplier Quality', 'Supplier & Incoming Quality', 'Supplier scorecards, incoming inspection, AQL sampling, supplier audits and CoA review in one place.'],
    ['bi-clipboard2-check', 'Audits', 'Audits & Compliance', 'Internal audits, layered process audits and a configurable Compliance Framework Engine for ISO, BRCGS, SQF, IFS and more.'],
    ['bi-clipboard-data', 'CAPA', 'CAPA & NCR Workflow', 'Every issue flows through detection, containment, root cause, corrective/preventive action, verification and closure.'],
    ['bi-speedometer', 'OEE', 'Production Performance', 'Real-time OEE, scrap & rework tracking and Cost of Quality, connected directly to your production lines and shifts.'],
    ['bi-robot', 'AI Assistant', 'AI Quality Assistant', 'AI-assisted form suggestions, root-cause hypotheses, dashboard insights and a natural-language quality chat - scoped to your company data only.'],
];

$pageTitle = 'Intelligent Quality Management for FMCG Manufacturing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= out(APP_NAME) ?> - Intelligent Quality Management for FMCG Manufacturing</title>
<meta name="description" content="Digitize quality inspections, detect problems faster, manage CAPA, improve production performance and make smarter quality decisions with AI.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background:rgba(15,23,42,.85); backdrop-filter:blur(10px);">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
      <span style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#2563EB,#60A5FA);display:flex;align-items:center;justify-content:center;font-weight:800;">Q</span>
      <?= out(APP_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav1"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse justify-content-end" id="nav1">
      <ul class="navbar-nav align-items-lg-center gap-lg-3">
        <li class="nav-item"><a class="nav-link" href="#platform">Platform</a></li>
        <li class="nav-item"><a class="nav-link" href="#tools">Quality Tools</a></li>
        <li class="nav-item"><a class="nav-link" href="#ai">AI Assistant</a></li>
        <li class="nav-item"><a class="nav-link" href="#security">Security</a></li>
        <li class="nav-item"><a class="btn btn-outline-light btn-sm" href="<?= base_url('login.php') ?>">Sign In</a></li>
        <li class="nav-item"><a class="btn btn-primary btn-sm" href="#cta">Request Demo</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="lp-hero pt-5">
  <div class="container position-relative" style="padding-top:110px; padding-bottom:90px;">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <span class="badge rounded-pill" style="background:rgba(37,99,235,.18); color:#93C5FD; padding:8px 16px;">Digital QMS for FMCG Manufacturing</span>
        <h1 class="display-4 fw-bold mt-3 mb-3">Intelligent Quality Management for FMCG Manufacturing</h1>
        <p class="fs-5 text-white-50 mb-4">Digitize quality inspections, detect problems faster, manage CAPA, improve production performance and make smarter quality decisions with AI.</p>
        <div class="d-flex gap-3 flex-wrap">
          <a href="#cta" class="btn btn-primary btn-lg px-4">Request Demo</a>
          <a href="#platform" class="btn btn-outline-light btn-lg px-4">Explore Platform</a>
        </div>
        <div class="row mt-5 g-4">
          <div class="col-4"><div class="lp-stat" data-count="70">0</div><div class="text-white-50 small">Quality tools &amp; methods</div></div>
          <div class="col-4"><div class="lp-stat" data-count="12">0</div><div class="text-white-50 small">Compliance frameworks</div></div>
          <div class="col-4"><div class="lp-stat" data-count="100">0</div><div class="text-white-50 small">% company data isolation</div></div>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-block position-relative" style="height:420px;">
        <div class="floating-card" style="top:10px; left:30px; width:220px;" >
          <div class="small text-white-50">Overall Quality Score</div>
          <div class="fs-3 fw-bold text-white">94.2<span class="fs-6">%</span></div>
          <div class="small" style="color:#4ADE80;"><i class="bi bi-arrow-up"></i> 2.1% vs last month</div>
        </div>
        <div class="floating-card" style="top:170px; left:150px; width:190px; animation-delay:1s;">
          <div class="small text-white-50">Open CAPA</div>
          <div class="fs-3 fw-bold text-white">6</div>
          <div class="small" style="color:#FBBF24;">2 overdue</div>
        </div>
        <div class="floating-card" style="top:300px; left:10px; width:230px; animation-delay:2s;">
          <div class="small text-white-50 mb-1"><i class="bi bi-robot"></i> AI Insight</div>
          <div class="small text-white">Packaging contributes 43% of defects this week.</div>
        </div>
        <div class="floating-card" style="top:60px; left:-260px; width:150px; animation-delay:1.6s; display:none;"></div>
      </div>
    </div>
  </div>
</section>

<section class="lp-section" id="platform">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <h6 class="text-primary fw-bold text-uppercase small">Quality Intelligence</h6>
      <h2 class="fw-bold">One platform for the entire quality lifecycle</h2>
      <p class="text-muted mx-auto" style="max-width:620px;">Plan, produce, measure, inspect, detect, analyze, correct, prevent, verify, close and improve - all connected, all auditable.</p>
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-2 reveal">
      <?php foreach (['Plan','Produce','Measure','Inspect','Detect','Analyze','Root Cause','Correct','Prevent','Verify','Close','Improve'] as $i => $step): ?>
        <span class="badge rounded-pill px-3 py-2" style="background:#EFF6FF; color:#2563EB; font-size:.85rem;"><?= $i+1 ?>. <?= out($step) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="lp-section" style="background:#F1F5F9;" id="tools">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <h6 class="text-primary fw-bold text-uppercase small">Complete Quality Tool Library</h6>
      <h2 class="fw-bold">Every tool your quality team needs</h2>
      <p class="text-muted mx-auto" style="max-width:620px;">Built on a Dynamic Quality Tool Engine - new tools, thresholds and workflows can be added without touching code.</p>
    </div>
    <div class="row g-4">
      <?php foreach ($features as $f): ?>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="lp-feature-card">
          <div class="lp-feature-icon"><i class="bi <?= out($f[0]) ?>"></i></div>
          <div class="text-muted small fw-semibold text-uppercase mb-1"><?= out($f[1]) ?></div>
          <h5 class="fw-bold"><?= out($f[2]) ?></h5>
          <p class="text-muted small mb-0"><?= out($f[3]) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="lp-section" id="ai">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal">
        <h6 class="text-primary fw-bold text-uppercase small">AI Quality Assistant</h6>
        <h2 class="fw-bold mb-3">Every reading gets an expert second opinion</h2>
        <p class="text-muted mb-4">The AI assistant analyzes readings, defects, issues, CAPA, audits, complaints and supplier performance - scoped strictly to your company's own data - and suggests next steps instead of just showing numbers.</p>
        <ul class="list-unstyled d-flex flex-column gap-2">
          <li><i class="bi bi-check-circle-fill text-primary me-2"></i>Live suggestions while employees enter data</li>
          <li><i class="bi bi-check-circle-fill text-primary me-2"></i>Root-cause hypotheses for 5 Whys, Fishbone, 8D, A3</li>
          <li><i class="bi bi-check-circle-fill text-primary me-2"></i>Plain-language dashboard insights every day</li>
          <li><i class="bi bi-check-circle-fill text-primary me-2"></i>Natural-language quality chat for managers</li>
        </ul>
      </div>
      <div class="col-lg-6 reveal">
        <div class="qc-card">
          <div class="ai-chat-bubble user">Which department has the highest defect rate?</div>
          <div class="ai-chat-bubble ai">Production has the highest number of recorded issues this period, driven mainly by weight-related deviations on the filling line.</div>
          <div class="ai-chat-bubble user">What should we investigate first?</div>
          <div class="ai-chat-bubble ai">Start with the 2 open critical issues on Dairy Line 1 - these carry the highest risk to product safety.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="lp-section" style="background:#F1F5F9;">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <h6 class="text-primary fw-bold text-uppercase small">Quality Dashboard &amp; Reports</h6>
      <h2 class="fw-bold">From raw data to management intelligence</h2>
    </div>
    <div class="row g-4 reveal">
      <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-award"></i></div><div class="stat-value">94.2%</div><div class="stat-label">Overall Quality Score</div></div></div>
      <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div><div class="stat-value">96.1%</div><div class="stat-label">First Pass Yield</div></div></div>
      <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-speedometer"></i></div><div class="stat-value">74.3%</div><div class="stat-label">OEE</div></div></div>
      <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value">6</div><div class="stat-label">Open Critical Issues</div></div></div>
    </div>
  </div>
</section>

<section class="lp-section" id="security">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal">
        <h6 class="text-primary fw-bold text-uppercase small">Enterprise Security</h6>
        <h2 class="fw-bold mb-3">Hard multi-tenant data isolation</h2>
        <p class="text-muted">Every company-owned table is scoped by <code>company_id</code> and every access check happens server-side. MySQLi prepared statements, CSRF protection, hashed passwords, secure sessions and full audit trails are built in from day one.</p>
      </div>
      <div class="col-lg-6 reveal">
        <div class="row g-3">
          <?php foreach ([['bi-shield-lock','Company-level isolation'],['bi-key','CSRF-protected forms'],['bi-database-lock','Prepared statements everywhere'],['bi-clock-history','Full audit trail']] as $s): ?>
          <div class="col-6"><div class="lp-feature-card text-center py-4"><i class="bi <?= out($s[0]) ?> fs-3 text-primary"></i><div class="small fw-semibold mt-2"><?= out($s[1]) ?></div></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="lp-section text-center text-white" style="background:linear-gradient(135deg,#1D4ED8,#2563EB);" id="cta">
  <div class="container reveal">
    <h2 class="fw-bold mb-3">Ready to modernize your quality management?</h2>
    <p class="mb-4 text-white-75" style="max-width:560px; margin:0 auto;">See how <?= out(APP_NAME) ?> turns manual inspections into real-time quality intelligence.</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="mailto:sales@qualitycore.app" class="btn btn-light btn-lg px-4 fw-semibold">Request Demo</a>
      <a href="<?= base_url('login.php') ?>" class="btn btn-outline-light btn-lg px-4">Explore Platform</a>
    </div>
  </div>
</section>

<footer class="py-5" style="background:#0F172A; color:#94A3B8;">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="fw-bold text-white mb-2"><?= out(APP_NAME) ?></div>
        <p class="small">Intelligent Quality Management for FMCG Manufacturing - QMS, Food Safety, SPC, CAPA, Audits, Supplier Quality and AI in one platform.</p>
      </div>
      <div class="col-md-2"><div class="fw-semibold text-white small mb-2">Platform</div>
        <div class="small d-flex flex-column gap-1"><a class="text-decoration-none text-secondary" href="#tools">Quality Tools</a><a class="text-decoration-none text-secondary" href="#ai">AI Assistant</a><a class="text-decoration-none text-secondary" href="#security">Security</a></div>
      </div>
      <div class="col-md-2"><div class="fw-semibold text-white small mb-2">Company</div>
        <div class="small d-flex flex-column gap-1"><a class="text-decoration-none text-secondary" href="#">About</a><a class="text-decoration-none text-secondary" href="#">Contact</a></div>
      </div>
      <div class="col-md-4"><div class="fw-semibold text-white small mb-2">Sign in</div>
        <a href="<?= base_url('login.php') ?>" class="btn btn-sm btn-outline-light">Go to Login</a>
      </div>
    </div>
    <hr class="border-secondary my-4">
    <div class="small text-center">&copy; <?= date('Y') ?> <?= out(APP_NAME) ?>. All rights reserved.</div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<script>
document.querySelectorAll('.lp-stat').forEach(function (el) {
  var target = parseInt(el.getAttribute('data-count'), 10), cur = 0;
  var step = Math.max(1, Math.round(target / 40));
  var timer = setInterval(function () {
    cur += step;
    if (cur >= target) { cur = target; clearInterval(timer); }
    el.textContent = cur;
  }, 30);
});
</script>
</body>
</html>
