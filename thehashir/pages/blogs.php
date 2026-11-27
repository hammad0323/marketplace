<?php
/** /blogs — city guides */
$blogs = get_blogs();
$prov = str_param('province', 60);
$provinces = array();
foreach ($blogs as $b) {
    if (!empty($b['state_title'])) {
        $provinces[$b['state_title']] = isset($provinces[$b['state_title']]) ? $provinces[$b['state_title']] + 1 : 1;
    }
}
ksort($provinces);
if ($prov !== '' && isset($provinces[$prov])) {
    $blogs = array_values(array_filter($blogs, function ($b) use ($prov) { return $b['state_title'] === $prov; }));
} else {
    $prov = '';
}
page_start(array(
    'title' => 'City Guides – Best Mosques in Canadian Cities (2026) | The Hashir',
    'description' => 'Guides to the mosques and Islamic centres in ' . count($blogs) . ' Canadian cities: services, programs, Jumu\'ah and how to choose a mosque.',
    'canonical' => '/blogs',
    'noindex' => $prov !== '',
    'active' => 'community',
));
page_hero('City Guides', 'Where to pray, learn and connect — mosque guides for cities across Canada.', array(array('Home', '/'), array('City Guides', null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="city-chips mb-4" aria-label="Filter by province">
      <a class="city-chip<?= $prov === '' ? ' border-primary text-primary' : '' ?>" href="<?= e(url('/blogs')) ?>"<?= $prov === '' ? ' aria-current="true"' : '' ?>>All provinces</a>
      <?php foreach ($provinces as $p => $n): ?>
      <a class="city-chip<?= $prov === $p ? ' border-primary text-primary' : '' ?>" href="<?= e(url('/blogs?province=' . rawurlencode($p))) ?>"<?= $prov === $p ? ' aria-current="true"' : '' ?>><?= e($p) ?><span class="n"><?= (int)$n ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="row g-4">
      <?php foreach ($blogs as $b): ?>
      <div class="col-sm-6 col-lg-4"><?php blog_card($b); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php page_end(); ?>
