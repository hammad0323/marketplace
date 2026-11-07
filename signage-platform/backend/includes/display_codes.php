<?php
/**
 * Display codes: a short code per screen / playlist that any device running
 * the app can enter ("Show Display") to play that content — no pairing needed.
 */

function normalize_display_code(string $raw): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
}

function generate_display_code(): string
{
    do {
        $c = '';
        for ($i = 0; $i < 6; $i++) $c .= PAIRING_ALPHABET[random_int(0, strlen(PAIRING_ALPHABET) - 1)];
    } while (db_val('SELECT 1 FROM display_codes WHERE code = ?', [$c]));
    return $c;
}

/** Code row for a screen/playlist the user owns; created on first use. */
function display_code_for(string $kind, int $id, int $userId, bool $create = true): ?array
{
    $col = $kind === 'screen' ? 'screen_id' : 'playlist_id';
    $row = db_one("SELECT * FROM display_codes WHERE {$col} = ?", [$id]);
    if ($row || !$create) return $row;
    db_run("INSERT INTO display_codes (code, user_id, {$col}) VALUES (?, ?, ?)", [generate_display_code(), $userId, $id]);
    return db_one("SELECT * FROM display_codes WHERE {$col} = ?", [$id]);
}

/** Resolve screen_id / playlist_id from input to [kind, row] with ownership check. */
function display_code_target(array $in, array $user): array
{
    if (v_present($in, 'playlist_id')) return ['playlist', owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $user)];
    if (v_present($in, 'screen_id')) return ['screen', owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $user)];
    v_error('screen_id', 'screen_id or playlist_id is required');
}

function present_display_code(array $c, string $kind, array $target): array
{
    return [
        'code' => $c['code'],
        'kind' => $kind,
        'screen_id' => $c['screen_id'] !== null ? (int)$c['screen_id'] : null,
        'playlist_id' => $c['playlist_id'] !== null ? (int)$c['playlist_id'] : null,
        'name' => $kind === 'screen' ? $target['screen_name'] : $target['playlist_name'],
        'is_published' => $target['published_version'] !== null,
        'view_count' => (int)$c['view_count'],
        'last_used_at' => $c['last_used_at'],
    ];
}
