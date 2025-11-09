<?php
// === ENVIRONMENT BASED CONFIG ===
// Production ortamında: .env dosyasından yükle veya server env vars

// Facebook Configuration
define('FB_PIXEL_ID', getenv('FB_PIXEL_ID') ?: '4117707111847832');
define('FB_ACCESS_TOKEN', getenv('FB_ACCESS_TOKEN') ?: 'EAAfr0PPevagBPvymU0cNJmPtFaDx4q5NaHxZAVUwPVQfOTfa1Ut0JZCQg6b5vaTfbdq9ZCSNkP9dD5quKSTfbhR9aNn1FCFNrKU46ftLQKvlBzWRkkFh1tZCfFbqhbrEfRuuOXrIx7uUBbjN6vDX3fp8PQy7UAYgt0nEGAoVau88r2V6w33sZBOD3Uw4B58jySwZDZD');
define('FB_API_VERSION', getenv('FB_API_VERSION') ?: 'v19.0');
define('FB_RETRY_MAX', (int)(getenv('FB_RETRY_MAX') ?: 3));

// Telegram Configuration
define('TG_BOT_TOKEN', getenv('TG_BOT_TOKEN') ?: '8398727739:AAEawi1_3_tGb_GpttqIB85C4_pEggzpPPQ');
define('TG_GROUP_ID', getenv('TG_GROUP_ID') ?: '-1001599745285');
define('TG_ALLOWED_PREFIX', getenv('TG_ALLOWED_PREFIX') ?: 'https://t.me/+NJ');
define('TG_RETRY_MAX', (int)(getenv('TG_RETRY_MAX') ?: 2));

// === WEB UI Configuration ===
define('SITE_TITLE', getenv('SITE_TITLE') ?: 'BoRSA DÜNYASI');
define('SITE_DESCRIPTION', getenv('SITE_DESCRIPTION') ?: '#KATILIMENDEKSİ #BİST100 #BORSA #ANALİZ | Ücretsiz VIP Grubu, Risk Yönetimi, Portföy Kontrolü ve Temel Teknik Analiz.');
define('SITE_LOGO_URL', getenv('SITE_LOGO_URL') ?: 'https://i.imgur.com/FJNxUvk.jpeg');
define('TELEGRAM_ICON_URL', getenv('TELEGRAM_ICON_URL') ?: 'https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg');
define('TELEGRAM_JOIN_LINK', getenv('TELEGRAM_JOIN_LINK') ?: 'https://t.me/+NJKLetIKDa0wZmVh');

// Debug Mode
define('DEBUG', getenv('DEBUG') === '1' || getenv('DEBUG') === 'true');

// Paths
define('LOG_DIR', __DIR__ . '/logs');

// Log Files
define('TG_WEBHOOK_LOG', LOG_DIR . '/telegram_webhook.log');
define('TG_APPROVE_LOG', LOG_DIR . '/telegram_approve.log');
define('FB_PIXEL_LOG', LOG_DIR . '/facebook_conversion.log');
define('PROCESSED_LOG', LOG_DIR . '/processed_requests.log');

// Initialize log directory
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Ensure all log files exist
$log_files = [
    TG_WEBHOOK_LOG,
    TG_APPROVE_LOG,
    FB_PIXEL_LOG,
    PROCESSED_LOG
];

foreach ($log_files as $file) {
    if (!file_exists($file)) {
        @touch($file);
        @chmod($file, 0664);
    }
}

// === ERROR HANDLING ===
if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
    ini_set('error_log', LOG_DIR . '/php_error.log');
}
?>
