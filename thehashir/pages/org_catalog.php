<?php
/** /organizations-catalog */
$roles = get_org_roles();
page_start(array(
    'title' => 'Muslim Organizations – Charities, Schools, Food Banks & More | The Hashir',
    'description' => 'Find Muslim charities, Islamic schools, food banks, outreach organizations, halal certification bodies and student associations across Canada and the US.',
    'canonical' => '/organizations-catalog',
    'active' => 'orgs',
));
page_hero('Explore Organizations', 'Charities, Islamic schools, food banks, outreach groups and the key bodies that serve the Muslim community.', array(array('Home', '/'), array('Organizations', null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="card-grid">
      <?php foreach ($roles as $r): ?>
      <article class="cat-card<?= $r['count'] ? '' : ' is-empty' ?>">
        <div class="cat-card-media"><i class="bi <?= e(role_icon($r['role_id'])) ?>" aria-hidden="true"></i><?php if ($r['profile_image']): ?><img src="<?= e(media($r['profile_image'])) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></div>
        <div class="cat-card-body">
          <h2><a class="stretched-link" href="<?= e(url(org_role_url($r))) ?>"><?= e($r['role_title']) ?></a></h2>
          <p><?= e(clean_text($r['description'])) ?></p>
          <span class="cat-card-count"><?= $r['count'] ? e(plural($r['count'], 'organization')) : 'No listings yet' ?></span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php page_end(); ?>
