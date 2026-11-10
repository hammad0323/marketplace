<?php
/**
 * Search engine.
 *
 * Every product keeps a normalised `search_text` (name, Urdu name, SKU,
 * barcode, ISBN, brand, category path, tags, keywords, variations).
 * A query is tokenised; each token is expanded with admin-managed synonyms
 * ("atta" -> flour) and typo corrections (Levenshtein against the catalog
 * vocabulary). Every token must match (AND); if nothing matches we fall back
 * to ANY token (OR). Results are scored so name / SKU hits rank first.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function search_normalize(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    // keep letters (any script incl. Urdu), digits; everything else -> space
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
    // split letters/digits: "class5" -> "class 5", "5kg" -> "5 kg"
    $s = preg_replace('/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function search_stopwords(): array
{
    return ['the', 'and', 'for', 'of', 'a', 'an', 'in', 'with', 'to', 'ka', 'ki', 'ke'];
}

function search_tokens(string $q): array
{
    $tokens = array_values(array_filter(explode(' ', search_normalize($q)), function ($t) {
        return $t !== '' && !in_array($t, search_stopwords(), true);
    }));
    return array_slice(array_unique($tokens), 0, 8);
}

function search_synonyms(): array
{
    static $syn = null;
    if ($syn === null) {
        $syn = [];
        foreach (db_all('SELECT term, synonyms FROM search_synonyms') as $r) {
            $syn[search_normalize($r['term'])] = array_filter(array_map('search_normalize', explode(',', $r['synonyms'])));
        }
    }
    return $syn;
}

/** Distinct words in the catalog (cached 30 minutes) for typo correction. */
function search_vocabulary(): array
{
    return cache_get('search_vocab', 1800, function () {
        $words = [];
        foreach (db_col("SELECT search_text FROM products WHERE status = 'active' AND deleted_at IS NULL") as $t) {
            foreach (explode(' ', trim((string) $t)) as $w) {
                if (mb_strlen($w) >= 3 && !ctype_digit($w)) $words[$w] = true;
            }
        }
        return array_keys($words);
    });
}

/** Closest vocabulary words for a misspelt token. */
function search_typo_candidates(string $token): array
{
    $len = mb_strlen($token);
    if ($len < 4 || ctype_digit($token) || preg_match('/[^\x00-\x7F]/', $token)) return [];
    $max = $len >= 7 ? 2 : 1;
    $best = [];
    foreach (search_vocabulary() as $w) {
        if (abs(mb_strlen($w) - $len) > $max || preg_match('/[^\x00-\x7F]/', $w)) continue;
        $d = levenshtein($token, $w);
        if ($d > 0 && $d <= $max) $best[$w] = $d;
    }
    asort($best);
    return array_slice(array_keys($best), 0, 3);
}

/** Expand each token into alternative spellings (itself + synonyms). */
function search_expand(array $tokens, bool $withTypos = false): array
{
    $syn = search_synonyms();
    $groups = [];
    foreach ($tokens as $t) {
        $alts = [$t];
        if (isset($syn[$t])) $alts = array_merge($alts, $syn[$t]);
        // simple plural / singular
        if (mb_strlen($t) > 3 && substr($t, -1) === 's') $alts[] = substr($t, 0, -1);
        if ($withTypos) $alts = array_merge($alts, search_typo_candidates($t));
        $groups[] = array_values(array_unique($alts));
    }
    return $groups;
}

/** SQL condition for one alternative (may be multi-word synonym). */
function search_alt_condition(string $alt, array &$params): string
{
    $words = explode(' ', $alt);
    $conds = [];
    foreach ($words as $w) {
        if (ctype_digit($w) || mb_strlen($w) <= 2) {
            // short tokens / numbers must match a whole word: "class 5" must not match "15"
            $conds[] = 'p.search_text LIKE ?';
            $params[] = '% ' . db_like($w) . ' %';
        } else {
            // word-prefix match gives partial-word search: "math" -> "mathematics"
            $conds[] = 'p.search_text LIKE ?';
            $params[] = '% ' . db_like($w) . '%';
        }
    }
    return '(' . implode(' AND ', $conds) . ')';
}

/**
 * Core search. Returns ordered product ids (most relevant first).
 * Options: category_id, limit (max ids), mode ('and'|'or'|'auto')
 */
function search_product_ids(string $q, array $o = []): array
{
    $q = trim($q);
    if ($q === '') return [];
    $limit = (int) ($o['limit'] ?? 500);

    // 1) Exact code match (SKU / barcode / ISBN / product code) - instant for POS & scanners
    $code = trim($q);
    if (preg_match('/^[A-Za-z0-9\-]{3,}$/', $code)) {
        $exact = db_col("SELECT DISTINCT p.id FROM products p LEFT JOIN product_variations v ON v.product_id = p.id
            WHERE p.deleted_at IS NULL AND p.status = 'active' AND (p.sku = ? OR p.barcode = ? OR p.isbn = ? OR p.product_code = ? OR v.sku = ? OR v.barcode = ?)",
            [$code, $code, str_replace('-', '', $code), $code, $code, $code]);
        if ($exact) return array_map('intval', $exact);
    }

    $tokens = search_tokens($q);
    if (!$tokens) return [];

    $run = function (array $groups, string $glue) use ($o, $limit, $tokens) {
        $params = [];
        $conds = [];
        foreach ($groups as $alts) {
            $altConds = [];
            foreach ($alts as $alt) $altConds[] = search_alt_condition($alt, $params);
            $conds[] = '(' . implode(' OR ', $altConds) . ')';
        }
        $where = "p.status = 'active' AND p.deleted_at IS NULL AND (" . implode(" $glue ", $conds) . ')';
        if (!empty($o['category_id'])) {
            $ids = category_descendant_ids((int) $o['category_id']);
            $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
            $params = array_merge($params, $ids);
        }
        // Relevance score: name hits weigh most, then brand/sku, then anything else.
        $scoreParts = [];
        $scoreParams = [];
        foreach ($groups as $alts) {
            $t = $alts[0];
            $scoreParts[] = '(CASE WHEN LOWER(p.name) LIKE ? THEN 12 WHEN LOWER(p.name) LIKE ? THEN 8 WHEN p.search_text LIKE ? THEN 3 ELSE 0 END)';
            $scoreParams[] = db_like($t) . '%';
            $scoreParams[] = '%' . db_like($t) . '%';
            $scoreParams[] = '% ' . db_like($t) . '%';
        }
        $phrase = implode(' ', $tokens);
        $scoreParts[] = '(CASE WHEN LOWER(p.name) LIKE ? THEN 15 ELSE 0 END)';
        $scoreParams[] = '%' . db_like($phrase) . '%';
        $sql = 'SELECT p.id, (' . implode(' + ', $scoreParts) . ') AS score FROM products p WHERE ' . $where
            . ' ORDER BY score DESC, p.sales_count DESC, p.id DESC LIMIT ' . $limit;
        return array_map('intval', array_column(db_all($sql, array_merge($scoreParams, $params)), 'id'));
    };

    $mode = $o['mode'] ?? 'auto';
    $ids = $run(search_expand($tokens), 'AND');
    if (!$ids) {
        $ids = $run(search_expand($tokens, true), 'AND');      // typo tolerant
    }
    if (!$ids && $mode !== 'and' && count($tokens) > 1) {
        $ids = $run(search_expand($tokens, true), 'OR');       // any word
    }
    return $ids;
}

/** Categories whose name matches the query (for suggestions). */
function search_categories(string $q, int $limit = 4): array
{
    $tokens = search_tokens($q);
    if (!$tokens) return [];
    $out = [];
    foreach (categories_all() as $c) {
        if (!$c['is_active'] || !category_is_visible((int) $c['id'])) continue;
        $hay = search_normalize($c['name'] . ' ' . $c['name_urdu']);
        $pathHay = search_normalize(implode(' ', array_column(category_path((int) $c['id']), 'name')));
        $hit = true;
        foreach ($tokens as $t) {
            if (strpos(' ' . $pathHay, ' ' . $t) === false) { $hit = false; break; }
        }
        $nameHit = false;
        foreach ($tokens as $t) {
            if (strpos(' ' . $hay, ' ' . $t) !== false) { $nameHit = true; break; }
        }
        if ($hit && $nameHit) {
            $c['path_label'] = implode(' › ', array_column(category_path((int) $c['id']), 'name'));
            $out[] = $c;
        }
        if (count($out) >= $limit) break;
    }
    return $out;
}

function search_log(string $q, int $count): void
{
    $q = mb_substr(trim(mb_strtolower($q)), 0, 160);
    if (mb_strlen($q) < 2) return;
    // de-duplicate rapid repeated logging from the same session
    $key = md5($q);
    if (($_SESSION['_last_search'] ?? '') === $key) return;
    $_SESSION['_last_search'] = $key;
    db_exec('INSERT INTO search_logs (term, results_count, customer_id, ip_address) VALUES (?, ?, ?, ?)', [$q, $count, customer_id() ?: null, client_ip()]);
}

function search_popular(int $limit = 8): array
{
    $manual = array_filter(array_map('trim', explode(',', (string) setting('popular_searches', ''))));
    if (count($manual) >= $limit) return array_slice($manual, 0, $limit);
    $auto = cache_get('popular_searches', 900, function () {
        return db_col('SELECT term FROM search_logs WHERE results_count > 0 AND created_at > ? GROUP BY term ORDER BY COUNT(*) DESC LIMIT 12',
            [date('Y-m-d H:i:s', strtotime('-30 days'))]);
    });
    return array_slice(array_values(array_unique(array_merge($manual, $auto))), 0, $limit);
}

/** Wrap query terms in <mark> (input is escaped first). */
function highlight(string $text, string $q): string
{
    $safe = e($text);
    $tokens = search_tokens($q);
    if (!$tokens) return $safe;
    $quoted = array_map(function ($t) { return preg_quote(e($t), '/'); }, $tokens);
    usort($quoted, function ($a, $b) { return strlen($b) - strlen($a); });
    return preg_replace('/(' . implode('|', $quoted) . ')/iu', '<mark>$1</mark>', $safe);
}
