<?php
require __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
$type = mp_find_marketplace_type_by_slug('artisan');
$vendor = $type ? mp_find_vendor_by_slug($slug) : null;

if (!$vendor || (int) $vendor['marketplace_type_id'] !== (int) $type['id'] || $vendor['status'] !== 'approved') {
    require __DIR__ . '/404.php';
    return;
}

$profile = mp_find_artisan_profile($vendor['id']) ?? [];
$products = mp_products_by_vendor($vendor['id']);
$rating = mp_vendor_average_rating($vendor['id']);
$followers = mp_vendor_follower_count($vendor['id']);

$social = $profile['social_links'] ?? [];
$gallery = $profile['gallery_images'] ?? [];
$achievements = $profile['achievements'] ?? [];
$portfolio = $profile['portfolio_items'] ?? [];
$isFollowing = isset($_SESSION['customer_id']) && mp_vendor_is_followed_by($vendor['id'], $_SESSION['customer_id']);

$pageTitle = $vendor['store_name'] . ' — Artisan Story';
$theme = 'artisan';
require __DIR__ . '/header.php';
?>

<section class="artisan-story reveal">
    <span class="artisan-badge">🏺 Handmade</span>
    <?php if ($vendor['is_featured']): ?><span class="artisan-badge">Featured Artist</span><?php endif; ?>

    <h1><?= mp_e($vendor['store_name']) ?></h1>
    <p>
        ⭐ <?= $rating['average'] ?: 'No ratings yet' ?> <?= $rating['total'] ? "({$rating['total']} reviews)" : '' ?>
        &nbsp;·&nbsp; <?= $followers ?> followers
    </p>

    <form method="post" action="/follow.php?vendor_id=<?= (int) $vendor['id'] ?>" class="inline-form">
        <?= mp_csrf_field() ?>
        <input type="hidden" name="redirect_to" value="/artisan-store.php?slug=<?= mp_e($vendor['slug']) ?>">
        <button type="submit" class="btn btn-secondary"><?= $isFollowing ? 'Following ✓' : 'Follow Artist' ?></button>
    </form>

    <?php if (!empty($profile['biography'])): ?>
        <h2>Artist Biography</h2>
        <p><?= nl2br(mp_e($profile['biography'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($profile['brand_story'])): ?>
        <h2>The Story Behind the Brand</h2>
        <p><?= nl2br(mp_e($profile['brand_story'])) ?></p>
    <?php endif; ?>

    <?php if ($gallery): ?>
        <h2>Artist Gallery</h2>
        <div class="artisan-gallery">
            <?php foreach ($gallery as $image): ?><img src="<?= mp_e($image) ?>" alt="Gallery image"><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($achievements): ?>
        <h2>Achievements</h2>
        <ul>
            <?php foreach ($achievements as $achievement): ?><li><?= mp_e($achievement) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($portfolio): ?>
        <h2>Portfolio</h2>
        <div class="artisan-gallery">
            <?php foreach ($portfolio as $item): ?><img src="<?= mp_e($item['image'] ?? '') ?>" alt="<?= mp_e($item['title'] ?? '') ?>"><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (array_filter($social)): ?>
        <h2>Follow the Artist</h2>
        <p>
            <?php foreach ($social as $platform => $url): ?>
                <?php if ($url): ?><a href="<?= mp_e($url) ?>" target="_blank" rel="noopener"><?= mp_e(ucfirst($platform)) ?></a> &nbsp;<?php endif; ?>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</section>

<h2 class="artisan-section-title reveal">Handcrafted Collection</h2>
<div class="card-grid reveal">
    <?php foreach ($products as $product): ?>
        <?php mp_render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>This artist hasn't listed any products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
