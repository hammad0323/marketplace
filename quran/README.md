# Quran Foundation API — PHP kit

Ready-made pages and a small PHP client for the
[Quran Foundation Content & Search APIs](https://api-docs.quran.foundation/).
It needs only PHP 7.4+ with cURL: no Composer and no database.

## 1. Get credentials

1. Sign in at <https://dev-console.quran.foundation/projects> and create an app.
2. For the app type, choose **Backend/server app**. Only this type gets a Client Secret.
3. Copy the **Client ID** and **Client Secret**. The secret is shown only once.
4. New apps run in **pre-live**, which only has Surah 1 and 2. Request production
   access in the Console when your site is ready (QF also asks for a public
   Privacy Policy and Terms page; see <https://api-docs.quran.foundation/legal/developer-privacy/>).

## 2. Install

1. Upload the whole `quran/` folder to `public_html/`.
2. Edit **`qf-config.php`**: paste `QF_CLIENT_ID` and `QF_CLIENT_SECRET`, and keep
   `QF_ENV = 'prelive'` until production is approved.
3. Make sure `quran/cache/` is writable (permission 755 or 775).
4. Set `QF_DEBUG` to `true`, open `https://your-site.com/quran/test.php`, and
   check that every row is ✅. Then set `QF_DEBUG` back to `false`.

## 3. Pages

| URL | Shows | API endpoints used |
|---|---|---|
| `index.php` | All 114 surahs | `GET /chapters` |
| `surah.php?id=1` | Arabic (Uthmani/IndoPak), translation, ayah-by-ayah audio with auto-play | `/chapters/{id}`, `/verses/by_chapter/{id}`, `/resources/translations`, `/resources/recitations` |
| `ayah.php?key=2:255` | One ayah with tafsir | `/verses/by_key/{key}`, `/resources/tafsirs`, `/tafsirs/{id}/by_ayah/{key}` |
| `info.php?id=1` | Background of a surah | `/chapters/{id}/info` |
| `search.php?q=mercy` | Search results | Search API `GET /search/api/v1/search` |
| `api.php` | JSON API for mobile apps (see section 5) | all of the above |
| `test.php` | Connection diagnostics (only when `QF_DEBUG` is true) | token + `/quran/verses/uthmani` |

## 4. Using the client in your own pages

```php
<?php
require __DIR__ . '/quran/bootstrap.php';   // gives you qf()

$chapters = qf()->chapters('en');                       // all surahs
$fatiha   = qf()->versesByChapter(1, [                  // verses + translation + audio
    'translations' => '131',                            // IDs from qf()->translations()
    'audio'        => 7,                                // ID from qf()->recitations()
    'fields'       => 'text_uthmani,text_indopak_nastaleeq',
    'per_page'     => 50,
]);
$kursi    = qf()->verseByKey('2:255', ['translations' => '131']);
$arabic   = qf()->arabicText('indopak_nastaleeq', ['chapter_number' => 1]);
$tafsir   = qf()->tafsirByAyah(169, '1:1');             // IDs from qf()->tafsirs()
$mp3      = qf()->chapterAudio(3, 1)['audio_url'];      // full surah audio
$random   = qf()->randomVerse(['translations' => '131']);
$results  = qf()->search('mercy');

// Any other endpoint from the docs:
$juz = qf()->content('/juzs/1');
```

Every method throws `QuranApiException` (with `->status` and `->body`) on failure,
so wrap calls in `try { … } catch (Throwable $e) { … }`.

### Client methods

| Method | Endpoint |
|---|---|
| `chapters($lang)`, `chapter($id)`, `chapterInfo($id)` | `/chapters`, `/chapters/{id}`, `/chapters/{id}/info` |
| `versesByChapter($n, $opts)`, `versesByPage($n, $opts)`, `versesByJuz($n, $opts)` | `/verses/by_chapter|by_page|by_juz/{n}` |
| `verseByKey('2:255', $opts)`, `randomVerse($opts)` | `/verses/by_key/{key}`, `/verses/random` |
| `arabicText($script, $filter)` | `/quran/verses/{script}` |
| `translations($lang)`, `tafsirs($lang)`, `languages()` | `/resources/translations`, `/resources/tafsirs`, `/resources/languages` |
| `tafsirByAyah($id, $key)` | `/tafsirs/{id}/by_ayah/{key}` |
| `recitations()`, `chapterReciters()` | `/resources/recitations`, `/resources/chapter_reciters` |
| `chapterAudio($reciter, $n)`, `ayahAudioByChapter($recitation, $n)` | `/chapter_recitations/{r}/{n}`, `/recitations/{r}/by_chapter/{n}` |
| `search($q, 'quick'|'advanced', $opts)` | `/search/api/v1/search` |
| `content($path, $query)` / `get($path, $query)` | any Content / gateway path |
| `token()` | `POST /oauth2/token` (normally automatic) |
| `clearCache()` | deletes cached responses |

`$opts` for verse methods: `translations`, `tafsirs`, `audio`, `words` (bool),
`fields`, `translation_fields`, `word_fields`, `language`, `page`, `per_page` (max 50).

## 5. JSON API for a mobile app (`api.php`)

A mobile app must **never** contain the QF `client_secret` (QF rule, and anyone can
extract it from an APK/IPA). The app calls `api.php` on your server instead, and
`api.php` calls Quran Foundation with the secret kept server-side.

```
Mobile app ──HTTPS──▶ https://your-site.com/quran/api.php ──▶ apis.quran.foundation
             (X-App-Key)                                     (token + secret)
```

Set `QF_APP_API_KEY` in `qf-config.php` to a long random string, and send it from
the app as the `X-App-Key` header. Every response looks like this:

```json
{ "ok": true,  "data": { ... } }
{ "ok": false, "error": { "code": "invalid_parameter", "message": "..." } }
```

`GET api.php` with no parameters lists all actions.

| Action | Required | Optional |
|---|---|---|
| `chapters` | — | `language` |
| `chapter`, `chapter_info` | `id` (1–114) | `language` |
| `verses` | `chapter` (1–114) | `translations`, `tafsirs`, `audio`, `words`, `fields`, `word_fields`, `translation_fields`, `language`, `page`, `per_page` (≤50) |
| `verses_by_page` | `page_number` (1–604) | same as `verses` |
| `verses_by_juz` | `juz` (1–30) | same as `verses` |
| `verse` | `key` (`2:255`) | same as `verses` except paging |
| `random` | — | `translations`, `fields`, `language` |
| `arabic` | `script` + one of `chapter`, `key`, `page_number`, `juz` | — |
| `juzs`, `languages` | — | — |
| `translations`, `tafsirs`, `recitations`, `chapter_reciters` | — | `language` |
| `tafsir` | `id`, `key` | — |
| `chapter_audio` | `reciter`, `chapter` | — |
| `ayah_audio` | `recitation`, `chapter` | — |
| `search` | `q` | `mode` (`quick`/`advanced`), `translations`, `size` |

Examples:

```
GET /quran/api.php?action=chapters
GET /quran/api.php?action=verses&chapter=1&translations=131&audio=7&fields=text_uthmani,text_indopak_nastaleeq&per_page=50
GET /quran/api.php?action=verse&key=2:255&translations=131
GET /quran/api.php?action=arabic&script=indopak_nastaleeq&chapter=1
GET /quran/api.php?action=tafsir&id=169&key=1:1
GET /quran/api.php?action=chapter_audio&reciter=3&chapter=1
GET /quran/api.php?action=search&q=mercy
```

Audio URLs in responses are already full `https://` links, ready for an audio player.
Error codes: `400` missing parameter, `401` wrong app key, `404` not found,
`422` invalid parameter, `429` too many requests (limit: `QF_API_RATE_LIMIT` per
minute per IP), `502` Quran Foundation error.

## 6. How it works (and the QF rules it follows)

- **Token:** `POST {auth}/oauth2/token` with `grant_type=client_credentials&scope=content`
  and HTTP Basic `client_id:client_secret`. The token lasts 3600 s and is cached
  until 60 s before expiry. If the server answers `invalid_client`, the client tries
  once more with the secret sent in the POST body.
- **Requests:** every call sends `x-auth-token`, `x-client-id` and a `User-Agent`.
  Without a User-Agent, QF answers 403 `error code: 1010`.
- **Retries:** a 401 gets a fresh token and one retry, and a 429/5xx gets one retry
  after a short wait. Nothing loops.
- **Caching:** responses are cached in `cache/` for `QF_CACHE_TTL` (default 24 h).
  The client never allows more than **7 days**, the maximum the QF Terms permit.
- **Display:** the Quran text is shown unmodified, with `translate="no"` and
  `<meta name="google" content="notranslate">` as QF requires. Tafsir HTML is reduced
  to basic tags before it is printed.
- **Secrets:** the secret only lives in `qf-config.php` (PHP, never sent to browsers).
  Cache files start with `<?php exit; ?>`, and `cache/.htaccess` denies web access.

| Environment | Auth URL | API URL |
|---|---|---|
| Pre-live | `https://prelive-oauth2.quran.foundation` | `https://apis-prelive.quran.foundation` |
| Production | `https://oauth2.quran.foundation` | `https://apis.quran.foundation` |

## 7. Troubleshooting

| Error | Fix |
|---|---|
| `invalid_client` (401 on token) | Wrong ID/secret, app type is not *Backend/server app*, or pre-live credentials with `QF_ENV = 'production'` (or the reverse). Regenerate the secret in the Console if unsure. |
| `invalid_scope` / 403 | The app does not have the `content` scope, or the environment doesn't match. |
| 403 `error code: 1010` | Missing User-Agent. Keep `QF_USER_AGENT` set. |
| 404 for surah 3+ | Normal in pre-live, which only has Surah 1–2. |
| "Could not connect" | Your host blocks outgoing HTTPS. Ask the host's support. |

Help: developers@quran.com
