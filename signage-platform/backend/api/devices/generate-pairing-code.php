<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$d = require_device();
rate_limit('paircode:device:' . $d['device_id'], 30, 3600);
if ($d['user_id'] !== null) fail('This display is already connected to an account', 409, 'ALREADY_PAIRED');
$ttl = (int)app_setting('pairing_code_ttl_minutes', 15);
db_begin();
db_run('DELETE FROM device_pairing_codes WHERE device_id = ? AND used_at IS NULL', [$d['device_id']]);
do {
    $code = '';
    for ($i = 0; $i < PAIRING_LENGTH; $i++) $code .= PAIRING_ALPHABET[random_int(0, strlen(PAIRING_ALPHABET) - 1)];
} while (db_val('SELECT 1 FROM device_pairing_codes WHERE pairing_code_hash = ?', [sha256($code)]));
db_run('INSERT INTO device_pairing_codes (pairing_code_hash, device_id, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)', [sha256($code), $d['device_id'], $ttl]);
db_commit();
ok(['pairing_code' => $code, 'expires_in' => $ttl * 60, 'device_code' => $d['device_code']], 'Pairing code created');
