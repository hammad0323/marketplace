<?php
/**
 * Every plain function used across the site, in one file: generic
 * helpers, session/auth helpers, the notification-log seam, and all
 * database query functions (grouped by table with a comment divider).
 * No classes anywhere — just functions, all prefixed mp_ to avoid
 * collisions. Required once by config.php.
 */

// =====================================================================
// Generic helpers
// =====================================================================

function mp_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function mp_slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);

    return $text !== '' ? $text : 'n-a';
}

function mp_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function mp_old(string $key, $default = '')
{
    return $_SESSION['_old_input'][$key] ?? $default;
}

function mp_flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function mp_csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function mp_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . mp_e(mp_csrf_token()) . '">';
}

function mp_verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
}

/** Badge shown next to products/stores so customers know which marketplace they're in. */
function mp_marketplace_badge(string $marketplaceType): string
{
    $badges = [
        'artisan'  => '🏺 Handmade',
        'business' => '🏪 Business Shop',
        'official' => '⭐ Official Store',
    ];
    return $badges[$marketplaceType] ?? $marketplaceType;
}

function mp_render_product_card(array $product): void
{
    require __DIR__ . '/product-card.php';
}

// =====================================================================
// Session-based auth for vendors and admins. No roles/permissions
// system — deliberately simple.
// =====================================================================

function mp_login_vendor(array $vendor): void
{
    $_SESSION['vendor_id'] = $vendor['id'];
}

function mp_current_vendor(): ?array
{
    if (empty($_SESSION['vendor_id'])) {
        return null;
    }
    return mp_find_vendor((int) $_SESSION['vendor_id']);
}

function mp_require_vendor(): array
{
    $vendor = mp_current_vendor();
    if (!$vendor) {
        mp_redirect('/vendor-login.php');
    }
    return $vendor;
}

function mp_logout_vendor(): void
{
    unset($_SESSION['vendor_id']);
}

function mp_login_admin(array $admin): void
{
    $_SESSION['admin_id'] = $admin['id'];
}

function mp_current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return mp_find_admin((int) $_SESSION['admin_id']);
}

function mp_require_admin(): array
{
    $admin = mp_current_admin();
    if (!$admin) {
        mp_redirect('/admin-login.php');
    }
    return $admin;
}

function mp_logout_admin(): void
{
    unset($_SESSION['admin_id']);
}

// =====================================================================
// Notification seam — placeholder for the future centralized email
// engine. Business logic calls mp_notify() at every point an email
// should eventually fire; swapping this for real SMTP sending later
// won't require touching any page.
// =====================================================================

function mp_notify(string $event, string $recipientEmail, array $context = []): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $line = sprintf(
        "[%s] %s -> %s %s\n",
        date('Y-m-d H:i:s'),
        $event,
        $recipientEmail,
        json_encode($context)
    );

    file_put_contents($dir . '/notifications.log', $line, FILE_APPEND);
}

// =====================================================================
// marketplace_types
// =====================================================================

function mp_find_marketplace_type(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM marketplace_types WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_marketplace_type_by_slug(string $slug): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM marketplace_types WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

// =====================================================================
// vendors
// =====================================================================

function mp_find_vendor(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_vendor_by_slug(string $slug): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function mp_find_vendor_by_email(string $email): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendors WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function mp_insert_vendor(array $data): int
{
    $stmt = mp_db()->prepare(
        'INSERT INTO vendors (marketplace_type_id, store_name, slug, email, password_hash, phone, status)
         VALUES (:marketplace_type_id, :store_name, :slug, :email, :password_hash, :phone, :status)'
    );
    $stmt->execute($data);
    return (int) mp_db()->lastInsertId();
}

/** Approved vendors of a given marketplace type, for landing/listing pages. */
function mp_approved_vendors_by_marketplace(int $marketplaceTypeId, int $limit = 12): array
{
    $stmt = mp_db()->prepare(
        "SELECT * FROM vendors
         WHERE marketplace_type_id = :type AND status = 'approved'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function mp_pending_vendors(): array
{
    $stmt = mp_db()->query(
        "SELECT vendors.*, marketplace_types.name AS marketplace_name
         FROM vendors
         JOIN marketplace_types ON marketplace_types.id = vendors.marketplace_type_id
         WHERE vendors.status = 'pending'
         ORDER BY vendors.created_at ASC"
    );

    return $stmt->fetchAll();
}

function mp_all_vendors(): array
{
    return mp_db()->query('SELECT * FROM vendors ORDER BY created_at DESC')->fetchAll();
}

function mp_approve_vendor(int $vendorId, int $adminId): void
{
    $stmt = mp_db()->prepare(
        "UPDATE vendors SET status = 'approved', approved_at = NOW(), approved_by = :admin_id WHERE id = :id"
    );
    $stmt->execute(['admin_id' => $adminId, 'id' => $vendorId]);
}

function mp_reject_vendor(int $vendorId, string $reason): void
{
    $stmt = mp_db()->prepare(
        "UPDATE vendors SET status = 'rejected', rejection_reason = :reason WHERE id = :id"
    );
    $stmt->execute(['reason' => $reason, 'id' => $vendorId]);
}

function mp_vendor_average_rating(int $vendorId): array
{
    $stmt = mp_db()->prepare(
        'SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total
         FROM vendor_ratings WHERE vendor_id = :id'
    );
    $stmt->execute(['id' => $vendorId]);
    $row = $stmt->fetch();

    return [
        'average' => $row['avg_rating'] ? (float) $row['avg_rating'] : 0.0,
        'total'   => (int) $row['total'],
    ];
}

function mp_vendor_follower_count(int $vendorId): int
{
    $stmt = mp_db()->prepare('SELECT COUNT(*) AS total FROM vendor_follows WHERE vendor_id = :id');
    $stmt->execute(['id' => $vendorId]);
    return (int) $stmt->fetch()['total'];
}

function mp_vendor_is_followed_by(int $vendorId, int $customerId): bool
{
    $stmt = mp_db()->prepare(
        'SELECT 1 FROM vendor_follows WHERE vendor_id = :vendor AND customer_id = :customer'
    );
    $stmt->execute(['vendor' => $vendorId, 'customer' => $customerId]);
    return (bool) $stmt->fetchColumn();
}

// =====================================================================
// categories
// =====================================================================

function mp_find_category(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_category_by_slug_in_marketplace(string $slug, int $marketplaceTypeId): ?array
{
    $stmt = mp_db()->prepare(
        'SELECT * FROM categories WHERE slug = :slug AND marketplace_type_id = :type LIMIT 1'
    );
    $stmt->execute(['slug' => $slug, 'type' => $marketplaceTypeId]);
    return $stmt->fetch() ?: null;
}

function mp_active_categories_by_marketplace(int $marketplaceTypeId): array
{
    $stmt = mp_db()->prepare(
        'SELECT * FROM categories
         WHERE marketplace_type_id = :type AND is_active = 1
         ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute(['type' => $marketplaceTypeId]);
    return $stmt->fetchAll();
}

function mp_all_categories_by_marketplace(int $marketplaceTypeId): array
{
    $stmt = mp_db()->prepare(
        'SELECT * FROM categories WHERE marketplace_type_id = :type ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute(['type' => $marketplaceTypeId]);
    return $stmt->fetchAll();
}

/**
 * Filters an arbitrary list of category IDs down to only those that
 * actually belong to the given marketplace type. Used to sanitize
 * user-submitted category_ids[] before creating vendor category
 * requests, since categories share one auto-increment ID space
 * across all marketplace types.
 */
function mp_filter_category_ids_by_marketplace(array $categoryIds, int $marketplaceTypeId): array
{
    $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
    if (!$categoryIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $stmt = mp_db()->prepare(
        "SELECT id FROM categories WHERE marketplace_type_id = ? AND id IN ({$placeholders})"
    );
    $stmt->execute([$marketplaceTypeId, ...$categoryIds]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// =====================================================================
// vendor_category_requests — governs which categories a Business Shop
// vendor is allowed to sell in. A category only becomes available on
// the "add product" form once a request for it has status = approved
// and is_enabled = 1.
// =====================================================================

function mp_find_category_request(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM vendor_category_requests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_request_vendor_categories(int $vendorId, array $categoryIds): void
{
    $stmt = mp_db()->prepare(
        'INSERT IGNORE INTO vendor_category_requests (vendor_id, category_id) VALUES (:vendor_id, :category_id)'
    );

    foreach ($categoryIds as $categoryId) {
        $stmt->execute(['vendor_id' => $vendorId, 'category_id' => (int) $categoryId]);
    }
}

function mp_vendor_category_requests_for_vendor(int $vendorId): array
{
    $stmt = mp_db()->prepare(
        'SELECT vendor_category_requests.*, categories.name AS category_name, categories.slug AS category_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         WHERE vendor_category_requests.vendor_id = :vendor_id
         ORDER BY vendor_category_requests.created_at DESC'
    );
    $stmt->execute(['vendor_id' => $vendorId]);
    return $stmt->fetchAll();
}

function mp_pending_category_requests(): array
{
    $stmt = mp_db()->query(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         WHERE vendor_category_requests.status = 'pending'
         ORDER BY vendor_category_requests.created_at ASC"
    );
    return $stmt->fetchAll();
}

function mp_all_category_requests(): array
{
    return mp_db()->query(
        "SELECT vendor_category_requests.*, categories.name AS category_name,
                vendors.store_name, vendors.slug AS vendor_slug
         FROM vendor_category_requests
         JOIN categories ON categories.id = vendor_category_requests.category_id
         JOIN vendors ON vendors.id = vendor_category_requests.vendor_id
         ORDER BY vendor_category_requests.created_at DESC"
    )->fetchAll();
}

/** Category IDs a vendor may currently list products in. */
function mp_approved_category_ids_for_vendor(int $vendorId): array
{
    $stmt = mp_db()->prepare(
        "SELECT category_id FROM vendor_category_requests
         WHERE vendor_id = :vendor_id AND status = 'approved' AND is_enabled = 1"
    );
    $stmt->execute(['vendor_id' => $vendorId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function mp_decide_category_request(int $requestId, string $status, int $adminId, ?string $notes, ?int $usageLimit): void
{
    $stmt = mp_db()->prepare(
        'UPDATE vendor_category_requests
         SET status = :status, admin_notes = :notes, usage_limit = :usage_limit,
             decided_at = NOW(), decided_by = :admin_id
         WHERE id = :id'
    );
    $stmt->execute([
        'status'      => $status,
        'notes'       => $notes,
        'usage_limit' => $usageLimit,
        'admin_id'    => $adminId,
        'id'          => $requestId,
    ]);
}

function mp_set_category_request_enabled(int $requestId, bool $enabled): void
{
    $stmt = mp_db()->prepare('UPDATE vendor_category_requests SET is_enabled = :enabled WHERE id = :id');
    $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $requestId]);
}

// =====================================================================
// artisan_profiles (JSON columns decoded on read)
// =====================================================================

const MP_ARTISAN_PROFILE_JSON_COLUMNS = [
    'workshop_images', 'gallery_images', 'process_media',
    'achievements', 'portfolio_items', 'social_links',
];

function mp_find_artisan_profile(int $vendorId): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM artisan_profiles WHERE vendor_id = :vendor_id LIMIT 1');
    $stmt->execute(['vendor_id' => $vendorId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        return null;
    }

    foreach (MP_ARTISAN_PROFILE_JSON_COLUMNS as $column) {
        $profile[$column] = $profile[$column] ? json_decode($profile[$column], true) : [];
    }

    return $profile;
}

function mp_save_artisan_profile(int $vendorId, array $data): void
{
    foreach (MP_ARTISAN_PROFILE_JSON_COLUMNS as $column) {
        if (isset($data[$column]) && is_array($data[$column])) {
            $data[$column] = json_encode($data[$column]);
        }
    }

    $exists = mp_db()->prepare('SELECT 1 FROM artisan_profiles WHERE vendor_id = :vendor_id');
    $exists->execute(['vendor_id' => $vendorId]);

    if ($exists->fetchColumn()) {
        $assignments = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $stmt = mp_db()->prepare("UPDATE artisan_profiles SET {$assignments} WHERE vendor_id = :vendor_id");
        $stmt->execute($data + ['vendor_id' => $vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ":{$c}", $columns);
        $stmt = mp_db()->prepare(
            'INSERT INTO artisan_profiles (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($data);
    }
}

// =====================================================================
// business_profiles
// =====================================================================

function mp_find_business_profile(int $vendorId): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM business_profiles WHERE vendor_id = :vendor_id LIMIT 1');
    $stmt->execute(['vendor_id' => $vendorId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        return null;
    }

    $profile['business_hours'] = $profile['business_hours'] ? json_decode($profile['business_hours'], true) : [];

    return $profile;
}

function mp_save_business_profile(int $vendorId, array $data): void
{
    if (isset($data['business_hours']) && is_array($data['business_hours'])) {
        $data['business_hours'] = json_encode($data['business_hours']);
    }

    $exists = mp_db()->prepare('SELECT 1 FROM business_profiles WHERE vendor_id = :vendor_id');
    $exists->execute(['vendor_id' => $vendorId]);

    if ($exists->fetchColumn()) {
        $assignments = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $stmt = mp_db()->prepare("UPDATE business_profiles SET {$assignments} WHERE vendor_id = :vendor_id");
        $stmt->execute($data + ['vendor_id' => $vendorId]);
    } else {
        $data['vendor_id'] = $vendorId;
        $columns = array_keys($data);
        $placeholders = array_map(fn ($c) => ":{$c}", $columns);
        $stmt = mp_db()->prepare(
            'INSERT INTO business_profiles (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($data);
    }
}

// =====================================================================
// products
// =====================================================================

function mp_find_product(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_product_by_slug(string $slug): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM products WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function mp_published_products(int $marketplaceTypeId, int $limit = 12): array
{
    $stmt = mp_db()->prepare(
        "SELECT * FROM products
         WHERE marketplace_type_id = :type AND status = 'published'
         ORDER BY is_featured DESC, created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mp_best_seller_products(int $marketplaceTypeId, int $limit = 8): array
{
    $stmt = mp_db()->prepare(
        "SELECT * FROM products
         WHERE marketplace_type_id = :type AND status = 'published' AND is_best_seller = 1
         ORDER BY created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':type', $marketplaceTypeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mp_products_by_vendor(int $vendorId): array
{
    $stmt = mp_db()->prepare('SELECT * FROM products WHERE vendor_id = :vendor_id ORDER BY created_at DESC');
    $stmt->execute(['vendor_id' => $vendorId]);
    return $stmt->fetchAll();
}

function mp_products_by_category(int $categoryId, int $limit = 24): array
{
    $stmt = mp_db()->prepare(
        "SELECT * FROM products WHERE category_id = :category_id AND status = 'published'
         ORDER BY created_at DESC LIMIT :limit"
    );
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mp_count_vendor_products_in_category(int $vendorId, int $categoryId): int
{
    $stmt = mp_db()->prepare(
        'SELECT COUNT(*) FROM products WHERE vendor_id = :vendor_id AND category_id = :category_id'
    );
    $stmt->execute(['vendor_id' => $vendorId, 'category_id' => $categoryId]);
    return (int) $stmt->fetchColumn();
}

function mp_insert_product(array $data): int
{
    $columns = array_keys($data);
    $placeholders = array_map(fn ($c) => ":{$c}", $columns);
    $stmt = mp_db()->prepare(
        'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($data);
    return (int) mp_db()->lastInsertId();
}

/**
 * Global search across every marketplace, tagged with marketplace
 * slug/badge so results can be labelled Handmade / Business Shop /
 * Official Store regardless of which marketplace they came from.
 */
function mp_search_products(string $term, int $limit = 30): array
{
    $stmt = mp_db()->prepare(
        "SELECT products.*, marketplace_types.slug AS marketplace_slug,
                marketplace_types.badge_label, vendors.store_name, vendors.slug AS vendor_slug
         FROM products
         JOIN marketplace_types ON marketplace_types.id = products.marketplace_type_id
         JOIN vendors ON vendors.id = products.vendor_id
         WHERE products.status = 'published'
           AND (products.title LIKE :term_title OR products.description LIKE :term_description)
         ORDER BY products.is_featured DESC, products.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':term_title', '%' . $term . '%');
    $stmt->bindValue(':term_description', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// =====================================================================
// customers, follows, ratings
// =====================================================================

function mp_find_customer_by_email(string $email): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function mp_insert_customer(array $data): int
{
    $stmt = mp_db()->prepare(
        'INSERT INTO customers (name, email, password_hash) VALUES (:name, :email, :password_hash)'
    );
    $stmt->execute($data);
    return (int) mp_db()->lastInsertId();
}

function mp_customer_follow_vendor(int $customerId, int $vendorId): void
{
    $stmt = mp_db()->prepare(
        'INSERT IGNORE INTO vendor_follows (customer_id, vendor_id) VALUES (:customer_id, :vendor_id)'
    );
    $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
}

function mp_customer_unfollow_vendor(int $customerId, int $vendorId): void
{
    $stmt = mp_db()->prepare(
        'DELETE FROM vendor_follows WHERE customer_id = :customer_id AND vendor_id = :vendor_id'
    );
    $stmt->execute(['customer_id' => $customerId, 'vendor_id' => $vendorId]);
}

function mp_customer_rate_vendor(int $customerId, int $vendorId, int $rating, ?string $review): void
{
    $stmt = mp_db()->prepare(
        'INSERT INTO vendor_ratings (customer_id, vendor_id, rating, review)
         VALUES (:customer_id, :vendor_id, :rating, :review)
         ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)'
    );
    $stmt->execute([
        'customer_id' => $customerId,
        'vendor_id'   => $vendorId,
        'rating'      => $rating,
        'review'      => $review,
    ]);
}

// =====================================================================
// admin_users
// =====================================================================

function mp_find_admin(int $id): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function mp_find_admin_by_email(string $email): ?array
{
    $stmt = mp_db()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}
