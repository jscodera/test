<?php
// === IMPORT CONFIG ===
require_once __DIR__ . '/config.php';

// === DEBUG PANEL ===
if (isset($_GET['debug'])) {
    $logs = [
        "Telegram Webhook" => TG_WEBHOOK_LOG,
        "Telegram Approve" => TG_APPROVE_LOG,
        "Facebook Pixel"   => FB_PIXEL_LOG,
        "Processed"        => PROCESSED_LOG,
    ];

    header("Content-Type: text/plain; charset=utf-8");
    foreach ($logs as $name => $file) {
        echo "===== {$name} =====\n";
        if (file_exists($file)) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tail = $lines ? array_slice($lines, -20) : [];
            foreach ($tail as $l) {
                echo $l . "\n";
            }
        } else {
            echo "(yok)\n";
        }
        echo "\n";
    }
    exit;
}

// === UTILITIES ===
function write_log($file, $level, $message) {
    if (!$file) return false;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) return false;
    
    $line = date("Y-m-d H:i:s") . " | {$level} | " . $message . PHP_EOL;
    $fp = @fopen($file, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $line);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    return false;
}

function is_already_processed($hash) {
    $processed_log = PROCESSED_LOG;
    if (!$processed_log) return false;
    
    $found = false;
    $fp = @fopen($processed_log, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        rewind($fp);
        while (!feof($fp)) {
            $line = trim(fgets($fp));
            if ($line === $hash) {
                $found = true;
                break;
            }
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

function curl_post_json_retry($url, $jsonPayload, $headers = [], $maxAttempts = 1, $timeout = 10) {
    $attempt = 0;
    $res = null;
    $curlErr = null;
    $http_code = 0;
    
    while ($attempt < $maxAttempts) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => array_merge(["Content-Type: application/json"], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $res = curl_exec($ch);
        $curlErr = ($res === false) ? curl_error($ch) : null;
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res !== false && ($http_code >= 200 && $http_code < 300)) {
            return ['success' => true, 'http_code' => $http_code, 'response' => $res];
        }

        $attempt++;
        if ($attempt < $maxAttempts) {
            sleep(min(8, (1 << $attempt)));
        }
    }
    return ['success' => false, 'error' => ['curlErr' => $curlErr, 'http_code' => $http_code, 'response' => $res]];
}

// === MAIN WEBHOOK HANDLER ===
$input = file_get_contents("php://input");
if (!$input) {
    http_response_code(204);
    exit;
}

write_log(TG_WEBHOOK_LOG, 'RAW', substr($input, 0, 500));

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    write_log(TG_WEBHOOK_LOG, 'ERROR', 'JSON_ERROR: ' . json_last_error_msg());
    http_response_code(400);
    exit;
}

if (!isset($update['chat_join_request'])) {
    http_response_code(200);
    exit;
}

// Extract fields
$join   = $update['chat_join_request'] ?? [];
$from   = $join['from'] ?? [];
$tg_id  = isset($from['id']) ? (int)$from['id'] : null;
$chatid = isset($join['chat']['id']) ? (int)$join['chat']['id'] : null;

if (!$tg_id || !$chatid) {
    write_log(TG_WEBHOOK_LOG, 'WARN', 'INVALID_IDS');
    http_response_code(400);
    exit;
}

$name = trim(trim($from['first_name'] ?? '') . ' ' . trim($from['last_name'] ?? ''));
$usedLink = $join['invite_link']['invite_link'] ?? $join['invite_link']['url'] ?? '';

if (empty($usedLink)) {
    write_log(TG_WEBHOOK_LOG, 'INFO', "SKIP_NO_LINK: user={$tg_id}");
    http_response_code(200);
    exit;
}

if (strpos($usedLink, TG_ALLOWED_PREFIX) !== 0) {
    write_log(TG_WEBHOOK_LOG, 'INFO', "SKIP_OTHER_LINK: {$usedLink}");
    http_response_code(200);
    exit;
}

$event_hash = hash('sha256', sprintf('%d|%d|%s', $tg_id, $chatid, $usedLink));
if (is_already_processed($event_hash)) {
    write_log(TG_WEBHOOK_LOG, 'INFO', "DUPLICATE_SKIP: user={$tg_id}");
    http_response_code(200);
    exit;
}

write_log(TG_WEBHOOK_LOG, 'INFO', "REQUEST: name=\"{$name}\" user={$tg_id} chat={$chatid}");

$approved = approveTelegramUser($tg_id, $chatid);
if (!$approved) {
    write_log(TG_WEBHOOK_LOG, 'WARN', "APPROVE_FAILED: user={$tg_id}");
    http_response_code(200);
    exit;
}

sendToFacebookPixel($tg_id, $name, $chatid, $usedLink);
write_log(TG_WEBHOOK_LOG, 'INFO', "PROCESSED: user={$tg_id}");

http_response_code(200);
exit;

// === FUNCTIONS ===
function approveTelegramUser($user_id, $chat_id) {
    $bot_token = TG_BOT_TOKEN;
    $retry_max = TG_RETRY_MAX;
    
    $url = "https://api.telegram.org/bot{$bot_token}/approveChatJoinRequest";
    $data = json_encode(['chat_id' => $chat_id, 'user_id' => $user_id]);

    $result = curl_post_json_retry($url, $data, [], $retry_max, 8);

    if ($result['success']) {
        $decoded = json_decode($result['response'], true);
        $success = is_array($decoded) && ($decoded['ok'] ?? false) === true;
        write_log(TG_APPROVE_LOG, 'INFO', "APPROVE: user={$user_id} result=" . ($success ? 'OK' : 'FAIL'));
        return $success;
    } else {
        write_log(TG_APPROVE_LOG, 'ERROR', "APPROVE_FAIL: user={$user_id} http=" . ($result['error']['http_code'] ?? 'unknown'));
        return false;
    }
}

function sendToFacebookPixel($tg_id, $name, $chatid, $usedLink) {
    $pixel_id = FB_PIXEL_ID;
    $access_token = FB_ACCESS_TOKEN;
    $fb_version = FB_API_VERSION;
    $retry_max = FB_RETRY_MAX;

    $url = "https://graph.facebook.com/{$fb_version}/{$pixel_id}/events";

    $payloadArr = [
        'data' => [[
            'event_name' => 'Purchase',
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => array_filter([
                'external_id' => hash('sha256', (string)$tg_id),
                'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]),
            'custom_data' => [
                'content_name' => 'Telegram Group Join',
                'group_id' => $chatid,
                'user_name' => $name,
                'currency' => 'TRY',
                'value' => 1,
            ]
        ]]
    ];

    $jsonPayload = json_encode($payloadArr);
    if ($jsonPayload === false) {
        write_log(FB_PIXEL_LOG, 'ERROR', 'JSON_ERROR: ' . json_last_error_msg());
        return;
    }

    $result = curl_post_json_retry($url . '?access_token=' . urlencode($access_token), $jsonPayload, [], $retry_max, 8);

    if ($result['success']) {
        $decoded = json_decode($result['response'], true);
        if (isset($decoded['events_received'])) {
            write_log(FB_PIXEL_LOG, 'INFO', "SUCCESS: events=" . $decoded['events_received']);
        } else {
            write_log(FB_PIXEL_LOG, 'WARN', "RESPONSE: " . substr($result['response'], 0, 200));
        }
    } else {
        write_log(FB_PIXEL_LOG, 'ERROR', "FAIL: http=" . ($result['error']['http_code'] ?? 'unknown'));
    }
}
?>