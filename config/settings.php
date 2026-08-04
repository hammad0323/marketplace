<?php
/**
 * Static site settings, read via mp_config() in includes/helpers.php.
 * Kept as a plain returned array (not env-driven constants) since
 * there's no admin-editable settings table in this PR's scope — see
 * README "What's deferred".
 */

return [
    'app_name'  => getenv('APP_NAME') ?: 'Marketplace',
    'app_url'   => getenv('APP_URL') ?: 'http://localhost',
    'app_env'   => getenv('APP_ENV') ?: 'local',
    'app_debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),

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
