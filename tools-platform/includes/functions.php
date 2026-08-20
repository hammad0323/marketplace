<?php
/**
 * functions.php — the Tool Engine.
 *
 * Every reusable, cross-page function lives here: settings, slugs,
 * categories, tools, related/popular/trending/featured lookups, view
 * tracking, and small formatting helpers. SEO-specific generation
 * (meta tags, schema, breadcrumbs, score) lives in seo.php; auth in
 * auth.php. All DB access goes through tp_query()/tp_execute() from
 * db.php — no raw mysqli calls outside that file.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

// ------------------------------------------------------------
// Site settings (key/value store, cached per-request)
// ------------------------------------------------------------
$GLOBALS['tp_settings'] = [];

function tp_load_settings(): void
{
    $defaults = [
        'site_name' => 'ToolStack',
        'site_tagline' => 'Smart Tools for Work, Business & Everyday Life',
        'site_url' => '',
        'logo' => '',
        'favicon' => '',
        'primary_color' => '#6366F1',
        'secondary_color' => '#22D3EE',
        'accent_color' => '#8B5CF6',
        'default_theme' => 'system',
        'footer_copyright' => '© ' . date('Y') . ' ToolStack. All rights reserved.',
        'contact_email' => 'hello@example.com',
        'support_email' => 'support@example.com',
        'social_links' => '{}',
        'google_analytics_id' => '',
        'gsc_verification' => '',
        'header_scripts' => '',
        'footer_scripts' => '',
        'custom_css' => '',
        'maintenance_mode' => '0',
        'default_disclaimer' => 'Results are estimates for informational purposes only and should not be considered professional financial, engineering, tax, medical, or legal advice.',
        'currency_api_provider' => '',
        'currency_api_key' => '',
        'currency_base' => 'USD',
        'currency_update_frequency' => 'daily',
        'robots_extra_rules' => '',
    ];

    $rows = tp_query('SELECT setting_key, setting_value FROM site_settings');
    $stored = [];
    foreach ($rows as $row) {
        $stored[$row['setting_key']] = $row['setting_value'];
    }
    $GLOBALS['tp_settings'] = array_merge($defaults, $stored);
}

function tp_setting(string $key, $default = '')
{
    return $GLOBALS['tp_settings'][$key] ?? $default;
}

function tp_save_setting(string $key, string $value): void
{
    tp_execute(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        'ss',
        [$key, $value]
    );
    $GLOBALS['tp_settings'][$key] = $value;
}

// ------------------------------------------------------------
// Slugs
// ------------------------------------------------------------
function tp_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

/** Ensure a slug is unique within a table/column, appending -2, -3, ... */
function tp_unique_slug(string $table, string $base, ?int $excludeId = null): string
{
    $slug = tp_slugify($base);
    $original = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?" . ($excludeId ? ' AND id != ?' : '') . ' LIMIT 1';
        $types = $excludeId ? 'si' : 's';
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        if (!tp_query_one($sql, $types, $params)) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}

// ------------------------------------------------------------
// Categories
// ------------------------------------------------------------
function get_categories(bool $publishedOnly = true): array
{
    $sql = 'SELECT * FROM categories';
    if ($publishedOnly) {
        $sql .= " WHERE status = 'published'";
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    return tp_query($sql);
}

function get_category(int $id): ?array
{
    return tp_query_one('SELECT * FROM categories WHERE id = ?', 'i', [$id]);
}

function get_category_by_slug(string $slug): ?array
{
    return tp_query_one('SELECT * FROM categories WHERE slug = ?', 's', [$slug]);
}

function get_tool_count_for_category(int $categoryId): int
{
    $row = tp_query_one(
        "SELECT COUNT(*) AS c FROM tools WHERE category_id = ? AND status = 'published'",
        'i',
        [$categoryId]
    );
    return (int) ($row['c'] ?? 0);
}

// ------------------------------------------------------------
// Tools — the Tool Engine core
// ------------------------------------------------------------

/** Base SELECT used by every tool lookup, joined with its category. */
function tp_tool_select_sql(string $where): string
{
    return "SELECT t.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM tools t
            JOIN categories c ON c.id = t.category_id
            WHERE {$where}";
}

function get_tool(int $id): ?array
{
    return tp_query_one(tp_tool_select_sql('t.id = ?'), 'i', [$id]);
}

function get_tool_by_slug(string $slug): ?array
{
    return tp_query_one(tp_tool_select_sql('t.slug = ?'), 's', [$slug]);
}

function get_tool_category(array $tool): ?array
{
    return get_category((int) $tool['category_id']);
}

/** Full content bundle for a tool page: content block, FAQs, examples, formulas. */
function get_tool_full(int $toolId): array
{
    return [
        'content' => tp_query_one('SELECT * FROM tool_content WHERE tool_id = ?', 'i', [$toolId]),
        'faqs' => tp_query(
            "SELECT * FROM tool_faqs WHERE tool_id = ? AND status = 'published' ORDER BY sort_order ASC, id ASC",
            'i',
            [$toolId]
        ),
        'examples' => tp_query('SELECT * FROM tool_examples WHERE tool_id = ? ORDER BY sort_order ASC, id ASC', 'i', [$toolId]),
        'formulas' => tp_query('SELECT * FROM tool_formulas WHERE tool_id = ? ORDER BY sort_order ASC, id ASC', 'i', [$toolId]),
        'seo' => tp_query_one("SELECT * FROM seo_settings WHERE entity_type = 'tool' AND entity_id = ?", 'i', [$toolId]),
    ];
}

function tp_tools_where_published(): string
{
    return "t.status = 'published' AND (t.publish_at IS NULL OR t.publish_at <= NOW())";
}

function get_popular_tools(int $limit = 8, ?int $excludeId = null): array
{
    $where = tp_tools_where_published() . ' AND t.is_popular = 1' . ($excludeId ? ' AND t.id != ?' : '');
    $sql = tp_tool_select_sql($where) . ' ORDER BY t.views DESC, t.sort_order ASC LIMIT ?';
    $types = $excludeId ? 'ii' : 'i';
    $params = $excludeId ? [$excludeId, $limit] : [$limit];
    return tp_query($sql, $types, $params);
}

function get_trending_tools(int $limit = 8, ?int $excludeId = null): array
{
    // "Trending" = admin flag first, falling back to most-viewed in the
    // last 7 days so the rail is never empty on a fresh install.
    $where = tp_tools_where_published() . ' AND t.is_trending = 1' . ($excludeId ? ' AND t.id != ?' : '');
    $sql = tp_tool_select_sql($where) . ' ORDER BY t.views DESC LIMIT ?';
    $types = $excludeId ? 'ii' : 'i';
    $params = $excludeId ? [$excludeId, $limit] : [$limit];
    $rows = tp_query($sql, $types, $params);

    if (count($rows) < $limit) {
        $need = $limit - count($rows);
        $haveIds = array_column($rows, 'id') ?: [0];
        $placeholders = implode(',', array_fill(0, count($haveIds), '?'));
        $sql2 = tp_tool_select_sql(
            tp_tools_where_published() . " AND t.id NOT IN ($placeholders) AND t.views > 0"
        ) . ' ORDER BY t.views DESC LIMIT ?';
        $types2 = str_repeat('i', count($haveIds)) . 'i';
        $rows = array_merge($rows, tp_query($sql2, $types2, array_merge($haveIds, [$need])));
    }

    return $rows;
}

function get_featured_tools(int $limit = 12, ?int $excludeId = null): array
{
    $where = tp_tools_where_published() . ' AND t.is_featured = 1' . ($excludeId ? ' AND t.id != ?' : '');
    $sql = tp_tool_select_sql($where) . ' ORDER BY t.sort_order ASC LIMIT ?';
    $types = $excludeId ? 'ii' : 'i';
    $params = $excludeId ? [$excludeId, $limit] : [$limit];
    return tp_query($sql, $types, $params);
}

function get_recent_tools(int $limit = 12): array
{
    $sql = tp_tool_select_sql(tp_tools_where_published()) . ' ORDER BY t.created_at DESC LIMIT ?';
    return tp_query($sql, 'i', [$limit]);
}

function get_tools_by_category(int $categoryId, int $limit = 100, int $offset = 0): array
{
    $sql = tp_tool_select_sql(tp_tools_where_published() . ' AND t.category_id = ?')
        . ' ORDER BY t.sort_order ASC, t.name ASC LIMIT ? OFFSET ?';
    return tp_query($sql, 'iii', [$categoryId, $limit, $offset]);
}

/**
 * Related tools: admin-picked rows in tool_related take priority; the
 * rest of the slots are filled from the same category, most viewed first.
 */
function get_related_tools(int $toolId, int $categoryId, int $limit = 6): array
{
    $sql = tp_tool_select_sql(tp_tools_where_published())
        . ' AND t.id IN (SELECT related_tool_id FROM tool_related WHERE tool_id = ?)'
        . ' ORDER BY (SELECT sort_order FROM tool_related WHERE tool_id = ? AND related_tool_id = t.id) ASC'
        . ' LIMIT ?';
    $picked = tp_query($sql, 'iii', [$toolId, $toolId, $limit]);

    if (count($picked) >= $limit) {
        return $picked;
    }

    $haveIds = array_column($picked, 'id');
    $haveIds[] = $toolId;
    $placeholders = implode(',', array_fill(0, count($haveIds), '?'));
    $need = $limit - count($picked);

    $sql2 = tp_tool_select_sql(
        tp_tools_where_published() . " AND t.category_id = ? AND t.id NOT IN ($placeholders)"
    ) . ' ORDER BY t.views DESC LIMIT ?';
    $types2 = 'i' . str_repeat('i', count($haveIds)) . 'i';
    $params2 = array_merge([$categoryId], $haveIds, [$need]);

    return array_merge($picked, tp_query($sql2, $types2, $params2));
}

/**
 * Record a view at most once per tool per visitor per day (hashed IP,
 * never stored raw) so page refreshes cannot inflate popularity.
 */
function track_tool_view(int $toolId): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash = hash('sha256', $ip . date('Y-m-d') . 'tp_view_salt');
    $today = date('Y-m-d');

    $inserted = tp_execute(
        'INSERT IGNORE INTO tool_views (tool_id, view_date, ip_hash) VALUES (?, ?, ?)',
        'iss',
        [$toolId, $today, $ipHash]
    );

    if ($inserted['success'] && $inserted['affected_rows'] > 0) {
        tp_execute('UPDATE tools SET views = views + 1 WHERE id = ?', 'i', [$toolId]);
    }
}

function tp_format_number($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, $decimals);
}

function tp_format_currency($value, string $symbol = '$', int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $sign = $value < 0 ? '-' : '';
    return $sign . $symbol . number_format(abs((float) $value), $decimals);
}

/** Redirect lookup used by the front controller before a 404 is rendered. */
function tp_find_redirect(string $path): ?array
{
    return tp_query_one(
        "SELECT * FROM redirects WHERE old_url = ? AND status = 'active' LIMIT 1",
        's',
        [$path]
    );
}

/**
 * redirects.new_url is stored relative to the site root (e.g.
 * "/bmi-calculator", no base path, so it stays valid if the project
 * is ever moved between a subfolder and the domain root) — UNLESS an
 * admin typed a full external URL into the manual redirect form.
 * Always resolve through this before sending a Location header.
 */
function tp_resolve_redirect_target(string $newUrl): string
{
    if (str_starts_with($newUrl, 'http://') || str_starts_with($newUrl, 'https://')) {
        return $newUrl;
    }
    return tp_url($newUrl);
}

function tp_flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function tp_flash_get(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Pages CMS lookup. */
function get_page_by_slug(string $slug): ?array
{
    return tp_query_one("SELECT * FROM pages WHERE slug = ? AND status = 'published'", 's', [$slug]);
}

/** Blog helpers. */
function get_blog_posts(int $limit = 10, int $offset = 0, ?int $categoryId = null): array
{
    $where = "status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())";
    $types = 'ii';
    $params = [$limit, $offset];
    if ($categoryId) {
        $where .= ' AND blog_category_id = ?';
        $types = 'iii';
        $params = [$categoryId, $limit, $offset];
    }
    return tp_query("SELECT * FROM blog_posts WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?", $types, $params);
}

function get_blog_post_by_slug(string $slug): ?array
{
    return tp_query_one("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published'", 's', [$slug]);
}

/** List every calculator-logic file under /tools, relative to that folder, for the Admin Tool Builder's dropdown. */
function tp_list_tool_files(): array
{
    $root = TOOLS_PLATFORM_ROOT . '/tools';
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = ltrim(str_replace($root, '', $file->getPathname()), '/');
        }
    }
    sort($files);
    return $files;
}

function tp_asset(string $path): string
{
    return TOOLS_PLATFORM_URL . '/assets/' . ltrim($path, '/');
}

function tp_url(string $path = ''): string
{
    return TOOLS_PLATFORM_URL . '/' . ltrim($path, '/');
}

/**
 * The scheme+host part only (no path) — e.g. "https://www.beglet.com".
 * Auto-detected from the request; override with the "site_url" setting
 * (Admin → Settings) if you ever need to force a specific domain (e.g.
 * behind a proxy that mangles the Host header).
 */
function tp_site_origin(): string
{
    $override = trim((string) tp_setting('site_url'));
    if ($override !== '') {
        return rtrim($override, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** Fully-qualified URL for contexts that require one: canonical, OG/Twitter, JSON-LD, sitemap.xml. */
function tp_absolute_url(string $path = ''): string
{
    return tp_site_origin() . tp_url($path);
}

/** Given a value that may already be absolute (http...) or root-relative (/...), return it fully-qualified. */
function tp_to_absolute(string $urlOrPath): string
{
    if ($urlOrPath === '' || str_starts_with($urlOrPath, 'http://') || str_starts_with($urlOrPath, 'https://')) {
        return $urlOrPath;
    }
    return tp_site_origin() . '/' . ltrim($urlOrPath, '/');
}

/**
 * The current request's path relative to the site root — i.e. with
 * TOOLS_PLATFORM_URL's base path stripped and any query string removed.
 * Used to match the `redirects` table, whose old_url/new_url are always
 * stored relative to the site root (portable across a subfolder move).
 */
function tp_request_path_relative(): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $base = TOOLS_PLATFORM_URL;
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . ltrim($path, '/');
}

// ------------------------------------------------------------
// Route file generation — every tool/category gets a literal
// root-level "<slug>.php" file (no ?id=, no router) as required by
// the URL spec. The Admin Panel calls these on save; if a slug
// changes, the old file is removed and the admin is nudged toward
// adding a 301 redirect (see admin/tool-form.php).
// ------------------------------------------------------------
function tp_write_route_file(string $slug, string $template, string $varName, $reservedSlugs = [], string $subdir = ''): bool
{
    if (in_array($slug, (array) $reservedSlugs, true)) {
        return false; // never overwrite a hand-written root page like index.php
    }
    $dir = TOOLS_PLATFORM_ROOT . ($subdir !== '' ? '/' . trim($subdir, '/') : '');
    if ($subdir !== '' && !is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/' . $slug . '.php';
    $depth = $subdir !== '' ? '/..' : '';
    $slugEscaped = addslashes($slug);
    $content = "<?php\n\${$varName} = '{$slugEscaped}';\nrequire __DIR__ . '{$depth}/includes/{$template}';\n";
    return (bool) @file_put_contents($path, $content);
}

function tp_write_tool_route(string $slug): bool
{
    return tp_write_route_file($slug, 'tool-page.php', 'toolSlug', tp_reserved_slugs());
}

function tp_write_category_route(string $slug): bool
{
    return tp_write_route_file($slug, 'category-page.php', 'categorySlug', tp_reserved_slugs());
}

function tp_write_page_route(string $slug): bool
{
    return tp_write_route_file($slug, 'static-page.php', 'pageSlugCms', tp_reserved_slugs());
}

function tp_write_blog_route(string $slug): bool
{
    return tp_write_route_file($slug, 'blog-post.php', 'blogSlug', [], 'blog');
}

function tp_delete_route_file(string $slug, string $subdir = ''): void
{
    $dir = TOOLS_PLATFORM_ROOT . ($subdir !== '' ? '/' . trim($subdir, '/') : '');
    $path = $dir . '/' . $slug . '.php';
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Filenames that already exist as real, hand-written root pages. */
function tp_reserved_slugs(): array
{
    return [
        'index', 'search', 'about', 'contact', 'privacy-policy', 'terms',
        'disclaimer', 'cookie-policy', 'blog', 'all-tools', 'popular',
        'trending', 'new-tools', 'sitemap', 'robots', '404',
    ];
}

// ------------------------------------------------------------
// Sitemap / robots regeneration — called from the Admin Settings
// page ("Regenerate Sitemap" button) and once during install.
// ------------------------------------------------------------
function tp_regenerate_sitemap(): bool
{
    $urls = [['loc' => tp_absolute_url(), 'priority' => '1.0']];

    foreach (get_categories(true) as $cat) {
        $urls[] = ['loc' => tp_absolute_url($cat['slug']), 'priority' => '0.8'];
    }
    foreach (tp_query("SELECT slug, updated_at FROM tools WHERE status = 'published'") as $tool) {
        $urls[] = ['loc' => tp_absolute_url($tool['slug']), 'lastmod' => substr($tool['updated_at'], 0, 10), 'priority' => '0.7'];
    }
    foreach (tp_query("SELECT slug, updated_at FROM pages WHERE status = 'published'") as $page) {
        $urls[] = ['loc' => tp_absolute_url($page['slug']), 'lastmod' => substr($page['updated_at'], 0, 10), 'priority' => '0.5'];
    }
    foreach (tp_query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'") as $post) {
        $urls[] = ['loc' => tp_absolute_url('blog/' . $post['slug']), 'lastmod' => substr($post['updated_at'], 0, 10), 'priority' => '0.6'];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
        if (!empty($u['lastmod'])) {
            $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
        }
        $xml .= '    <priority>' . $u['priority'] . "</priority>\n  </url>\n";
    }
    $xml .= '</urlset>';

    return (bool) @file_put_contents(TOOLS_PLATFORM_ROOT . '/sitemap.xml', $xml);
}

/**
 * NOTE: a robots.txt written here only has effect if it's actually
 * served from the domain root (https://yourdomain.com/robots.txt) —
 * that's a hard rule search engines follow, not a suggestion. If this
 * app is deployed at www.yourdomain.com/tools/, THIS file
 * (.../tools/robots.txt) will be ignored by crawlers; merge the
 * Disallow lines below into whatever robots.txt already lives at your
 * actual domain root instead (or deploy at a subdomain, where the
 * subdomain root IS this app's root).
 */
function tp_regenerate_robots(): bool
{
    $base = TOOLS_PLATFORM_URL; // '' at domain root, e.g. '/tools' in a subfolder
    $lines = [
        'User-agent: *',
        'Allow: ' . ($base ?: '/'),
        'Disallow: ' . $base . '/admin/',
        'Disallow: ' . $base . '/includes/',
        'Disallow: ' . $base . '/database/',
        'Disallow: ' . $base . '/logs/',
        'Disallow: ' . $base . '/api/',
    ];
    $extra = trim((string) tp_setting('robots_extra_rules'));
    if ($extra !== '') {
        $lines[] = $extra;
    }
    $lines[] = '';
    $lines[] = 'Sitemap: ' . tp_absolute_url('sitemap.xml');
    return (bool) @file_put_contents(TOOLS_PLATFORM_ROOT . '/robots.txt', implode("\n", $lines) . "\n");
}
