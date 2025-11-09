<?php
// === DEBUG ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === CONFIG ===
$bot_token   = "8398727739:AAEawi1_3_tGb_GpttqIB85C4_pEggzpPPQ"; 
$group_id    = "-1001599745285";
$group_link  = "https://t.me/+HBQzH1l9R5c2MThh";

// === Log dosyaları ===
$tg_log      = __DIR__."/telegram_webhook.log";
$approve_log = __DIR__."/telegram_approve.log";
$pixel_log   = __DIR__."/facebook_conversion.log";
$error_log   = __DIR__."/error_log";

$log_files = [$tg_log, $approve_log, $pixel_log, $error_log];

// Tek dosya temizleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    $file = $_POST['clear_log'];
    if (in_array($file, $log_files)) {
        file_put_contents($file, "");
        $msg = basename($file) . " başarıyla temizlendi.";
    }
}

// Hepsini temizleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    foreach ($log_files as $f) {
        if (file_exists($f)) file_put_contents($f, "");
    }
    $msg = "Tüm log dosyaları başarıyla temizlendi.";
}

function tailFile($file, $lines = 20) {
    if (!file_exists($file)) return ["(Dosya yok)"];
    $data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return array_slice($data, -$lines);
}

// === Webhook durumu ===
$webhook_info = json_decode(file_get_contents("https://api.telegram.org/bot$bot_token/getWebhookInfo"), true);

// === Grup üye sayısı ===
$member_count = json_decode(file_get_contents("https://api.telegram.org/bot$bot_token/getChatMemberCount?chat_id=$group_id"), true);

// === İstek analizi ===
$link_stats = [];
if (file_exists($tg_log)) {
    $lines = file($tg_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'LINK:') !== false) {
            preg_match('/LINK:\s*(\S+)/', $line, $m);
            if (!empty($m[1])) {
                $link = $m[1];
                if (!isset($link_stats[$link])) $link_stats[$link] = 0;
                $link_stats[$link]++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Telegram Panel - Borsa Dünyası</title>
  <style>
    body { font-family: Arial; background: #111; color: #eee; padding: 20px; }
    h1 { color: #FFD700; }
    .box { background: #222; padding: 15px; margin: 15px 0; border-radius: 8px; }
    pre { background: #000; color: #0f0; padding: 10px; overflow:auto; }
    button { padding: 6px 12px; background:#c00; color:#fff; border:none; border-radius:5px; cursor:pointer; }
    button:hover { background:#f00; }

    /* Büyük kırmızı buton */
    .big-danger-btn {
      padding: 15px 30px;
      font-size: 20px;
      font-weight: bold;
      background: linear-gradient(45deg, #ff0000, #cc0000);
      color: #fff;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      box-shadow: 0 0 15px rgba(255,0,0,0.6);
      transition: all 0.2s ease-in-out;
    }
    .big-danger-btn:hover {
      background: linear-gradient(45deg, #ff3333, #ff0000);
      box-shadow: 0 0 25px rgba(255,0,0,0.9);
      transform: scale(1.05);
    }
  </style>
</head>
<body>
  <h1>📊 Telegram Rapor Paneli</h1>

  <?php if (!empty($msg)): ?>
    <p style="color:#0f0;"><?= htmlspecialchars($msg) ?></p>
  <?php endif; ?>

  <!-- Hepsini temizle butonu -->
  <div class="box" style="text-align:center;">
    <form method="POST">
      <input type="hidden" name="clear_all" value="1">
      <button type="submit" class="big-danger-btn">🚮 HEPSİNİ TEMİZLE</button>
    </form>
  </div>

  <div class="box">
    <h2>Webhook Durumu</h2>
    <p><strong>URL:</strong> <?= $webhook_info["result"]["url"] ?? 'Yok' ?></p>
    <p><strong>Durum:</strong> <?= ($webhook_info["ok"] ? "✅ Aktif" : "❌ Pasif") ?></p>
    <p><strong>Pending Update:</strong> <?= $webhook_info["result"]["pending_update_count"] ?? 0 ?></p>
  </div>

  <div class="box">
    <h2>Grup Bilgileri</h2>
    <p><strong>Grup ID:</strong> <?= $group_id ?></p>
    <p><strong>Üye Sayısı:</strong> <?= $member_count["result"] ?? 'Bilinmiyor' ?></p>
  </div>

  <div class="box">
    <h2>Katılım Link İstatistikleri</h2>
    <?php if ($link_stats): ?>
      <ul>
        <?php foreach($link_stats as $link => $count): ?>
          <li><?= $link ?> → <?= $count ?> kişi</li>
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
      <h3><?= $label ?> (Son 20 Satır)</h3>
      <form method="POST" style="margin-bottom:10px;">
        <input type="hidden" name="clear_log" value="<?= $file ?>">
        <button type="submit">Temizle</button>
      </form>
      <pre><?php echo implode("\n", tailFile($file)); ?></pre>
    <?php endforeach; ?>
  </div>
</body>
</html>
