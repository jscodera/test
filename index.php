<?php
// === IMPORT CONFIG ===
require_once __DIR__ . '/config.php';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(SITE_TITLE) ?></title>
<meta name="description" content="<?= htmlspecialchars(SITE_DESCRIPTION) ?>">

<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){
 if(f.fbq)return;n=f.fbq=function(){n.callMethod?
 n.callMethod.apply(n,arguments):n.queue.push(arguments)};
 if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
 n.queue=[];t=b.createElement(e);t.async=!0;
 t.src=v;s=b.getElementsByTagName(e)[0];
 s.parentNode.insertBefore(t,s)
}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');

fbq('init', '<?= htmlspecialchars(FB_PIXEL_ID) ?>');
fbq('track', 'PageView');
</script>
<noscript>
  <img height="1" width="1" style="display:none"
       src="https://www.facebook.com/tr?id=<?= htmlspecialchars(FB_PIXEL_ID) ?>&ev=PageView&noscript=1"/>
</noscript>

<style>
body{font-family:Arial,system-ui;margin:0;padding:0;background:linear-gradient(135deg,#0a0a33,#1a1a4d,#000);color:#fff;min-height:100vh;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;padding:20px}
.logo{width:130px;height:130px;border-radius:50%;margin-bottom:20px;border:3px solid #FFD700;object-fit:cover}
h1{font-size:2.6rem;margin-bottom:18px;font-weight:bold;color:#FFD700}
p{max-width:640px;font-size:1.1rem;line-height:1.6;margin-bottom:30px;opacity:.95}
.btn-telegram{display:inline-flex;align-items:center;gap:10px;padding:16px 34px;font-size:1.2rem;font-weight:bold;color:#fff;background:linear-gradient(145deg,#0088cc,#005f99);border-radius:50px;text-decoration:none;box-shadow:0 6px 14px rgba(0,0,0,.4);transition:all .3s;animation:pulse 1.8s infinite}
.btn-telegram:hover{transform:scale(1.08);background:linear-gradient(145deg,#00aaff,#0088cc);box-shadow:0 10px 24px rgba(0,0,0,.5)}
.btn-telegram img{width:24px;height:24px}
@keyframes pulse{0%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,136,204,.6)}70%{transform:scale(1.06);box-shadow:0 0 0 18px rgba(0,136,204,0)}100%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,136,204,0)}}
</style>
</head>
<body>

<img src="<?= htmlspecialchars(SITE_LOGO_URL) ?>" alt="<?= htmlspecialchars(SITE_TITLE) ?>" class="logo" loading="lazy" width="130" height="130" title="<?= htmlspecialchars(SITE_TITLE) ?>">
<h1><?= htmlspecialchars(SITE_TITLE) ?></h1>
<p>
  #KATILIMENDEKSİ #BİST100 #BORSA #ANALİZ <br><br>
  🔥 Ücretsiz VIP Grubu <br>
  📊 Risk Yönetimi <br>
  💼 Ücretsiz Portföy Kontrolü <br>
  ⭐️ Temel Teknik Analiz
</p>

<a href="<?= htmlspecialchars(TELEGRAM_JOIN_LINK) ?>" 
   target="_blank" rel="noopener noreferrer" 
   class="btn-telegram" id="cta-telegram" 
   aria-label="Telegram grubuna katıl" 
   title="Telegram Grubuna Katıl">
  <img src="<?= htmlspecialchars(TELEGRAM_ICON_URL) ?>" 
       alt="Telegram" width="24" height="24" loading="lazy">
  Telegram Grubuna Katıl
</a>

<script>
(function(){
  var KEY='tg_addtocart_once';
  var btn=document.getElementById('cta-telegram');
  if(!btn) return;

  btn.addEventListener('click',function(e){
    e.preventDefault();

    if(localStorage.getItem(KEY)){
      window.open(btn.href,'_blank');
      return;
    }

    try{ localStorage.setItem(KEY,'1'); }catch(err){}

    if(typeof fbq==='function'){
      fbq('track','AddToCart',{
        platform:'telegram',
        currency:'TRY',
        value:1
      });
    }

    setTimeout(function(){
      window.open(btn.href,'_blank');
    },300);
  });
})();
</script>

</body>
</html>
       alt="Telegram" width="24" height="24" loading="lazy">
  Telegram Grubuna Katıl
</a>

<script>
// Telegram butonuna tıklanınca tek seferlik AddToCart gönder
(function(){
  var KEY='tg_addtocart_once';
  var btn=document.getElementById('cta-telegram');
  if(!btn) return;

  btn.addEventListener('click',function(e){
    e.preventDefault(); // yönlendirmeyi durdur

    if(localStorage.getItem(KEY)){
      console.log("AddToCart daha önce gönderildi → direkt yönlendiriliyor");
      window.open(btn.href,'_blank');
      return;
    }

    // İlk kez tıklama → işaretle
    try{ localStorage.setItem(KEY,'1'); }catch(err){ window.__addToCartFired=true; }

    if(typeof fbq==='function'){
      fbq('track','AddToCart',{
        platform:'telegram',
        currency:'TRY',
        value:1,
        debug:true
      });
      console.log("✅ AddToCart gönderildi (ilk tıklama)");
    } else {
      console.warn("❌ fbq tanımlı değil, Pixel yüklenmemiş olabilir!");
    }

    // 0.3 sn bekle → sonra yönlendir
    setTimeout(function(){
      window.open(btn.href,'_blank');
    },300);
  });
})();
</script>

</body>
</html>
