<?php
/** /sitemap.xml — generated from published records */
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function sm_url($path, $lastmod = '', $freq = 'weekly', $prio = '0.6')
{
    echo '<url><loc>' . htmlspecialchars(abs_url($path), ENT_XML1) . '</loc>';
    if ($lastmod !== '' && valid_date($lastmod)) {
        echo '<lastmod>' . substr($lastmod, 0, 10) . '</lastmod>';
    }
    echo '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>' . "\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
sm_url('/', '', 'daily', '1.0');
foreach (array('/mosques', '/prayer-times', '/program-catalog', '/services-catalog', '/events-catalog', '/organizations-catalog', '/blogs', '/about', '/support', '/solution-mosque') as $p) {
    sm_url($p, '', 'weekly', '0.8');
}
foreach (array(1, 2, 3) as $gc) {
    foreach (get_categories($gc) as $c) {
        if ($c['count'] > 0) {
            sm_url(category_url($c), '', 'weekly', '0.7');
        }
    }
}
foreach (get_org_roles() as $r) {
    if ($r['count'] > 0) {
        sm_url(org_role_url($r), '', 'weekly', '0.7');
    }
}
foreach (prayer_cities() as $c) {
    sm_url(city_prayer_url($c), '', 'daily', '0.8');
}
foreach (db_all('SELECT DISTINCT v.city_id FROM th_venue v WHERE v.verify_status = 1 AND v.role_id = 1 AND v.city_id IS NOT NULL') as $c) {
    sm_url('/mosques?city=' . (int)$c['city_id'], '', 'weekly', '0.6');
}
foreach (db_all('SELECT venue_id, role_id, url, venue_title, update_date FROM th_venue WHERE verify_status = 1') as $v) {
    sm_url(venue_url($v), $v['update_date'], 'weekly', (int)$v['role_id'] === 1 ? '0.8' : '0.6');
}
foreach (db_all('SELECT d.detail_id, d.details_url, d.details_title, d.update_date FROM th_details d JOIN th_venue v ON v.venue_id = d.venue_id WHERE v.verify_status = 1 AND ' . DETAIL_ACTIVE) as $d) {
    sm_url(detail_url($d), $d['update_date'], 'monthly', '0.5');
}
foreach (get_blogs() as $b) {
    $doc = blog_doc((string)(int)$b['id']);
    if ($doc && (!empty($doc['seo']['noindex']) || !empty($doc['seo']['canonical']))) {
        continue;
    }
    sm_url(blog_url($b), $doc && !empty($doc['updated_at']) ? $doc['updated_at'] : $b['updated_date'], 'monthly', '0.7');
}
foreach (page_docs() as $slug => $pg) {
    if ($pg['status'] === 'published' && empty($pg['seo']['noindex']) && !in_array($slug, array('about', 'support', 'solution-mosque'), true)) {
        sm_url('/' . $slug, $pg['updated_at'], 'yearly', '0.3');
    }
}
sm_url('/mosque-map', '', 'weekly', '0.6');
echo '</urlset>';
