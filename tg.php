<?php
// === DEBUG PANEL ===
if (isset($_GET['debug'])) {
    $logs = [
        "Telegram Webhook" => __DIR__."/telegram_webhook.log",
        "Telegram Approve" => __DIR__."/telegram_approve.log",
        "Facebook Pixel"   => __DIR__."/facebook_conversion.log",
        "Processed"        => __DIR__."/processed_requests.log",
    ];

    header("Content-Type: text/plain; charset=utf-8");
    foreach ($logs as $name => $file) {
        echo "===== {$name} =====\n";
        if (file_exists($file)) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = array_slice($lines, -20); // son 20 satır
            foreach ($tail as $l) {
                echo $l."\n";
            }
        } else {
            echo "(yok)\n";
        }
        echo "\n";
    }
    exit;
}


// Single-file webhook: Telegram join-request approve + Facebook CAPI send

// === DEBUG MODE (turn off in production) ===
$DEBUG = getenv('DEBUG') === '1';
if ($DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// === CONFIG (use env vars where possible) ===
$pixel_id       = getenv('FB_PIXEL_ID')     ?: "4117707111847832";
$access_token   = getenv('FB_ACCESS_TOKEN') ?: "EAAfr0PPevagBPvymU0cNJmPtFaDx4q5NaHxZAVUwPVQfOTfa1Ut0JZCQg6b5vaTfbdq9ZCSNkP9dD5quKSTfbhR9aNn1FCFNrKU46ftLQKvlBzWRkkFh1tZCfFbqhbrEfRuuOXrIx7uUBbjN6vDX3fp8PQy7UAYgt0nEGAoVau88r2V6w33sZBOD3Uw4B58jySwZDZD";
$bot_token      = getenv('TG_BOT_TOKEN')    ?: "8398727739:AAEawi1_3_tGb_GpttqIB85C4_pEggzpPPQ";
$fb_api_version = getenv('FB_API_VERSION')  ?: 'v19.0';

// Retry config
$fb_retry_max  = getenv('FB_RETRY_MAX') ? intval(getenv('FB_RETRY_MAX')) : 3;
$tg_retry_max  = getenv('TG_RETRY_MAX') ? intval(getenv('TG_RETRY_MAX')) : 2;

// === Log dosyaları ===
$dir = __DIR__;
$tg_log       = "$dir/telegram_webhook.log";
$pixel_log    = "$dir/facebook_conversion.log";
$approve_log  = "$dir/telegram_approve.log";
$processed_log= "$dir/processed_requests.log";

// ensure log files exist and are writable
foreach ([$tg_log, $pixel_log, $approve_log, $processed_log] as $f) {
    if (!file_exists($f)) {
        @touch($f);
        @chmod($f, 0664);
    }
}

// === Utilities ===
function write_log($file, $level, $message) {
    $line = date("Y-m-d H:i:s") . " | {$level} | " . $message . PHP_EOL;
    $fp = @fopen($file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $line);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function is_already_processed($hash) {
    global $processed_log;
    $found = false;
    $fp = @fopen($processed_log, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        rewind($fp);
        while (!feof($fp)) {
            $line = trim(fgets($fp));
            if ($line === $hash) { $found = true; break; }
        }
        if (!$found) {
            fwrite($fp, $hash . PHP_EOL);
        }
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    return $found;
}

// cURL helper: JSON POST + retry + exponential backoff
function curl_post_json_retry($url, $jsonPayload, $headers = [], $maxAttempts = 1, $timeout = 10) {
    $attempt = 0;
    $lastErr = null;
    while ($attempt < $maxAttempts) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        $hdrs = array_merge(["Content-Type: application/json"], $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $res = curl_exec($ch);
        $curlErr = ($res === false) ? curl_error($ch) : null;
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res !== false && ($http_code >= 200 && $http_code < 300)) {
            return ['success' => true, 'http_code' => $http_code, 'response' => $res];
        }

        $lastErr = [
            'curlErr'   => $curlErr,
            'http_code' => $http_code,
            'response'  => $res,
        ];

        $attempt++;
        if ($attempt < $maxAttempts) {
            sleep(min(8, (1 << $attempt)));
        }
    }
    return ['success' => false, 'error' => $lastErr];
}

// === Process incoming webhook ===
$input = file_get_contents("php://input");
if (!$input) {
    http_response_code(204);
    exit;
}

write_log($tg_log, 'RAW', $input);

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    write_log($tg_log, 'ERROR', 'JSON ERROR: ' . json_last_error_msg());
    http_response_code(400);
    echo "Bad Request";
    exit;
}

if (!isset($update['chat_join_request'])) {
    http_response_code(200);
    echo "IGNORED";
    exit;
}

// Extract fields
$join   = $update['chat_join_request'];
$from   = $join['from'] ?? [];
$name   = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
$tg_id  = $from['id'] ?? null;
$chatid = $join['chat']['id'] ?? null;

// Determine invite link
$usedLink = '';
if (!empty($join['invite_link']['invite_link'])) {
    $usedLink = $join['invite_link']['invite_link'];
} elseif (!empty($join['invite_link']['url'])) {
    $usedLink = $join['invite_link']['url'];
} elseif (isset($join['invite_link']) && is_string($join['invite_link'])) {
    $usedLink = $join['invite_link'];
}

// Require invite link — only count link-joins
if (empty($usedLink)) {
    write_log($tg_log, 'INFO', "SKIP_NO_LINK: user={$tg_id} chat={$chatid} name=\"{$name}\"");
    http_response_code(200);
    echo "SKIPPED_NO_LINK";
    exit;
}

// --- Sadece 'https://t.me/+NJ' ile başlayan linkleri kabul et ---
$allowed_prefix = "https://t.me/+NJ";
if (strpos($usedLink, $allowed_prefix) !== 0) {
    write_log($tg_log, 'INFO', "SKIP_OTHER_LINK: beklenen={$allowed_prefix} gelen={$usedLink}");
    http_response_code(200);
    echo "IGNORED_OTHER_LINK";
    exit;
}

if (!$tg_id || !$chatid) {
    write_log($tg_log, 'WARN', "INVALID: missing tg_id or chatid for payload");
    http_response_code(400);
    echo "Invalid";
    exit;
}

// idempotency
$event_hash = hash('sha256', sprintf('%s|%s|%s', $tg_id, $chatid, $usedLink));
if (is_already_processed($event_hash)) {
    write_log($tg_log, 'INFO', "DUPLICATE_SKIP: {$tg_id} chat={$chatid} link={$usedLink}");
    http_response_code(200);
    echo "OK";
    exit;
}

write_log($tg_log, 'INFO', "REQUEST: name=\"{$name}\" user={$tg_id} chat={$chatid} link={$usedLink}");

// Approve
$approved = approveTelegramUser($tg_id, $chatid);
if (!$approved) {
    write_log($tg_log, 'WARN', "APPROVE_FAILED: user={$tg_id} chat={$chatid}");
    http_response_code(200);
    echo "OK";
    exit;
}

// Pixel config kontrolü
if (empty($pixel_id) || empty($access_token)) {
    write_log($pixel_log, 'ERROR', "PIXEL_CONFIG_MISSING: skipping FB CAPI send for user={$tg_id}");
    http_response_code(200);
    echo "OK";
    exit;
}

// Send to Facebook
sendToFacebookPixel($tg_id, $name, $chatid, $usedLink);
write_log($tg_log, 'INFO', "PROCESSED: user={$tg_id} chat={$chatid} link={$usedLink}");

http_response_code(200);
echo "OK";
exit;

// === Functions ===
function approveTelegramUser($user_id, $chat_id) {
    global $bot_token, $approve_log, $tg_retry_max;

    $url  = "https://api.telegram.org/bot$bot_token/approveChatJoinRequest";
    $data = json_encode(['chat_id' => $chat_id, 'user_id' => $user_id]);

    $result = curl_post_json_retry($url, $data, ['Content-Type: application/json'], $tg_retry_max ?: 1, 8);

    if ($result['success']) {
        write_log($approve_log, 'INFO', "APPROVE_HTTP_OK: user={$user_id} chat={$chat_id} RESP=" . $result['response']);
        $decoded = json_decode($result['response'], true);
        return is_array($decoded) && isset($decoded['ok']) && $decoded['ok'] === true;
    } else {
        $err = $result['error'];
        write_log($approve_log, 'ERROR', 'APPROVE_FAIL: curlErr=' . ($err['curlErr'] ?? 'null') .
            ' http=' . ($err['http_code'] ?? 'null') .
            ' resp=' . substr((string)($err['response'] ?? ''),0,500));
        return false;
    }
}

function sendToFacebookPixel($tg_id, $name, $chatid, $usedLink) {
    global $pixel_id, $access_token, $pixel_log, $fb_api_version, $fb_retry_max;

    $url = "https://graph.facebook.com/{$fb_api_version}/{$pixel_id}/events";

    $client_ip        = $_SERVER['REMOTE_ADDR'] ?? null;
    $client_ua        = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $event_source_url = $_SERVER['HTTP_REFERER'] ?? null;

    $payloadArr = [
        'data' => [[
            'event_name'    => 'Purchase',
            'event_time'    => time(),
            'action_source' => 'website',
            'user_data'     => array_filter([
                'external_id'        => hash('sha256', (string)$tg_id),
                'client_ip_address'  => $client_ip,
                'client_user_agent'  => $client_ua,
            ]),
            'custom_data'   => [
                'content_name' => 'Telegram Group Join',
                'group_id'     => $chatid,
                'user_name'    => $name,
                'invite_link'  => $usedLink,
                'currency'     => 'TRY',
                'value'        => 1,
                'event_source_url' => $event_source_url
            ]
        ]]
    ];

    $jsonPayload = json_encode($payloadArr);
    if ($jsonPayload === false) {
        write_log($pixel_log, 'ERROR', 'PAYLOAD_JSON_ERROR: ' . json_last_error_msg());
        return;
    }

    $result = curl_post_json_retry($url . '?access_token=' . urlencode($access_token), $jsonPayload, ['Content-Type: application/json'], $fb_retry_max ?: 1, 8);

    if ($result['success']) {
        $decoded = json_decode($result['response'], true);
        if (isset($decoded['events_received'])) {
            write_log($pixel_log, 'INFO', "FB_SUCCESS events=" . $decoded['events_received'] . " HTTP=" . $result['http_code']);
        } elseif (isset($decoded['error'])) {
            write_log($pixel_log, 'ERROR', "FB_API_ERROR code=" . ($decoded['error']['code'] ?? '') . " msg=" . ($decoded['error']['message'] ?? ''));
        } else {
            write_log($pixel_log, 'WARN', "FB_UNKNOWN resp=" . substr($result['response'] ?? '', 0, 1000));
        }
    } else {
        $err = $result['error'];
        write_log($pixel_log, 'ERROR', 'FB_CURL_FAIL: curlErr=' . ($err['curlErr'] ?? 'null') .
            ' http=' . ($err['http_code'] ?? 'null') .
            ' resp=' . substr((string)($err['response'] ?? ''),0,1000));
    }
}
?> 