<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$post = row('SELECT * FROM posts WHERE slug = ? AND is_active = 1', [$_GET['slug'] ?? '']);
if (!$post) {
    require __DIR__ . '/404.php';
    exit;
}
$recent = active('posts', 'AND id <> ? ORDER BY published_at DESC, id DESC LIMIT 4', [$post['id']]);

$page_title = $post['title'];
$page_desc  = $post['excerpt'] ?: excerpt($post['content'], 155);
$page_image = $post['image'];
require __DIR__ . '/partials/header.php';

page_hero($post['title'], trim(($post['category'] ? $post['category'] . ' · ' : '') . nice_date($post['published_at']) . ($post['author'] ? ' · by ' . $post['author'] : '')), ['Blog' => 'blog.php', 'Article' => ''], 'Blog');
?>
<section class="section">
  <div class="container detail-grid">
    <article>
      <div class="detail-cover" data-reveal="zoom"><?= thumb($post['image'], $post['title'], '', 'fa-solid fa-newspaper') ?></div>
      <div class="prose" data-reveal="up"><?= rich($post['content']) ?></div>
      <div class="socials" style="margin-top:40px" data-reveal="up">
        <?php $share = urlencode(url('post.php?slug=' . $post['slug'])); ?>
        <strong style="margin-right:10px;align-self:center">Share:</strong>
        <a style="background:var(--dark)" href="https://www.facebook.com/sharer/sharer.php?u=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a style="background:var(--dark)" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
        <a style="background:var(--dark)" href="https://wa.me/?text=<?= $share ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
      </div>
    </article>
    <aside>
      <?php if ($recent): ?>
      <div class="sidebar-box" data-reveal="right">
        <h4>Recent Articles</h4>
        <ul class="side-links">
          <?php foreach ($recent as $r): ?><li><a href="<?= e(url('post.php?slug=' . $r['slug'])) ?>"><?= e($r['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <div class="sidebar-box dark-box" data-reveal="right" style="--d:.1s">
        <h4>Have a project in mind?</h4>
        <p>Get a free consultation with our experts.</p>
        <a href="<?= e(url('contact.php')) ?>" class="btn btn-block">Contact Us</a>
      </div>
    </aside>
  </div>
</section>
<?php
section_cta();
require __DIR__ . '/partials/footer.php';
