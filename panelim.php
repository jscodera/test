<?php
// === DEBUG ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === IMPORT CONFIG ===
require_once __DIR__ . '/config.php';

// === SESSION & CSRF ===
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === Log Files ===
$log_files = [
    'telegram_webhook.log' => TG_WEBHOOK_LOG,
    'telegram_approve.log' => TG_APPROVE_LOG,
    'facebook_conversion.log' => FB_PIXEL_LOG,
    'processed_requests.log' => PROCESSED_LOG,
];

// === POST HANDLER ===
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $msg = "❌ CSRF Token hatası!";
    } elseif (isset($_POST['clear_log'])) {
        $label = $_POST['clear_log'];
        if (isset($log_files[$label])) {
            $target = $log_files[$label];
            if (file_exists($target) && is_writable($target)) {
                file_put_contents($target, "");
                $msg = "✅ " . htmlspecialchars($label) . " temizlendi.";
            } else {
                $msg = "❌ Dosya yazılamıyor!";
            }
        } else {
            $msg = "❌ Geçersiz dosya!";
        }
    } elseif (isset($_POST['clear_all'])) {
        $cleared = 0;
        foreach ($log_files as $target) {
            if (file_exists($target) && is_writable($target)) {
                file_put_contents($target, "");
                $cleared++;
            }
        }
        $msg = "✅ {$cleared} log dosyası temizlendi.";
    }
}

function tailFile($file, $lines = 20) {
    if (!file_exists($file)) return ["(Dosya yok)"];
    $data = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $data ? array_slice($data, -$lines) : ["(Boş)"];
}

// === API CALLS ===
$webhook_info = [];
$ch = curl_init("https://api.telegram.org/bot" . TG_BOT_TOKEN . "/getWebhookInfo");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true]);
$res = curl_exec($ch);
curl_close($ch);
if ($res) $webhook_info = json_decode($res, true) ?? [];

$member_count = [];
$ch = curl_init("https://api.telegram.org/bot" . TG_BOT_TOKEN . "/getChatMemberCount?chat_id=" . TG_GROUP_ID);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true]);
$res = curl_exec($ch);
curl_close($ch);
if ($res) $member_count = json_decode($res, true) ?? [];

// === LINK STATS ===
$link_stats = [];
if (file_exists(TG_WEBHOOK_LOG)) {
    $lines = @file(TG_WEBHOOK_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos($line, 'LINK:') !== false) {
                preg_match('/LINK:\s*(\S+)/', $line, $m);
                if (!empty($m[1])) {
                    $link = $m[1];
                    $link_stats[$link] = ($link_stats[$link] ?? 0) + 1;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Telegram Panel</title>
  <style>
    body { font-family: Arial; background: #111; color: #eee; padding: 20px; }
    h1 { color: #FFD700; }
    .box { background: #222; padding: 15px; margin: 15px 0; border-radius: 8px; }
    pre { background: #000; color: #0f0; padding: 10px; overflow:auto; max-height: 300px; font-size: 12px; }
    button { padding: 8px 16px; background: #c00; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
    button:hover { background: #f00; }
    .big-danger-btn { padding: 15px 30px; font-size: 18px; font-weight: bold; background: linear-gradient(45deg, #ff0000, #cc0000); box-shadow: 0 0 15px rgba(255,0,0,0.6); }
    .big-danger-btn:hover { box-shadow: 0 0 25px rgba(255,0,0,0.9); transform: scale(1.05); }
  </style>
</head>
<body>
  <h1>📊 Telegram Rapor Paneli</h1>

  <?php if (!empty($msg)): ?>
    <p style="color:#0f0;"><?= htmlspecialchars($msg) ?></p>
  <?php endif; ?>

  <div class="box" style="text-align:center;">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="clear_all" value="1">
      <button type="submit" class="big-danger-btn">🚮 HEPSİNİ TEMİZLE</button>
    </form>
  </div>

  <div class="box">
    <h2>Webhook Durumu</h2>
    <p><strong>URL:</strong> <?= htmlspecialchars($webhook_info["result"]["url"] ?? 'Yok') ?></p>
    <p><strong>Durum:</strong> <?= ($webhook_info["ok"] ? "✅ Aktif" : "❌ Pasif") ?></p>
    <p><strong>Pending:</strong> <?= htmlspecialchars((string)($webhook_info["result"]["pending_update_count"] ?? 0)) ?></p>
  </div>

  <div class="box">
    <h2>Grup Bilgileri</h2>
    <p><strong>ID:</strong> <?= htmlspecialchars(TG_GROUP_ID) ?></p>
    <p><strong>Üye:</strong> <?= htmlspecialchars((string)($member_count["result"] ?? 'Bilinmiyor')) ?></p>
  </div>

  <div class="box">
    <h2>Katılım Link İstatistikleri</h2>
    <?php if ($link_stats): ?>
      <ul>
        <?php foreach($link_stats as $link => $count): ?>
          <li><?= htmlspecialchars($link) ?> → <?= htmlspecialchars((string)$count) ?> kişi</li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Henüz loglarda katılım isteği yok.</p>
    <?php endif; ?>
  </div>

  <div class="box">
    <h2>📂 Log Dosyaları</h2>
    <?php foreach ($log_files as $label => $file): ?>
      <h3><?= htmlspecialchars($label) ?></h3>
      <form method="POST" style="margin-bottom:10px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="clear_log" value="<?= htmlspecialchars($label) ?>">
        <button type="submit">Temizle</button>
      </form>
      <pre><?php echo htmlspecialchars(implode("\n", tailFile($file))); ?></pre>
    <?php endforeach; ?>
  </div>
</body>
</html>
</html>
        <?php foreach($link_stats as $link => $count): ?>
          <li><?= htmlspecialchars($link) ?> → <?= htmlspecialchars((string)$count) ?> kişi</li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Henüz loglarda katılım isteği yok.</p>
    <?php endif; ?>
  </div>

  <div class="box">
    <h2>📂 Log Dosyaları</h2>
    <?php foreach ([
      'telegram_webhook.log' => $tg_log,
      'telegram_approve.log' => $approve_log,
      'facebook_conversion.log' => $pixel_log,
      'error_log' => $error_log
    ] as $label => $file): ?>
      <h3><?= htmlspecialchars($label) ?> (Son 20 Satır)</h3>
      <form method="POST" style="margin-bottom:10px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="clear_log" value="<?= htmlspecialchars($file) ?>">
        <button type="submit">Temizle</button>
      </form>
      <pre><?php echo htmlspecialchars(implode("\n", tailFile($file))); ?></pre>
    <?php endforeach; ?>
  </div>
</body>
</html>
