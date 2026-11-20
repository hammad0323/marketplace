<?php
/** Public APIs: platform configuration, shop profile and catalogue (no sign-in). */

function api_routes_public(): array
{
    return [
        ['GET', '/platform', function () {
            api_ok(['name' => platform_name(), 'tagline' => setting('platform_tagline'), 'base_url' => url(''), 'support_phone' => setting('support_phone'), 'support_email' => setting('support_email'),
                'play_store_url' => setting('play_store_url'), 'app_store_url' => setting('app_store_url'), 'privacy_notice' => setting('privacy_notice'),
                'max_upload_mb' => (int) setting('max_upload_mb', 25), 'accepted_files' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'docx'],
                'docx_conversion' => SOFFICE_PATH !== '' && is_executable(SOFFICE_PATH), 'languages' => ['en'], 'rtl_ready' => true]);
        }],
        ['GET', '/shops/([A-Za-z0-9-]{6,20})', function ($code) {
            $shop = api_shop($code);
            api_ok(['shop' => shop_public($shop), 'categories' => shop_catalog((int) $shop['id'])]);
        }],
        ['GET', '/shops/([A-Za-z0-9-]{6,20})/wifi\.png', function ($code) {
            $shop = api_shop($code);
            $w = shop_public_wifi((int) $shop['id']);
            if (!$w) {
                api_fail('Wi-Fi details are not shared by this shop.', 404); // never expose private networks
            }
            header('Content-Type: image/png');
            header('Cache-Control: private, max-age=300');
            echo qr_png($w['qr_payload'], ['scale' => 10]);
            exit;
        }],
        ['GET', '/catalog/categories', function () {
            api_ok(['categories' => db_all('SELECT id, code, name, name_ur, description, icon, pricing_basis FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order'),
                'options' => db_all('SELECT id, option_group, code, label, width_mm, height_mm FROM printing_options WHERE is_active = 1 ORDER BY option_group, sort_order')]);
        }],
    ];
}
