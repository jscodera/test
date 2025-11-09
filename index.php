<?php
// index.php — tek dosya, tek seferlik AddToCart + debug
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BoRSA DÜNYASI</title>
<meta name="description" content="#KATILIMENDEKSİ #BİST100 #BORSA #ANALİZ | Ücretsiz VIP Grubu, Risk Yönetimi, Portföy Kontrolü ve Temel Teknik Analiz.">

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

fbq('init', '4117707111847832'); // Senin Pixel ID
fbq('track', 'PageView'); // Sayfa görüntüleme
</script>
<noscript>
  <img height="1" width="1" style="display:none"
       src="https://www.facebook.com/tr?id=4117707111847832&ev=PageView&noscript=1"/>
</noscript>

<style>
body{
  font-family:Arial,system-ui;
  margin:0;padding:0;
  background:linear-gradient(135deg,#0a0a33,#1a1a4d,#000);
  color:#fff;
  min-height:100vh;
  display:flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
  text-align:center;
  padding:20px
}
.logo{width:130px;height:130px;border-radius:50%;margin-bottom:20px;border:3px solid #FFD700;object-fit:cover}
h1{font-size:2.6rem;margin-bottom:18px;font-weight:bold;color:#FFD700}
p{max-width:640px;font-size:1.1rem;line-height:1.6;margin-bottom:30px;opacity:.95}
.btn-telegram{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding:16px 34px;
  font-size:1.2rem;
  font-weight:bold;
  color:#fff;
  background:linear-gradient(145deg,#0088cc,#005f99);
  border-radius:50px;
  text-decoration:none;
  box-shadow:0 6px 14px rgba(0,0,0,.4);
  transition:all .3s;
  animation:pulse 1.8s infinite
}
.btn-telegram:hover{
  transform:scale(1.08);
  background:linear-gradient(145deg,#00aaff,#0088cc);
  box-shadow:0 10px 24px rgba(0,0,0,.5)
}
.btn-telegram img{width:24px;height:24px}
@keyframes pulse{
  0%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,136,204,.6)}
  70%{transform:scale(1.06);box-shadow:0 0 0 18px rgba(0,136,204,0)}
  100%{transform:scale(1);box-shadow:0 0 0 0 rgba(0,136,204,0)}
}
</style>
</head>
<body>

<img src="https://i.imgur.com/FJNxUvk.jpeg" alt="Borsa Dünyası" class="logo" loading="lazy" width="130" height="130" title="Borsa Dünyası">
<h1>BORSA DÜNYASI</h1>
<p>
  #KATILIMENDEKSİ #BİST100 #BORSA #ANALİZ <br><br>
  🔥 Ücretsiz VIP Grubu <br>
  📊 Risk Yönetimi <br>
  💼 Ücretsiz Portföy Kontrolü <br>
  ⭐️ Temel Teknik Analiz
</p>

<a href="https://t.me/+NJKLetIKDa0wZmVh" 
   target="_blank" rel="noopener noreferrer" 
   class="btn-telegram" id="cta-telegram" 
   aria-label="Telegram grubuna katıl" 
   title="Telegram Grubuna Katıl">
  <img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" 
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
