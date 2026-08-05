<?php
/**
 * Session bootstrap. Every page reaches this indirectly through
 * config/config.php — nothing should call session_start() anywhere
 * else.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
