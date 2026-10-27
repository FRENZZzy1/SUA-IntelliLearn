<?php
/**
 * Shared SUA IntelliLearn loading overlay.
 * Include once per page. It hides after window load, with a short minimum
 * display time to prevent flicker on fast navigations.
 */
?>
<style>
#sua-loading-screen{--sls-bg1:#f4faf4;--sls-bg2:#e3f1e4;--sls-green:#2e7d32;--sls-dark:#1b5e20;--sls-light:#4caf50;--sls-yellow:#fdd835;--sls-text:#1a1a2e;--sls-muted:#6c757d;position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;padding:24px;background:radial-gradient(circle at 50% 40%,var(--sls-bg1),var(--sls-bg2));color:var(--sls-text);font-family:Poppins,system-ui,-apple-system,"Segoe UI",sans-serif;opacity:1;visibility:visible;transition:opacity .45s ease,visibility .45s ease}
@media(prefers-color-scheme:dark){#sua-loading-screen{--sls-bg1:#14201a;--sls-bg2:#0c1410;--sls-text:#eef6ee;--sls-muted:#9db3a0}}
#sua-loading-screen.sls-done{opacity:0;visibility:hidden;pointer-events:none}
#sua-loading-screen .sls-stage{position:relative;width:200px;height:200px;display:grid;place-items:center}
#sua-loading-screen .sls-ring{position:absolute;inset:30px;border-radius:50%;border:2px solid var(--sls-light);opacity:0;animation:sls-ring 2.4s cubic-bezier(.2,.6,.3,1) infinite}
#sua-loading-screen .sls-ring:nth-child(2){animation-delay:.8s}#sua-loading-screen .sls-ring:nth-child(3){animation-delay:1.6s}
@keyframes sls-ring{0%{transform:scale(.6);opacity:.55}100%{transform:scale(1.7);opacity:0}}
#sua-loading-screen .sls-orbit{position:absolute;inset:0;animation:sls-spin 6s linear infinite}#sua-loading-screen .sls-orbit.sls-rev{animation-duration:9s;animation-direction:reverse}
#sua-loading-screen .sls-mini{position:absolute;width:16px;height:16px;top:6px;left:50%;margin-left:-8px;border-radius:50% 50% 50% 18%;background:var(--sls-yellow);box-shadow:0 4px 10px #fdd83573}
#sua-loading-screen .sls-mini.sls-m2{width:11px;height:11px;top:auto;bottom:14px;left:22px;margin:0;background:var(--sls-light)}
#sua-loading-screen .sls-mini.sls-m3{width:13px;height:13px;top:50%;left:auto;right:4px;margin-top:-6px}
@keyframes sls-spin{to{transform:rotate(360deg)}}
#sua-loading-screen .sls-bubble{position:relative;width:112px;height:96px;filter:drop-shadow(0 14px 22px #1b5e2052);animation:sls-pop 1.1s cubic-bezier(.3,1.5,.5,1) both,sls-float 3s ease-in-out 1.1s infinite}
#sua-loading-screen .sls-bubble svg{width:100%;height:100%;overflow:visible}
@keyframes sls-pop{0%{transform:scale(0) rotate(-14deg)}100%{transform:scale(1) rotate(0)}}@keyframes sls-float{0%,100%{transform:translateY(0) rotate(-1.5deg)}50%{transform:translateY(-9px) rotate(1.5deg)}}
#sua-loading-screen .sls-dots{position:absolute;left:0;right:0;top:30px;height:22px;display:flex;justify-content:center;align-items:center;gap:9px}
#sua-loading-screen .sls-dots i{width:12px;height:12px;border-radius:50%;background:#fff;animation:sls-dot 1.2s ease-in-out infinite}
#sua-loading-screen .sls-dots i:nth-child(2){animation-delay:.15s}#sua-loading-screen .sls-dots i:nth-child(3){animation-delay:.3s}
@keyframes sls-dot{0%,60%,100%{transform:translateY(0) scale(1);opacity:.7}30%{transform:translateY(-10px) scale(1.2);opacity:1;background:var(--sls-yellow)}}
#sua-loading-screen .sls-spark{position:absolute;top:18px;right:20px;width:20px;height:20px;color:var(--sls-yellow);animation:sls-twinkle 1.8s ease-in-out infinite}
@keyframes sls-twinkle{0%,100%{transform:scale(.3) rotate(0);opacity:0}50%{transform:scale(1) rotate(90deg);opacity:1}}
#sua-loading-screen .sls-brand{font-weight:600;font-size:1.35rem;letter-spacing:.02em;text-align:center}
#sua-loading-screen .sls-brand span{background:linear-gradient(100deg,var(--sls-dark) 20%,var(--sls-yellow) 45%,var(--sls-light) 55%,var(--sls-dark) 80%);background-size:250% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:sls-shimmer 2.6s linear infinite}
@keyframes sls-shimmer{to{background-position:-250% 0}}
#sua-loading-screen .sls-sub{font-size:.85rem;color:var(--sls-muted);margin-top:4px;font-weight:500;text-align:center}
#sua-loading-screen .sls-bar{width:min(240px,64vw);height:6px;border-radius:99px;background:#2e7d3226;overflow:hidden}
#sua-loading-screen .sls-bar:after{content:"";display:block;height:100%;width:40%;border-radius:99px;background:linear-gradient(90deg,var(--sls-green),var(--sls-yellow));animation:sls-slide 1.5s ease-in-out infinite}
@keyframes sls-slide{0%{transform:translateX(-110%)}100%{transform:translateX(280%)}}
@media(prefers-reduced-motion:reduce){#sua-loading-screen *{animation-duration:6s!important}}
</style>
<div id="sua-loading-screen" role="status" aria-live="polite" aria-label="Loading SUA IntelliLearn">
 <div class="sls-stage" aria-hidden="true">
  <div class="sls-ring"></div><div class="sls-ring"></div><div class="sls-ring"></div>
  <div class="sls-orbit"><div class="sls-mini"></div><div class="sls-mini sls-m2"></div></div><div class="sls-orbit sls-rev"><div class="sls-mini sls-m3"></div></div>
  <div class="sls-bubble"><svg viewBox="0 0 112 96"><defs><linearGradient id="sls-gradient" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4caf50"/><stop offset="1" stop-color="#1b5e20"/></linearGradient></defs><path fill="url(#sls-gradient)" d="M20 0h72c11 0 20 9 20 20v38c0 11-9 20-20 20H52L28 96V78h-8C9 78 0 69 0 58V20C0 9 9 0 20 0z"/><path fill="#fff" opacity=".12" d="M20 0h72c11 0 20 9 20 20v6C80 34 36 30 0 26v-6C0 9 9 0 20 0z"/></svg><div class="sls-dots"><i></i><i></i><i></i></div><svg class="sls-spark" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0l2.7 9.3L24 12l-9.3 2.7L12 24l-2.7-9.3L0 12l9.3-2.7z"/></svg></div>
 </div>
 <div><div class="sls-brand"><span>SUA IntelliLearn</span></div><div class="sls-sub">Getting things ready<span id="sls-ell"></span></div></div>
 <div class="sls-bar" aria-hidden="true"></div>
</div>
<script>
(function(){
 var loader=document.getElementById('sua-loading-screen');
 if(!loader||loader.dataset.initialized)return;
 loader.dataset.initialized='1';
 var ell=document.getElementById('sls-ell'),n=0;
 var dots=setInterval(function(){if(!ell){clearInterval(dots);return;}n=(n+1)%4;ell.textContent='...'.slice(0,n);},400);
 var started=Date.now(),minimum=700;
 function hide(){setTimeout(function(){loader.classList.add('sls-done');clearInterval(dots);},Math.max(0,minimum-(Date.now()-started)));}
 if(document.readyState==='complete')hide();else window.addEventListener('load',hide,{once:true});
 // A safety fallback prevents the overlay remaining forever if a resource stalls.
 setTimeout(function(){loader.classList.add('sls-done');clearInterval(dots);},15000);
})();
</script>
