<?php
/**
 * Blog page builder (layouts in storage/blogs/*.json), static pages (storage/pages/*.json)
 * and the renderers for every content block.
 *
 * The `blogs` table is not changed. A blog that exists in the table keeps its row; its builder layout
 * and SEO settings are stored in storage/blogs/{id}.json. A new blog stays a draft in storage
 * (storage/blogs/new-xxxx.json) and gets its `blogs` row only when it is published, so drafts never
 * appear on the current site.
 */

// ---------------------------------------------------------------------------
// Blog documents
// ---------------------------------------------------------------------------
function blog_doc_key_ok($key)
{
    return (bool)preg_match('/^(\d+|new-[a-f0-9]{8,16})$/', (string)$key);
}

function blog_doc($key)
{
    if (!blog_doc_key_ok($key)) {
        return null;
    }
    $d = storage_read('blogs/' . $key . '.json', null);
    return is_array($d) ? $d : null;
}

function blog_doc_save($key, $doc)
{
    if (!blog_doc_key_ok($key)) {
        throw new RuntimeException('Bad blog key');
    }
    $doc['key'] = (string)$key;
    $doc['updated_at'] = date('Y-m-d H:i:s');
    storage_write('blogs/' . $key . '.json', $doc);
}

/** Published = status published, or scheduled with a time that has passed. Blogs without a layout are published (existing rows). */
function blog_doc_live($doc)
{
    if (!$doc) {
        return true;
    }
    $status = isset($doc['status']) ? $doc['status'] : 'published';
    if ($status === 'published') {
        return true;
    }
    if ($status === 'scheduled' && !empty($doc['publish_at'])) {
        return strtotime($doc['publish_at']) <= time();
    }
    return false;
}

/** Hidden blog ids (unpublished / future scheduled) for listings. */
function blog_hidden_ids()
{
    static $hidden = null;
    if ($hidden === null) {
        $hidden = array();
        foreach (storage_list('blogs') as $key) {
            if (ctype_digit($key)) {
                $doc = blog_doc($key);
                if ($doc && !blog_doc_live($doc)) {
                    $hidden[(int)$key] = true;
                }
            }
        }
    }
    return $hidden;
}

/**
 * Publish scheduled drafts whose time has come: creates the `blogs` row.
 * Called from the blog listing, the sitemap and the admin. Cheap: reads a small folder.
 */
function blogs_publish_due()
{
    foreach (storage_list('blogs') as $key) {
        if (strpos($key, 'new-') !== 0) {
            continue;
        }
        $doc = blog_doc($key);
        if ($doc && isset($doc['status']) && $doc['status'] === 'scheduled' && !empty($doc['publish_at']) && strtotime($doc['publish_at']) <= time()) {
            blog_insert_row($key, $doc);
        }
    }
}

/** Create the `blogs` row for a draft and move its layout to storage/blogs/{id}.json. Returns the new id. */
function blog_insert_row($key, $doc)
{
    db_exec('INSERT INTO blogs (heading, url, author, quick_answer_text, city_id, profile_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        'ssssiss', array(
            mb_substr($doc['heading'], 0, 255, 'UTF-8'),
            mb_substr($doc['slug'], 0, 500, 'UTF-8'),
            mb_substr($doc['author'] !== '' ? $doc['author'] : SITE_NAME, 0, 100, 'UTF-8'),
            $doc['excerpt'] !== '' ? '<p>' . e($doc['excerpt']) . '</p>' : null,
            !empty($doc['city_id']) ? (int)$doc['city_id'] : null,
            (string)$doc['featured_image'],
            !empty($doc['publish_at']) ? date('Y-m-d H:i:s', strtotime($doc['publish_at'])) : date('Y-m-d H:i:s'),
        ));
    $id = db_last_id();
    $doc['blog_id'] = $id;
    $doc['status'] = 'published';
    blog_doc_save((string)$id, $doc);
    storage_delete('blogs/' . $key . '.json');
    return $id;
}

function blog_slugify($text)
{
    $t = mb_strtolower(trim((string)$text), 'UTF-8');
    if (function_exists('iconv')) {
        $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if ($x !== false && $x !== '') {
            $t = $x;
        }
    }
    $t = preg_replace('/[^a-z0-9]+/', '-', $t);
    return trim($t, '-');
}

function blog_doc_defaults($b = null)
{
    return array(
        'blog_id' => $b ? (int)$b['id'] : null,
        'status' => 'published',
        'publish_at' => '',
        'heading' => $b ? $b['heading'] : '',
        'slug' => $b ? $b['url'] : '',
        'excerpt' => $b ? clean_text($b['quick_answer_text']) : '',
        'author' => $b ? $b['author'] : SITE_NAME,
        'featured_image' => $b ? (string)$b['profile_image'] : '',
        'city_id' => $b && $b['city_id'] ? (int)$b['city_id'] : 0,
        'seo' => array('title' => '', 'description' => '', 'canonical' => '', 'og_image' => '', 'noindex' => false),
        // Existing city guides start with their original content as one block
        'blocks' => $b ? array(array('id' => 'b' . substr(md5(uniqid('', true)), 0, 8), 'type' => 'legacy_guide', 'data' => array())) : array(),
    );
}

function blog_image_url($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path) || strpos($path, 'images/') === 0) {
        return media($path);
    }
    // Existing blogs.profile_image values are file names
    return media('images/blogs/' . $path);
}

// ---------------------------------------------------------------------------
// Static pages (storage/pages/{slug}.json). Legal pages fall back to content/pages/{slug}.html.
// ---------------------------------------------------------------------------
function page_slug_ok($slug)
{
    return (bool)preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string)$slug) && strlen($slug) <= 80;
}

function page_doc($slug)
{
    if (!page_slug_ok($slug)) {
        return null;
    }
    $d = storage_read('pages/' . $slug . '.json', null);
    if (is_array($d)) {
        return $d;
    }
    $file = __DIR__ . '/../content/pages/' . $slug . '.html';
    if (is_file($file)) {
        $titles = array('privacy-policy' => 'Privacy Policy', 'cookie-policy' => 'Cookie Policy', 'disclaimer' => 'Disclaimer', 'copyright-notice' => 'Copyright Notice', 'terms' => 'Terms of Use');
        return array(
            'slug' => $slug,
            'title' => isset($titles[$slug]) ? $titles[$slug] : ucwords(str_replace('-', ' ', $slug)),
            'subtitle' => '',
            'content' => file_get_contents($file),
            'blocks' => array(),
            'status' => 'published',
            'in_footer' => true,
            'seo' => array('title' => '', 'description' => '', 'noindex' => false),
            'updated_at' => date('Y-m-d H:i:s', filemtime($file)),
            'source' => 'file',
        );
    }
    return null;
}

/** Slugs handled by coded pages; a published storage page with one of these slugs replaces the coded page. */
function page_reserved_slugs()
{
    return array('mosques', 'organizations-catalog', 'program-catalog', 'services-catalog', 'events-catalog', 'prayer-times', 'blogs',
        'search', 'sitemap.xml', 'robots.txt', 'manage', 'portal', 'api', 'assets', 'images', 'mosque-map', 'details', 'blog');
}

function page_docs()
{
    $out = array();
    foreach (storage_list('pages') as $slug) {
        $d = page_doc($slug);
        if ($d) {
            $out[$slug] = $d;
        }
    }
    foreach (glob(__DIR__ . '/../content/pages/*.html') as $f) {
        $slug = basename($f, '.html');
        if (!isset($out[$slug])) {
            $d = page_doc($slug);
            if ($d) {
                $out[$slug] = $d;
            }
        }
    }
    ksort($out);
    return $out;
}

// ---------------------------------------------------------------------------
// HTML cleaning for editor content
// ---------------------------------------------------------------------------
/** Rich text from the editor: formatting, links, images; no scripts, styles or event handlers. */
function safe_rich_html($html)
{
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }
    $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><h5><a><blockquote><span><div><table><thead><tbody><tr><th><td><img><figure><figcaption><hr><code><pre><sup><sub>';
    $html = strip_tags($html, $allowed);
    if (!class_exists('DOMDocument')) {
        return preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="th-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $node) {
        if ($node->getAttribute('id') === 'th-root' || !$node->hasAttributes()) {
            continue;
        }
        $keep = array();
        $tag = $node->nodeName;
        if ($tag === 'a' && preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $node->getAttribute('href'))) {
            $keep['href'] = $node->getAttribute('href');
        }
        if ($tag === 'img' && preg_match('#^(https?:|/)#i', $node->getAttribute('src'))) {
            $keep['src'] = $node->getAttribute('src');
            $keep['alt'] = $node->getAttribute('alt');
            $keep['loading'] = 'lazy';
        }
        $cls = $node->getAttribute('class');
        if ($cls !== '' && preg_match_all('/\bql-(align-(center|right|justify)|indent-[1-8])\b/', $cls, $m)) {
            $keep['class'] = implode(' ', $m[0]);
        }
        while ($node->attributes->length) {
            $node->removeAttribute($node->attributes->item(0)->nodeName);
        }
        foreach ($keep as $k => $v) {
            $node->setAttribute($k, $v);
        }
        if ($tag === 'a' && isset($keep['href']) && preg_match('#^https?:#i', $keep['href']) && strpos($keep['href'], SITE_URL) !== 0) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener');
        }
    }
    $root = $doc->getElementById('th-root');
    $out = '';
    if ($root) {
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    }
    return preg_replace('#<p>(\s|&nbsp;|\xC2\xA0|<br>)*</p>#u', '', $out);
}

/**
 * Custom HTML block (admins only). Keeps layout markup, inline styles and iframes from trusted video/map hosts.
 * Removes <script>, <style>, <object>, <embed>, <form>, event handlers and javascript: URLs.
 */
function safe_custom_html($html)
{
    $html = (string)$html;
    $html = preg_replace('#<(script|style|object|embed|applet|form|input|button|textarea|select|meta|link|base)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = preg_replace('#<(script|style|object|embed|applet|form|input|button|textarea|select|meta|link|base)\b[^>]*/?>#is', '', $html);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src|action|formaction|xlink:href)\s*=\s*(["\']?)\s*(javascript|vbscript|data):[^"\'>\s]*\2/i', '$1="#"', $html);
    $html = preg_replace_callback('#<iframe\b[^>]*>.*?</iframe>#is', function ($m) {
        if (preg_match('/src\s*=\s*["\'](https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|google\.com\/maps|maps\.google\.com)[^"\']*)["\']/i', $m[0], $s)) {
            return '<div class="ratio ratio-16x9"><iframe src="' . e(html_entity_decode($s[1])) . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
        }
        return '';
    }, $html);
    $html = preg_replace('/expression\s*\(/i', '', $html);
    return $html;
}

function youtube_id($url)
{
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string)$url, $m)) {
        return $m[1];
    }
    return '';
}

// ---------------------------------------------------------------------------
// Block catalogue (used by the builder UI and the renderer)
// ---------------------------------------------------------------------------
function block_types()
{
    return array(
        'heading' => array('Main heading', 'bi-type-h1'),
        'subheading' => array('Subheading', 'bi-type-h2'),
        'richtext' => array('Rich text', 'bi-text-paragraph'),
        'paragraph' => array('Paragraph', 'bi-paragraph'),
        'image' => array('Image', 'bi-image'),
        'gallery' => array('Image gallery', 'bi-images'),
        'video' => array('Video embed', 'bi-film'),
        'youtube' => array('YouTube video', 'bi-youtube'),
        'quote' => array('Quote', 'bi-quote'),
        'highlight' => array('Highlighted information', 'bi-info-square'),
        'bullets' => array('Bullet list', 'bi-list-ul'),
        'numbered' => array('Numbered list', 'bi-list-ol'),
        'faq' => array('FAQ section', 'bi-patch-question'),
        'accordion' => array('Accordion', 'bi-chevron-bar-expand'),
        'cta' => array('Call to action', 'bi-megaphone'),
        'html' => array('Custom HTML', 'bi-code-slash'),
        'mosque_map' => array('Mosque map', 'bi-geo-alt'),
        'mosque_carousel' => array('Mosque cards carousel', 'bi-collection'),
        'related_blogs' => array('Related blogs', 'bi-newspaper'),
        'related_programs' => array('Related programs', 'bi-journal-bookmark'),
        'related_services' => array('Related services', 'bi-hand-thumbs-up'),
        'related_events' => array('Related events', 'bi-calendar-event'),
        'legacy_guide' => array('City guide (original content)', 'bi-journal-richtext'),
    );
}

function bd($data, $key, $default = '')
{
    return is_array($data) && isset($data[$key]) ? $data[$key] : $default;
}

/** Collect FAQ entries from blocks for FAQPage schema. */
function blocks_faq_entries($blocks)
{
    $out = array();
    foreach ((array)$blocks as $b) {
        if (isset($b['type']) && $b['type'] === 'faq') {
            foreach ((array)bd($b['data'], 'items', array()) as $it) {
                $q = trim((string)bd($it, 'q'));
                $a = trim(clean_text(bd($it, 'a')));
                if ($q !== '' && $a !== '') {
                    $out[] = array($q, $a);
                }
            }
        }
    }
    return $out;
}

/** Headings for the table of contents. */
function blocks_toc($blocks)
{
    $toc = array();
    foreach ((array)$blocks as $b) {
        if (isset($b['type']) && in_array($b['type'], array('heading', 'subheading'), true) && trim((string)bd($b['data'], 'text')) !== '') {
            $toc[] = array('id' => 'sec-' . preg_replace('/[^a-z0-9]/i', '', (string)$b['id']), 'text' => trim($b['data']['text']), 'level' => $b['type'] === 'heading' ? 2 : 3);
        }
    }
    return $toc;
}

/** Render all blocks. $ctx: blog row/doc for related blocks. */
function render_blocks($blocks, $ctx = array())
{
    foreach ((array)$blocks as $b) {
        if (!is_array($b) || empty($b['type'])) {
            continue;
        }
        $data = isset($b['data']) && is_array($b['data']) ? $b['data'] : array();
        $bid = preg_replace('/[^a-z0-9]/i', '', isset($b['id']) ? (string)$b['id'] : uniqid());
        echo '<div class="blk blk-' . e($b['type']) . '">';
        render_block($b['type'], $data, $bid, $ctx);
        echo '</div>';
    }
}

function render_block($type, $d, $bid, $ctx)
{
    switch ($type) {
        case 'heading':
            if (trim(bd($d, 'text')) !== '') {
                echo '<h2 id="sec-' . e($bid) . '">' . e(bd($d, 'text')) . '</h2>';
            }
            break;
        case 'subheading':
            if (trim(bd($d, 'text')) !== '') {
                echo '<h3 id="sec-' . e($bid) . '">' . e(bd($d, 'text')) . '</h3>';
            }
            break;
        case 'richtext':
            echo '<div class="rich">' . safe_rich_html(bd($d, 'html')) . '</div>';
            break;
        case 'paragraph':
            $t = trim((string)bd($d, 'text'));
            if ($t !== '') {
                echo '<p>' . nl2br(e($t)) . '</p>';
            }
            break;
        case 'image':
            $src = blog_image_url(bd($d, 'src'));
            if ($src !== '') {
                $img = '<img src="' . e($src) . '" alt="' . e(bd($d, 'alt')) . '" loading="lazy" decoding="async">';
                $link = safe_url(bd($d, 'link'));
                echo '<figure class="blk-figure' . (bd($d, 'width') === 'wide' ? ' is-wide' : '') . '">' . ($link ? '<a href="' . e($link) . '">' . $img . '</a>' : $img);
                if (trim(bd($d, 'caption')) !== '') {
                    echo '<figcaption>' . e(bd($d, 'caption')) . '</figcaption>';
                }
                echo '</figure>';
            }
            break;
        case 'gallery':
            $imgs = array_filter((array)bd($d, 'images', array()), function ($i) { return trim((string)bd($i, 'src')) !== ''; });
            if ($imgs) {
                $cols = max(2, min(4, (int)bd($d, 'columns', 3)));
                echo '<div class="blk-gallery" style="--cols:' . $cols . '">';
                foreach ($imgs as $i) {
                    $src = blog_image_url($i['src']);
                    echo '<a href="' . e($src) . '" target="_blank" rel="noopener" class="blk-gallery-item"><img src="' . e($src) . '" alt="' . e(bd($i, 'alt')) . '" loading="lazy"></a>';
                }
                echo '</div>';
                if (trim(bd($d, 'caption')) !== '') {
                    echo '<p class="blk-caption">' . e(bd($d, 'caption')) . '</p>';
                }
            }
            break;
        case 'video':
            $url = safe_url(bd($d, 'url'));
            $yt = youtube_id($url);
            if ($yt) {
                render_block('youtube', array('url' => $url, 'caption' => bd($d, 'caption')), $bid, $ctx);
            } elseif ($url !== '' && preg_match('#^https://player\.vimeo\.com/video/\d+#', $url)) {
                echo '<div class="ratio ratio-16x9 blk-video"><iframe src="' . e($url) . '" loading="lazy" allowfullscreen title="' . e(bd($d, 'caption', 'Video')) . '"></iframe></div>';
            } elseif ($url !== '' && preg_match('/\.(mp4|webm)(\?|$)/i', $url)) {
                echo '<video class="blk-video w-100" controls preload="metadata" src="' . e($url) . '"></video>';
            }
            if (!$yt && trim(bd($d, 'caption')) !== '') {
                echo '<p class="blk-caption">' . e(bd($d, 'caption')) . '</p>';
            }
            break;
        case 'youtube':
            $yt = youtube_id(bd($d, 'url'));
            if ($yt) {
                $title = bd($d, 'caption', 'YouTube video');
                echo '<div class="ratio ratio-16x9 blk-video yt-lite" data-yt="' . e($yt) . '">'
                    . '<button type="button" class="yt-play" aria-label="Play video: ' . e($title) . '" style="background-image:url(https://i.ytimg.com/vi/' . e($yt) . '/hqdefault.jpg)"><i class="bi bi-play-circle-fill" aria-hidden="true"></i></button></div>';
                if (trim(bd($d, 'caption')) !== '') {
                    echo '<p class="blk-caption">' . e(bd($d, 'caption')) . '</p>';
                }
            }
            break;
        case 'quote':
            if (trim(bd($d, 'text')) !== '') {
                echo '<blockquote class="blk-quote"><p>' . nl2br(e(bd($d, 'text'))) . '</p>';
                if (trim(bd($d, 'cite')) !== '') {
                    echo '<footer>— ' . e(bd($d, 'cite')) . '</footer>';
                }
                echo '</blockquote>';
            }
            break;
        case 'highlight':
            $style = in_array(bd($d, 'style'), array('info', 'success', 'warning', 'note'), true) ? bd($d, 'style') : 'info';
            $icons = array('info' => 'bi-info-circle-fill', 'success' => 'bi-check-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'note' => 'bi-lightbulb-fill');
            echo '<aside class="blk-highlight is-' . e($style) . '"><i class="bi ' . $icons[$style] . '" aria-hidden="true"></i><div>';
            if (trim(bd($d, 'title')) !== '') {
                echo '<strong class="d-block mb-1">' . e(bd($d, 'title')) . '</strong>';
            }
            echo '<div class="rich">' . safe_rich_html(bd($d, 'html')) . '</div></div></aside>';
            break;
        case 'bullets':
        case 'numbered':
            $items = array_filter(array_map('trim', (array)bd($d, 'items', array())), 'strlen');
            if ($items) {
                $tag = $type === 'numbered' ? 'ol' : 'ul';
                echo '<' . $tag . ' class="blk-list">';
                foreach ($items as $it) {
                    echo '<li>' . e($it) . '</li>';
                }
                echo '</' . $tag . '>';
            }
            break;
        case 'faq':
        case 'accordion':
            $items = array_filter((array)bd($d, 'items', array()), function ($i) { return trim((string)bd($i, 'q')) !== ''; });
            if ($items) {
                if (trim(bd($d, 'title')) !== '') {
                    echo '<h2 id="sec-' . e($bid) . '">' . e(bd($d, 'title')) . '</h2>';
                }
                echo '<div class="accordion faq" id="acc' . e($bid) . '">';
                $n = 0;
                foreach ($items as $it) {
                    $cid = 'acc' . $bid . '-' . $n;
                    $open = $n === 0 && $type === 'faq';
                    echo '<div class="accordion-item"><h3 class="accordion-header m-0"><button class="accordion-button' . ($open ? '' : ' collapsed') . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . $cid . '" aria-expanded="' . ($open ? 'true' : 'false') . '" aria-controls="' . $cid . '">' . e($it['q']) . '</button></h3>';
                    echo '<div id="' . $cid . '" class="accordion-collapse collapse' . ($open ? ' show' : '') . '" data-bs-parent="#acc' . e($bid) . '"><div class="accordion-body rich">' . safe_rich_html(bd($it, 'a')) . '</div></div></div>';
                    $n++;
                }
                echo '</div>';
            }
            break;
        case 'cta':
            $link = bd($d, 'link');
            $href = preg_match('#^/#', $link) ? url($link) : safe_url($link);
            echo '<section class="blk-cta"><div>';
            if (trim(bd($d, 'title')) !== '') {
                echo '<h3>' . e(bd($d, 'title')) . '</h3>';
            }
            if (trim(bd($d, 'text')) !== '') {
                echo '<p>' . e(bd($d, 'text')) . '</p>';
            }
            echo '</div>';
            if ($href && trim(bd($d, 'button')) !== '') {
                echo '<a class="btn btn-light btn-lg" href="' . e($href) . '">' . e(bd($d, 'button')) . '</a>';
            }
            echo '</section>';
            break;
        case 'html':
            echo '<div class="blk-html">' . safe_custom_html(bd($d, 'html')) . '</div>';
            break;
        case 'mosque_map':
            render_mosque_map_block($d, $bid);
            break;
        case 'mosque_carousel':
            render_mosque_carousel_block($d, $bid);
            break;
        case 'related_blogs':
            $limit = max(1, min(9, (int)bd($d, 'limit', 3)));
            $exclude = isset($ctx['blog_id']) ? (int)$ctx['blog_id'] : 0;
            $hidden = blog_hidden_ids();
            $list = array();
            foreach (get_blogs() as $r) {
                if ((int)$r['id'] !== $exclude && !isset($hidden[(int)$r['id']]) && count($list) < $limit) {
                    $list[] = $r;
                }
            }
            if ($list) {
                echo '<h2 id="sec-' . e($bid) . '">' . e(bd($d, 'title', 'Related articles')) . '</h2><div class="row g-4">';
                foreach ($list as $r) {
                    echo '<div class="col-sm-6 col-lg-4">';
                    blog_card($r);
                    echo '</div>';
                }
                echo '</div>';
            }
            break;
        case 'related_programs':
        case 'related_services':
        case 'related_events':
            $gcMap = array('related_programs' => 2, 'related_services' => 3, 'related_events' => 1);
            $gc = $gcMap[$type];
            $g = grand_info($gc);
            $limit = max(1, min(12, (int)bd($d, 'limit', 6)));
            $ids = array_filter(array_map('intval', (array)bd($d, 'ids', array())));
            if ($ids) {
                list($ph, $t, $vals) = db_in_ints($ids);
                $rows = db_all('SELECT ' . DETAIL_COLS . ", v.venue_title, v.url, v.role_id, v.profile_image, c.city_title FROM th_details d
                    JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id
                    WHERE d.detail_id IN ($ph) AND d.grand_category_id = ? AND v.verify_status = 1 AND " . DETAIL_ACTIVE . " LIMIT $limit", $t . 'i', array_merge($vals, array($gc)));
            } else {
                $r = list_details(array('gc' => $gc, 'category' => (int)bd($d, 'category', 0), 'city' => (int)bd($d, 'city', 0), 'page' => 1, 'per' => $limit, 'sort' => 'recent'));
                $rows = $r['rows'];
            }
            if ($rows) {
                echo '<div class="section-head"><h2 id="sec-' . e($bid) . '">' . e(bd($d, 'title', 'Related ' . strtolower($g['plural']))) . '</h2>'
                    . '<a class="btn btn-outline-primary btn-sm" href="' . e(url($g['catalog'])) . '">All ' . e(strtolower($g['plural'])) . '</a></div><div class="card-grid">';
                foreach ($rows as $row) {
                    detail_card($row);
                }
                echo '</div>';
            }
            break;
        case 'legacy_guide':
            if (!empty($ctx['blog_row'])) {
                $g = guide_data($ctx['blog_row']);
                guide_body($ctx['blog_row'], $g);
            }
            break;
    }
}

/** Venue rows (published) for a list of ids, keeping the given order. */
function venues_by_ids($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
    if (!$ids) {
        return array();
    }
    list($ph, $t, $vals) = db_in_ints($ids);
    $rows = db_all('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . " WHERE v.venue_id IN ($ph) AND v.verify_status = 1", $t, $vals);
    $by = array();
    foreach ($rows as $r) {
        $by[(int)$r['venue_id']] = $r;
    }
    $out = array();
    foreach ($ids as $id) {
        if (isset($by[$id])) {
            $out[] = $by[$id];
        }
    }
    return $out;
}

function render_mosque_map_block($d, $bid)
{
    $venues = venues_by_ids(bd($d, 'venue_ids', array()));
    if (!$venues) {
        return;
    }
    $points = array();
    $missing = array();
    foreach ($venues as $v) {
        $c = venue_coords($v);
        if ($c) {
            $points[] = array('lat' => $c['lat'], 'lng' => $c['lng'], 'title' => trim($v['venue_title']), 'href' => url(venue_url($v)),
                'address' => trim(trim((string)$v['street_address']) . ', ' . venue_place_line($v), ', '), 'logo' => venue_logo($v));
        } else {
            $missing[] = $v;
        }
    }
    $height = max(260, min(700, (int)bd($d, 'height', 420)));
    $cfg = array('points' => $points, 'zoom' => (int)bd($d, 'zoom', 0), 'center' => bd($d, 'center', null),
        'tiles' => setting('map_tiles'), 'attribution' => setting('map_attribution'));
    if (trim(bd($d, 'title')) !== '') {
        echo '<h2 id="sec-' . e($bid) . '">' . e(bd($d, 'title')) . '</h2>';
    }
    if ($points) {
        echo '<div class="blk-map" style="height:' . $height . 'px" data-blog-map="' . e(json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '" role="region" aria-label="Map of ' . count($points) . ' mosques"></div>';
    }
    echo '<ul class="blk-map-list">';
    foreach ($venues as $v) {
        echo '<li><a href="' . e(url(venue_url($v))) . '">' . e(trim($v['venue_title'])) . '</a> <span class="text-muted">· ' . e(venue_place_line($v)) . '</span></li>';
    }
    echo '</ul>';
    $GLOBALS['NEEDS_LEAFLET'] = true;
}

function render_mosque_carousel_block($d, $bid)
{
    $venues = venues_by_ids(bd($d, 'venue_ids', array()));
    if (!$venues) {
        return;
    }
    $visible = max(1, min(4, (int)bd($d, 'visible', 3)));
    $layout = bd($d, 'layout') === 'compact' ? 'compact' : 'card';
    if (trim(bd($d, 'title')) !== '') {
        echo '<h2 id="sec-' . e($bid) . '">' . e(bd($d, 'title')) . '</h2>';
    }
    echo '<div class="mc-carousel is-' . $layout . '" style="--visible:' . $visible . '" data-mc-carousel>';
    echo '<button type="button" class="mc-nav mc-prev" data-mc-prev aria-label="Previous mosques"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>';
    echo '<div class="mc-track" tabindex="0" role="list">';
    foreach ($venues as $v) {
        $href = url(venue_url($v));
        $cover = venue_cover($v);
        echo '<article class="mc-card" role="listitem">';
        if ($layout === 'card') {
            echo '<div class="mc-media">' . ($cover !== '' ? '<img src="' . e($cover) . '" alt="" loading="lazy" onerror="this.remove()">' : '') . '</div>';
        }
        echo '<div class="mc-body"><div class="mc-logo">';
        img_or_initials(venue_logo($v), trim($v['venue_title']) . ' logo', $v['venue_title']);
        echo '</div><h3><a href="' . e($href) . '">' . e(trim($v['venue_title'])) . '</a></h3>';
        echo '<p class="mc-place"><i class="bi bi-geo-alt" aria-hidden="true"></i> ' . e(venue_place_line($v)) . '</p>';
        $desc = excerpt(trim((string)$v['meta_description']) !== '' ? $v['meta_description'] : $v['about_details'], 110);
        if ($desc !== '' && $layout === 'card') {
            echo '<p class="mc-desc">' . e($desc) . '</p>';
        }
        if (trim((string)$v['street_address']) !== '' && $layout === 'card') {
            echo '<p class="mc-addr">' . e(excerpt($v['street_address'], 70)) . '</p>';
        }
        echo '<a class="btn btn-primary btn-sm mt-auto" href="' . e($href) . '">View mosque</a></div></article>';
    }
    echo '</div><button type="button" class="mc-nav mc-next" data-mc-next aria-label="Next mosques"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div>';
}

// ---------------------------------------------------------------------------
// Original city-guide content (existing `blogs` columns), used for existing blogs
// ---------------------------------------------------------------------------
function guide_venue_offers($offers, $vid, $cats)
{
    foreach ($cats as $c) {
        if (!empty($offers[$vid][$c])) {
            return true;
        }
    }
    return false;
}

function guide_names_offering($mosques, $offers, $cats, $limit = 6)
{
    $out = array();
    foreach ($mosques as $m) {
        if (guide_venue_offers($offers, (int)$m['venue_id'], $cats)) {
            $out[] = $m;
        }
    }
    return array_slice($out, 0, $limit);
}

function guide_names_sentence($list)
{
    $n = array();
    foreach ($list as $m) {
        $n[] = trim($m['venue_title']);
    }
    if (count($n) > 1) {
        $last = array_pop($n);
        return implode(', ', $n) . ' and ' . $last;
    }
    return implode('', $n);
}

function guide_data($b)
{
    $cityId = (int)$b['city_id'];
    $cityName = trim((string)$b['city_title']);
    $state = trim((string)$b['state_title']);
    $mosques = $cityId ? db_all('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . "
        WHERE v.verify_status = 1 AND v.role_id = 1 AND v.city_id = ?
        ORDER BY (v.back_image IS NOT NULL AND v.back_image <> '') DESC, v.venue_title LIMIT 200", 'i', array($cityId)) : array();
    $count = count($mosques);
    $badges = venue_badges($mosques);
    $columns = array(
        'Quran classes' => array(1, 3, 4), 'Hifz' => array(2), 'Nikah' => array(10), 'Funeral' => array(13),
        'Counselling' => array(14), 'Sisters' => array(17), 'Seniors' => array(7, 16), 'Food bank' => array(15),
    );
    $offers = array();
    if ($mosques) {
        $ids = array();
        foreach ($mosques as $m) {
            $ids[] = (int)$m['venue_id'];
        }
        list($ph, $t, $vals) = db_in_ints($ids);
        foreach (db_all("SELECT d.venue_id, d.category_id FROM th_details d WHERE d.venue_id IN ($ph) AND " . DETAIL_ACTIVE, $t, $vals) as $d) {
            foreach (explode(',', $d['category_id']) as $cid) {
                $offers[(int)$d['venue_id']][(int)trim($cid)] = true;
            }
        }
    }
    $compare = array();
    foreach ($mosques as $m) {
        $vid = (int)$m['venue_id'];
        $row = array('m' => $m, 'cols' => array(), 'score' => 0);
        foreach ($columns as $label => $cats) {
            $has = guide_venue_offers($offers, $vid, $cats);
            $row['cols'][$label] = $has;
            $row['score'] += $has ? 1 : 0;
        }
        $row['times'] = !empty($badges['prayer'][$vid]) || !empty($badges['jummah'][$vid]);
        if ($row['score'] > 0 || $row['times']) {
            $compare[] = $row;
        }
    }
    usort($compare, function ($a, $c) { return $c['score'] - $a['score']; });
    $jCount = count($badges['jummah']);
    $faq = array();
    if ($cityName !== '') {
        $faq[] = array('How many mosques are there in ' . $cityName . '?', 'The Hashir currently lists ' . plural($count, 'mosque') . ' and Islamic centre' . ($count === 1 ? '' : 's') . ' in ' . $cityName . ($state !== '' ? ', ' . $state : '') . '. You can browse them all on the ' . $cityName . ' mosque directory.');
        $nikah = guide_names_offering($mosques, $offers, array(10));
        $faq[] = array('Which mosques in ' . $cityName . ' offer Nikah services?', $nikah ? guide_names_sentence($nikah) . ' list Nikah services on The Hashir. Contact the mosque to book.' : 'No mosque in ' . $cityName . ' lists Nikah services on The Hashir yet. Contact your local mosque directly.');
        $quran = guide_names_offering($mosques, $offers, array(1, 2, 3, 4));
        $faq[] = array('Where can I find Quran or Hifz classes in ' . $cityName . '?', $quran ? 'Quran, Hifz and Tajweed programs are listed by ' . guide_names_sentence($quran) . '.' : 'No Quran classes are listed for ' . $cityName . ' yet.');
        $funeral = guide_names_offering($mosques, $offers, array(13));
        $faq[] = array('Which mosques in ' . $cityName . ' help with funerals (Janazah)?', $funeral ? guide_names_sentence($funeral) . ' list funeral services on The Hashir.' : 'No funeral services are listed for ' . $cityName . ' yet. Your local mosque can usually advise.');
        $faq[] = array('Where can I find Jumu\'ah times in ' . $cityName . '?', $jCount ? plural($jCount, 'mosque') . ' in ' . $cityName . ' have published current Jumu\'ah times. See the ' . $cityName . ' prayer times page for the full list.' : 'Jumu\'ah times for ' . $cityName . ' have not been published on The Hashir yet. Please check with each mosque.');
    }
    return array(
        'cityId' => $cityId, 'cityName' => $cityName, 'state' => $state, 'mosques' => $mosques, 'count' => $count,
        'featured' => array_slice($mosques, 0, 9), 'badges' => $badges, 'columns' => $columns, 'compare' => array_slice($compare, 0, 40),
        'faq' => $faq, 'mapSrc' => map_embed_src($b['map_url']), 'hasSummary' => trim(clean_text($b['summary_text'])) !== '',
    );
}

function guide_body($b, $g)
{
    extract($g);
    ?>
      <?php if (trim(clean_text($b['quick_answer_text'])) !== '' || $cityName !== ''): ?>
      <section class="quick-answer mt-4" id="quick-answer" aria-labelledby="h-qa">
        <h2 class="h5 mt-0 mb-2" id="h-qa">Quick answer</h2>
        <div class="rich"><?= safe_html($b['quick_answer_text']) ?></div>
        <?php if ($cityName !== ''): ?>
        <div class="stat-grid">
          <div class="stat"><span class="label">City</span><span class="value"><?= e($cityName) ?></span></div>
          <div class="stat"><span class="label">Province</span><span class="value"><?= e($state) ?></span></div>
          <?php if (trim((string)$b['population']) !== '' && mb_strlen($b['population'], 'UTF-8') < 120): ?><div class="stat"><span class="label">Muslim population</span><span class="value"><?= e(is_numeric($b['population']) ? number_format((int)$b['population']) : $b['population']) ?></span></div><?php endif; ?>
          <div class="stat"><span class="label">Mosques on The Hashir</span><span class="value"><?= number_format($count) ?></span></div>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($mapSrc): ?>
      <h2 id="map">Map of mosques in <?= e($cityName) ?></h2>
      <div class="map-frame"><iframe src="<?= e($mapSrc) ?>" title="Map of mosques in <?= e($cityName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
      <?php endif; ?>

      <?php if ($featured): ?>
      <h2 id="mosques">Featured mosques in <?= e($cityName) ?></h2>
      <div class="card-grid" style="grid-template-columns:repeat(auto-fill,minmax(230px,1fr))">
        <?php foreach ($featured as $m) { venue_card($m, $badges); } ?>
      </div>
      <?php if ($count > count($featured)): ?><p class="mt-3"><a class="btn btn-outline-primary" href="<?= e(url('/mosques?city=' . $cityId)) ?>">See all <?= number_format($count) ?> mosques in <?= e($cityName) ?></a></p><?php endif; ?>
      <?php endif; ?>

      <?php if ($compare): ?>
      <h2 id="compare">What each mosque offers</h2>
      <p class="text-muted">Based on the programs and services each mosque lists on The Hashir. A dash means nothing is listed yet, not that the service isn't offered.</p>
      <div class="table-responsive table-wrap">
        <table class="table compare-table mb-0 align-middle">
          <thead><tr><th scope="col">Mosque</th><th scope="col">Prayer times</th><?php foreach (array_keys($columns) as $c): ?><th scope="col"><?= e($c) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($compare as $row): ?>
            <tr>
              <th scope="row"><a href="<?= e(url(venue_url($row['m']))) ?>"><?= e(trim($row['m']['venue_title'])) ?></a></th>
              <td class="<?= $row['times'] ? 'yes' : 'no' ?>"><?= $row['times'] ? '<i class="bi bi-check-lg" aria-label="Yes"></i>' : '<span aria-label="Not listed">–</span>' ?></td>
              <?php foreach ($row['cols'] as $has): ?><td class="<?= $has ? 'yes' : 'no' ?>"><?= $has ? '<i class="bi bi-check-lg" aria-label="Yes"></i>' : '<span aria-label="Not listed">–</span>' ?></td><?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($hasSummary): ?>
      <h2 id="summary">Summary</h2>
      <div class="rich"><?= safe_html($b['summary_text']) ?></div>
      <?php endif; ?>

      <?php if ($faq): ?>
      <h2 id="faq">Frequently asked questions</h2>
      <div class="accordion faq" id="faqAcc">
        <?php foreach ($faq as $i => $q): ?>
        <div class="accordion-item">
          <h3 class="accordion-header m-0"><button class="accordion-button<?= $i ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($q[0]) ?></button></h3>
          <div id="faq<?= $i ?>" class="accordion-collapse collapse<?= $i ? '' : ' show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= e($q[1]) ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
<?php
}
