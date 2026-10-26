<?php
/**
 * Minimal PHP client for the Quran Foundation Content & Search APIs.
 *
 * Handles the OAuth2 Client Credentials token (requested, cached on disk
 * and renewed before it expires), the required x-auth-token / x-client-id
 * headers, one retry on 401 / 429 / 5xx, and file caching of responses.
 * Needs only PHP 7.4+ with the cURL extension.
 *
 *   $qf = new QuranClient(QF_CLIENT_ID, QF_CLIENT_SECRET, QF_ENV);
 *   $chapters = $qf->chapters();
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

class QuranApiException extends RuntimeException
{
    public int $status;
    public string $body;

    public function __construct(string $message, int $status = 0, string $body = '')
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->body = $body;
    }
}

class QuranClient
{
    private const HOSTS = [
        'prelive'    => ['auth' => 'https://prelive-oauth2.quran.foundation', 'api' => 'https://apis-prelive.quran.foundation'],
        'production' => ['auth' => 'https://oauth2.quran.foundation',         'api' => 'https://apis.quran.foundation'],
    ];

    /** Base URL for relative audio paths such as "Alafasy/mp3/001001.mp3". */
    public const AUDIO_BASE = 'https://verses.quran.foundation/';

    private const MAX_CACHE_TTL = 604800; // QF Terms: never cache content longer than 7 days

    private string $clientId;
    private string $clientSecret;
    private string $env;
    private string $authUrl;
    private string $apiUrl;
    private string $authMethod;
    private string $cacheDir;
    private int $cacheTtl;
    private string $userAgent;
    private int $lastStatus = 0;

    /** Written before cached JSON so the cache files print nothing if opened in a browser. */
    private const CACHE_GUARD = "<?php exit; ?>\n";

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $env = 'prelive',
        array $options = []
    ) {
        if (!isset(self::HOSTS[$env])) {
            throw new InvalidArgumentException("QF env must be 'prelive' or 'production', got '$env'.");
        }
        $this->clientId     = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->env          = $env;
        $this->authUrl      = rtrim($options['auth_url'] ?? self::HOSTS[$env]['auth'], '/');
        $this->apiUrl       = rtrim($options['api_url'] ?? self::HOSTS[$env]['api'], '/');
        $this->authMethod   = ($options['auth_method'] ?? 'basic') === 'post' ? 'post' : 'basic';
        $this->cacheDir     = rtrim($options['cache_dir'] ?? __DIR__ . '/cache', '/');
        $this->cacheTtl     = min((int)($options['cache_ttl'] ?? 86400), self::MAX_CACHE_TTL);
        $this->userAgent    = $options['user_agent'] ?? 'QuranClient-PHP/1.0';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
    }

    public function env(): string
    {
        return $this->env;
    }

    /* ------------------------------------------------------------------
     *  Authentication
     * ------------------------------------------------------------------ */

    /**
     * Returns a valid access token, requesting a new one when the cached
     * token is missing or within 60 seconds of expiry.
     */
    public function token(bool $forceNew = false): string
    {
        $file = $this->cacheDir . '/token-' . $this->env . '-' . md5($this->clientId) . '.php';

        if (!$forceNew && is_file($file)) {
            $cached = json_decode(substr((string)file_get_contents($file), strlen(self::CACHE_GUARD)), true);
            if (!empty($cached['token']) && $cached['expires_at'] > time() + 60) {
                return $cached['token'];
            }
        }

        try {
            $res = $this->requestToken($this->authMethod);
        } catch (QuranApiException $e) {
            // Some clients are registered for the other auth method; try it once.
            if ($e->status !== 401 || strpos($e->body, 'invalid_client') === false) {
                throw $e;
            }
            $res = $this->requestToken($this->authMethod === 'basic' ? 'post' : 'basic');
        }

        @file_put_contents($file, self::CACHE_GUARD . json_encode([
            'token'      => $res['access_token'],
            'expires_at' => time() + (int)($res['expires_in'] ?? 3600),
        ]), LOCK_EX);

        return $res['access_token'];
    }

    private function requestToken(string $method): array
    {
        $fields = ['grant_type' => 'client_credentials', 'scope' => 'content'];
        $opts = [CURLOPT_POST => true];

        if ($method === 'basic') {
            $opts[CURLOPT_USERPWD] = $this->clientId . ':' . $this->clientSecret;
        } else {
            $fields['client_id'] = $this->clientId;
            $fields['client_secret'] = $this->clientSecret;
        }
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);

        [$status, $body] = $this->http(
            $this->authUrl . '/oauth2/token',
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            $opts
        );

        $json = json_decode($body, true);
        if ($status !== 200 || empty($json['access_token'])) {
            $reason = $json['error_description'] ?? $json['error'] ?? substr($body, 0, 200);
            throw new QuranApiException("Token request failed (HTTP $status): $reason", $status, $body);
        }
        return $json;
    }

    /* ------------------------------------------------------------------
     *  Generic requests
     * ------------------------------------------------------------------ */

    /** GET a Content API v4 path, e.g. content('/chapters', ['language' => 'en']). */
    public function content(string $path, array $query = [], bool $useCache = true): array
    {
        return $this->get('/content/api/v4' . $path, $query, $useCache);
    }

    /** GET any gateway path (Content: /content/api/v4/..., Search: /search/api/v1/...). */
    public function get(string $path, array $query = [], bool $useCache = true): array
    {
        $query = array_filter($query, static function ($v) {
            return $v !== null && $v !== '';
        });
        $url = $this->apiUrl . $path . ($query ? '?' . http_build_query($query) : '');

        $cacheFile = $this->cacheDir . '/resp-' . md5($url) . '.php';
        if ($useCache && $this->cacheTtl > 0 && is_file($cacheFile)
            && filemtime($cacheFile) > time() - $this->cacheTtl) {
            $data = json_decode(substr((string)file_get_contents($cacheFile), strlen(self::CACHE_GUARD)), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $data = $this->send($url);

        if ($useCache && $this->cacheTtl > 0) {
            @file_put_contents($cacheFile, self::CACHE_GUARD . json_encode($data), LOCK_EX);
        }
        return $data;
    }

    private function send(string $url, int $attempt = 0): array
    {
        [$status, $body, $headers] = $this->http($url, [
            'x-auth-token: ' . $this->token($attempt > 0 && $this->lastStatus === 401),
            'x-client-id: ' . $this->clientId,
            'Accept: application/json',
        ]);
        $this->lastStatus = $status;

        if ($status === 200) {
            $data = json_decode($body, true);
            if (!is_array($data)) {
                throw new QuranApiException('Invalid JSON from Quran API', $status, $body);
            }
            return $data;
        }

        // Retry once: 401 with a fresh token, 429/5xx after a short wait.
        if ($attempt === 0 && ($status === 401 || $status === 429 || $status >= 500)) {
            if ($status !== 401) {
                $wait = (int)($headers['retry-after'] ?? 2);
                sleep(max(1, min($wait, 5)));
            }
            return $this->send($url, 1);
        }

        $json = json_decode($body, true);
        $reason = $json['message'] ?? $json['error'] ?? substr($body, 0, 200);
        throw new QuranApiException("Quran API error (HTTP $status): $reason", $status, $body);
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    private function http(string $url, array $headers, array $extra = []): array
    {
        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, $extra + [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$respHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new QuranApiException("Could not connect to Quran Foundation: $error", 0);
        }
        return [$status, (string)$body, $respHeaders];
    }

    /** Deletes all cached responses (the token is kept). */
    public function clearCache(): int
    {
        $n = 0;
        foreach (glob($this->cacheDir . '/resp-*.php') ?: [] as $f) {
            $n += (int)@unlink($f);
        }
        return $n;
    }

    /* ------------------------------------------------------------------
     *  Chapters (surahs) & structure
     * ------------------------------------------------------------------ */

    public function chapters(string $language = 'en'): array
    {
        return $this->content('/chapters', ['language' => $language])['chapters'] ?? [];
    }

    public function chapter(int $id, string $language = 'en'): array
    {
        return $this->content("/chapters/$id", ['language' => $language])['chapter'] ?? [];
    }

    public function chapterInfo(int $id, string $language = 'en'): array
    {
        return $this->content("/chapters/$id/info", ['language' => $language])['chapter_info'] ?? [];
    }

    public function juzs(): array
    {
        return $this->content('/juzs')['juzs'] ?? [];
    }

    /* ------------------------------------------------------------------
     *  Verses
     *
     *  $opts for the verse methods (all optional):
     *    translations => '131,97'   translation IDs (see translations())
     *    tafsirs      => '169'      tafsir IDs (see tafsirs())
     *    audio        => 7          recitation ID (see recitations())
     *    words        => true       word-by-word data
     *    fields       => 'text_uthmani,text_indopak_nastaleeq'
     *    language     => 'en'       word-by-word language
     *    page, per_page             pagination (per_page max 50)
     * ------------------------------------------------------------------ */

    /** @return array{verses: array, pagination: array} */
    public function versesByChapter(int $chapter, array $opts = []): array
    {
        return $this->content("/verses/by_chapter/$chapter", $this->verseQuery($opts));
    }

    public function versesByPage(int $page, array $opts = []): array
    {
        return $this->content("/verses/by_page/$page", $this->verseQuery($opts));
    }

    public function versesByJuz(int $juz, array $opts = []): array
    {
        return $this->content("/verses/by_juz/$juz", $this->verseQuery($opts));
    }

    public function verseByKey(string $verseKey, array $opts = []): array
    {
        return $this->content('/verses/by_key/' . rawurlencode($verseKey), $this->verseQuery($opts))['verse'] ?? [];
    }

    /** A random verse, never cached. */
    public function randomVerse(array $opts = []): array
    {
        return $this->content('/verses/random', $this->verseQuery($opts), false)['verse'] ?? [];
    }

    private function verseQuery(array $opts): array
    {
        $q = $opts + ['language' => 'en', 'fields' => 'text_uthmani'];
        if (isset($q['words'])) {
            $q['words'] = $q['words'] ? 'true' : 'false';
        }
        return $q;
    }

    /**
     * Arabic text only, in one script, for a filter such as
     * ['chapter_number' => 1] or ['verse_key' => '2:255'] or ['page_number' => 3].
     * Scripts: uthmani, uthmani_simple, uthmani_tajweed, indopak_nastaleeq,
     * imlaei, qpc_hafs, qpc_nastaleeq, code_v1, code_v2.
     */
    public function arabicText(string $script, array $filter): array
    {
        return $this->content('/quran/verses/' . rawurlencode($script), $filter)['verses'] ?? [];
    }

    /* ------------------------------------------------------------------
     *  Translations & tafsir
     * ------------------------------------------------------------------ */

    public function translations(string $language = 'en'): array
    {
        return $this->content('/resources/translations', ['language' => $language])['translations'] ?? [];
    }

    public function tafsirs(string $language = 'en'): array
    {
        return $this->content('/resources/tafsirs', ['language' => $language])['tafsirs'] ?? [];
    }

    /** Tafsir for one ayah: ['text' => html, 'resource_name' => ..., 'verses' => covered keys]. */
    public function tafsirByAyah(int $tafsirId, string $verseKey): array
    {
        return $this->content("/tafsirs/$tafsirId/by_ayah/" . rawurlencode($verseKey))['tafsir'] ?? [];
    }

    public function languages(): array
    {
        return $this->content('/resources/languages')['languages'] ?? [];
    }

    /* ------------------------------------------------------------------
     *  Audio
     * ------------------------------------------------------------------ */

    /** Reciters with per-ayah audio (use the id with versesBy*(['audio' => id])). */
    public function recitations(string $language = 'en'): array
    {
        return $this->content('/resources/recitations', ['language' => $language])['recitations'] ?? [];
    }

    /** Reciters with full-surah audio files: the larger list Quran.com's player uses. */
    public function chapterReciters(string $language = 'en'): array
    {
        return $this->content('/resources/chapter_reciters', ['language' => $language])['reciters'] ?? [];
    }

    /**
     * Full-surah MP3 of a chapter reciter: ['audio_url' => ..., 'file_size' => ..., ...].
     * With $timestamps = true it also returns 'timestamps': per-ayah
     * [verse_key, timestamp_from, timestamp_to (ms), segments] for highlighting.
     */
    public function chapterAudio(int $reciterId, int $chapter, bool $timestamps = false): array
    {
        $query = $timestamps ? ['segments' => 'true'] : [];
        return $this->content("/chapter_recitations/$reciterId/$chapter", $query)['audio_file'] ?? [];
    }

    /** Per-ayah audio files of a surah: [['verse_key' => '1:1', 'url' => absolute URL], ...]. */
    public function ayahAudioByChapter(int $recitationId, int $chapter): array
    {
        $files = [];
        $page = 1;
        do {
            $res = $this->content("/recitations/$recitationId/by_chapter/$chapter", ['page' => $page, 'per_page' => 50]);
            foreach ($res['audio_files'] ?? [] as $f) {
                $f['url'] = self::audioUrl($f['url'] ?? '');
                $files[] = $f;
            }
            $page = $res['pagination']['next_page'] ?? null;
        } while ($page);
        return $files;
    }

    /** Turns a relative audio path from the API into a full URL. */
    public static function audioUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        return preg_match('#^https?://#', $url) ? $url : self::AUDIO_BASE . ltrim($url, '/');
    }

    /* ------------------------------------------------------------------
     *  Search  (Search API v1)
     * ------------------------------------------------------------------ */

    /**
     * mode 'quick' returns navigation (surahs, juz, …) + matching verses;
     * 'advanced' returns detailed, paginated verse results.
     * @return array{result: array, pagination: ?array}
     */
    public function search(string $query, string $mode = 'quick', array $opts = []): array
    {
        return $this->get('/search/api/v1/search', $opts + [
            'query'     => $query,
            'mode'      => $mode,
            'get_text'  => '1',
            'highlight' => '1',
        ]);
    }

    /* ------------------------------------------------------------------
     *  HTML helpers
     * ------------------------------------------------------------------ */

    /** Translation text without footnote markers or HTML, safe to echo after htmlspecialchars(). */
    public static function plainTranslation(string $html): string
    {
        $html = preg_replace('#<sup[^>]*>.*?</sup>#si', '', $html);
        return trim(html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8'));
    }

    /** Keeps basic formatting tags of API HTML (tafsir, chapter info) and drops all attributes. */
    public static function safeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#si', '', $html);
        $html = strip_tags((string)$html, '<p><br><h1><h2><h3><h4><h5><b><strong><i><em><ul><ol><li><blockquote><span><div>');
        return (string)preg_replace('#<(\w+)\s[^>]*>#', '<$1>', $html);
    }
}
