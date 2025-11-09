<?php
// Facebook Ayarları
define('FB_PIXEL_ID', '4117707111847832');
define('FB_ACCESS_TOKEN', 'EAAfr0PPevagBPvymU0cNJmPtFaDx4q5NaHxZAVUwPVQfOTfa1Ut0JZCQg6b5vaTfbdq9ZCSNkP9dD5quKSTfbhR9aNn1FCFNrKU46ftLQKvlBzWRkkFh1tZCfFbqhbrEfRuuOXrIx7uUBbjN6vDX3fp8PQy7UAYgt0nEGAoVau88r2V6w33sZBOD3Uw4B58jySwZDZD');
define('FB_API_VERSION', 'v19.0');
define('FB_RETRY_MAX', 3);

// Telegram Grup ve Bot Ayarları
define('TG_BOT_TOKEN', '8398727739:AAEawi1_3_tGb_GpttqIB85C4_pEggzpPPQ');
define('TG_GROUP_ID', '-1001599745285');

// Telegram Link Ayarları
define('TG_INVITE_LINK', 'https://t.me/+BL990ELDb_5jOTE0');         // Ana davet linki
define('TG_ALLOWED_PREFIX', 'https://t.me/+BL');                     // İzin verilen link prefix
define('TG_GROUP_LINK', 'https://t.me/+BL990ELDb_5jOTE0');          // Panel için grup linki
define('TG_RETRY_MAX', 2);

// Debug Ayarları
define('DEBUG', false);

// Log Dosya Yolları
define('LOG_DIR', __DIR__);
define('TG_WEBHOOK_LOG', LOG_DIR . '/telegram_webhook.log');
define('TG_APPROVE_LOG', LOG_DIR . '/telegram_approve.log');
define('FB_PIXEL_LOG', LOG_DIR . '/facebook_conversion.log');
define('ERROR_LOG', LOG_DIR . '/error_log');
define('PROCESSED_LOG', LOG_DIR . '/processed_requests.log');

// Log dosyalarının varlığını kontrol et
$log_files = [
    TG_WEBHOOK_LOG,
    TG_APPROVE_LOG,
    FB_PIXEL_LOG,
    ERROR_LOG,
    PROCESSED_LOG
];

foreach ($log_files as $file) {
    if (!file_exists($file)) {
        @touch($file);
        @chmod($file, 0664);
    }
}
