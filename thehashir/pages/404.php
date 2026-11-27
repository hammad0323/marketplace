<?php
http_response_code(404);
page_start(array('title' => 'Page not found | The Hashir', 'noindex' => true));
?>
<section class="section">
  <div class="container-xl">
    <?php empty_state('We couldn\'t find that page', 'The link may be old or the listing may have moved. Try searching, or browse mosques and prayer times.', array(array('Find a mosque', '/mosques'), array('Prayer times', '/prayer-times'), array('Go home', '/')), 'bi-signpost-split'); ?>
    <form class="search-card mx-auto mt-4" action="<?= e(url('/search')) ?>" method="get" role="search">
      <i class="bi bi-search" aria-hidden="true"></i>
      <label for="q404" class="visually-hidden">Search The Hashir</label>
      <input id="q404" name="q" type="search" placeholder="Search mosques, programs, services, cities…">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>
  </div>
</section>
<?php page_end(); ?>
