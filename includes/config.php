<?php
/**
 * Global application configuration.
 * All values are overridable via environment variables so no secrets
 * are hardcoded in source control.
 */

return [
    'app_name'  => getenv('APP_NAME') ?: 'Marketplace',
    'app_url'   => getenv('APP_URL') ?: 'http://localhost',
    'app_env'   => getenv('APP_ENV') ?: 'local',
    'app_debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),

    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'marketplace',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'marketplace_session',
        'lifetime' => 60 * 60 * 24 * 7, // 7 days
    ],

    // The two sellable marketplace types plus the platform-owned store.
    // Kept here (not hardcoded in pages) so a new marketplace type can
    // be introduced by adding a row to marketplace_types + here.
    'marketplace_types' => [
        'artisan'  => ['label' => 'Artisan Marketplace', 'badge' => '🏺 Handmade'],
        'business' => ['label' => 'Business Shops',      'badge' => '🏪 Business Shop'],
        'official' => ['label' => 'Official Store',      'badge' => '⭐ Official Store'],
    ],
];
