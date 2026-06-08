<?php
$maintenance_data = json_decode(file_get_contents('config/maintenance.json'), true);
if($maintenance_data['status'] !== 'on') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Mode</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
  @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
  @keyframes wrench { 0%,100%{transform:rotate(-20deg)} 50%{transform:rotate(20deg)} }
  @keyframes spark { 0%,100%{opacity:0;transform:scale(0)} 50%{opacity:1;transform:scale(1)} }
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
  @keyframes progress { 0%{width:0%} 100%{width:73%} }
  @keyframes bounce { 0%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }
  @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }
  @keyframes pulse-ring { 0%{transform:scale(0.8);opacity:1} 100%{transform:scale(1.6);opacity:0} }
  @keyframes star-twinkle { 0%,100%{opacity:0.2} 50%{opacity:1} }
  @keyframes cat-tail { 0%,100%{transform:rotate(-15deg) translateX(0)} 50%{transform:rotate(15deg) translateX(2px)} }
  @keyframes eye-blink { 0%,90%,100%{transform:scaleY(1)} 95%{transform:scaleY(0.1)} }
  @keyframes slide-in { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {background: #0F172A; }

  .wrap {
    min-height: 520px;
    background: #0F172A;
    border-radius: 16px;
    padding: 40px 28px 32px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    position: relative;
    overflow: hidden;
    font-family: var(--font-sans, sans-serif);
  }

  .stars { position: absolute; inset: 0; pointer-events: none; }
  .star {
    position: absolute;
    width: 3px; height: 3px;
    background: #FBBF24;
    border-radius: 50%;
  }

  .scene {
    position: relative;
    width: 220px;
    height: 200px;
    margin-bottom: 8px;
    animation: float 3.5s ease-in-out infinite;
  }

  .cat-body {
    position: absolute;
    bottom: 0; left: 50%;
    transform: translateX(-50%);
    width: 90px; height: 80px;
    background: #F8FAFC;
    border-radius: 50% 50% 40% 40%;
  }
  .cat-head {
    position: absolute;
    bottom: 68px; left: 50%;
    transform: translateX(-50%);
    width: 72px; height: 64px;
    background: #F8FAFC;
    border-radius: 50%;
    z-index: 2;
  }
  .cat-ear-l, .cat-ear-r {
    position: absolute;
    bottom: 118px;
    width: 0; height: 0;
    border-left: 12px solid transparent;
    border-right: 12px solid transparent;
    border-bottom: 22px solid #F8FAFC;
    z-index: 1;
  }
  .cat-ear-l { left: 66px; transform: rotate(-15deg); }
  .cat-ear-r { right: 66px; transform: rotate(15deg); }
  .cat-inner-ear-l, .cat-inner-ear-r {
    position: absolute;
    bottom: 122px;
    width: 0; height: 0;
    border-left: 7px solid transparent;
    border-right: 7px solid transparent;
    border-bottom: 14px solid #FDA4AF;
    z-index: 2;
  }
  .cat-inner-ear-l { left: 71px; transform: rotate(-15deg); }
  .cat-inner-ear-r { right: 71px; transform: rotate(15deg); }

  .cat-eye-l, .cat-eye-r {
    position: absolute;
    bottom: 95px;
    width: 14px; height: 14px;
    background: #1E293B;
    border-radius: 50%;
    z-index: 3;
    animation: eye-blink 4s ease-in-out infinite;
  }
  .cat-eye-l { left: 82px; }
  .cat-eye-r { right: 82px; }
  .cat-eye-l::after, .cat-eye-r::after {
    content: '';
    position: absolute;
    top: 2px; left: 3px;
    width: 5px; height: 5px;
    background: white;
    border-radius: 50%;
  }

  .cat-nose {
    position: absolute;
    bottom: 88px; left: 50%;
    transform: translateX(-50%);
    width: 0; height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid #FDA4AF;
    z-index: 3;
  }
  .cat-mouth {
    position: absolute;
    bottom: 79px; left: 50%;
    transform: translateX(-50%);
    font-size: 12px;
    color: #94A3B8;
    z-index: 3;
    letter-spacing: 2px;
  }

  .cat-tail {
    position: absolute;
    bottom: 8px; right: 55px;
    width: 48px; height: 16px;
    border-radius: 0 0 16px 16px;
    border: 5px solid #F8FAFC;
    border-top: none;
    transform-origin: left center;
    animation: cat-tail 1.8s ease-in-out infinite;
  }

  .helmet {
    position: absolute;
    bottom: 130px; left: 50%;
    transform: translateX(-50%);
    width: 80px; height: 46px;
    background: #FBBF24;
    border-radius: 40px 40px 0 0;
    z-index: 4;
  }
  .helmet::after {
    content: '';
    position: absolute;
    bottom: 0; left: -8px;
    width: 96px; height: 12px;
    background: #F59E0B;
    border-radius: 4px;
  }
  .helmet-stripe {
    position: absolute;
    top: 12px; left: 50%;
    transform: translateX(-50%);
    width: 56px; height: 8px;
    background: #F59E0B;
    border-radius: 4px;
  }

  .wrench-wrap {
    position: absolute;
    bottom: 72px; right: 22px;
    z-index: 5;
    animation: wrench 1.4s ease-in-out infinite;
    transform-origin: bottom left;
  }
  .wrench {
    width: 48px; height: 14px;
    background: #94A3B8;
    border-radius: 4px;
    position: relative;
  }
  .wrench::before {
    content: '';
    position: absolute;
    right: -8px; top: -5px;
    width: 16px; height: 24px;
    border: 5px solid #94A3B8;
    border-radius: 50%;
  }
  .wrench::after {
    content: '';
    position: absolute;
    left: -4px; top: -5px;
    width: 12px; height: 24px;
    border: 5px solid #94A3B8;
    border-radius: 50%;
  }

  .spark1, .spark2, .spark3 {
    position: absolute;
    width: 8px; height: 8px;
    background: #FBBF24;
    border-radius: 50%;
    z-index: 6;
  }
  .spark1 { top: 30px; right: 10px; animation: spark 1.4s 0s ease-in-out infinite; }
  .spark2 { top: 14px; right: 28px; animation: spark 1.4s 0.3s ease-in-out infinite; }
  .spark3 { top: 40px; right: 32px; animation: spark 1.4s 0.6s ease-in-out infinite; }

  .title-wrap { text-align: center; margin-top: 8px; animation: slide-in 0.6s ease both; }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #FBBF24;
    color: #1E293B;
    font-size: 11px;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 20px;
    margin-bottom: 14px;
    letter-spacing: 0.5px;
  }
  .badge-dot {
    width: 7px; height: 7px;
    background: #1E293B;
    border-radius: 50%;
    animation: blink 1s ease-in-out infinite;
  }

  h1 {
    color: #F8FAFC;
    font-size: 26px;
    font-weight: 500;
    margin-bottom: 10px;
  }
  h1 span { color: #FBBF24; }

  .subtitle {
    color: #94A3B8;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 24px;
    max-width: 320px;
    text-align: center;
  }

  .progress-wrap {
    width: 100%;
    max-width: 340px;
    margin-bottom: 20px;
    animation: slide-in 0.8s 0.2s ease both;
    opacity: 0;
    animation-fill-mode: forwards;
  }
  .progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
  }
  .progress-label span {
    font-size: 12px;
    color: #64748B;
  }
  .progress-label strong {
    font-size: 12px;
    color: #FBBF24;
    font-weight: 500;
  }
  .progress-bar {
    height: 8px;
    background: #1E293B;
    border-radius: 8px;
    overflow: hidden;
    border: 0.5px solid #334155;
  }
  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #FBBF24, #F59E0B);
    border-radius: 8px;
    width: 0;
    animation: progress 2.5s 0.8s cubic-bezier(.4,0,.2,1) forwards;
  }

  .checklist {
    width: 100%;
    max-width: 340px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    animation: slide-in 1s 0.4s ease both;
    opacity: 0;
    animation-fill-mode: forwards;
  }
  .check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #1E293B;
    border-radius: 10px;
    padding: 10px 14px;
    border: 0.5px solid #334155;
  }
  .check-icon {
    width: 22px; height: 22px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
  }
  .check-icon.done { background: rgba(251,191,36,0.15); color: #FBBF24; }
  .check-icon.wait { background: #0F172A; border: 1.5px solid #334155; }
  .check-icon.spin-icon {
    background: rgba(251,191,36,0.08);
    color: #FBBF24;
    animation: spin 1.6s linear infinite;
  }
  .check-text {
    font-size: 13px;
    flex: 1;
  }
  .check-text.done { color: #94A3B8; text-decoration: line-through; text-decoration-color: #475569; }
  .check-text.active { color: #F8FAFC; }
  .check-text.pending { color: #475569; }
  .check-tag {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
  }
  .tag-done { background: rgba(251,191,36,0.15); color: #FBBF24; }
  .tag-active { background: rgba(251,191,36,0.1); color: #FBBF24; animation: blink 1.5s ease-in-out infinite; }
  .tag-pending { background: #1E293B; color: #475569; border: 0.5px solid #334155; }

  .admin-link {
    margin-top: 20px;
    font-size: 12px;
    color: #475569;
    text-decoration: none;
    animation: slide-in 1.2s 0.6s ease both;
    opacity: 0;
    animation-fill-mode: forwards;
    transition: color 0.2s;
  }
  .admin-link:hover { color: #A78BFA; }
</style>
</head>
<body>
  



<div class="wrap">

  <div class="stars" id="stars"></div>

  <div class="scene">
    <div class="cat-ear-l"></div>
    <div class="cat-ear-r"></div>
    <div class="cat-inner-ear-l"></div>
    <div class="cat-inner-ear-r"></div>
    <div class="cat-head">
      <div class="cat-eye-l"></div>
      <div class="cat-eye-r"></div>
      <div class="cat-nose"></div>
      <div class="cat-mouth">ω</div>
    </div>
    <div class="helmet">
      <div class="helmet-stripe"></div>
    </div>
    <div class="cat-body"></div>
    <div class="cat-tail"></div>
    <div class="wrench-wrap">
      <div class="wrench"></div>
    </div>
    <div class="spark1"></div>
    <div class="spark2"></div>
    <div class="spark3"></div>
  </div>

  <div class="title-wrap">
    <div class="badge">
      <div class="badge-dot"></div>
      Maintenance Mode
    </div>
    <h1>Sedang <span>Diperbaiki</span> ✨</h1>
    <p class="subtitle">Website lagi istirahat sebentar :)</p>
  </div>

  <div class="progress-wrap">
    <div class="progress-label">
      <span>Progress perbaikan</span>
      <strong>73%</strong>
    </div>
    <div class="progress-bar">
      <div class="progress-fill"></div>
    </div>
  </div>

  <div class="checklist">
   
    
  </div>

</div>

<script>
  const stars = document.getElementById('stars');
  const positions = [
    [8,12],[20,35],[88,8],[92,22],[5,55],[15,70],[82,60],[95,75],[45,5],[60,18],[30,90],[75,85],[50,42],[3,80],[97,45]
  ];
  positions.forEach(([l,t], i) => {
    const s = document.createElement('div');
    s.className = 'star';
    s.style.cssText = `left:${l}%;top:${t}%;animation:star-twinkle ${1.5+Math.random()*2}s ${Math.random()*2}s ease-in-out infinite;opacity:${0.2+Math.random()*0.5}`;
    stars.appendChild(s);
  });
</script>


  <?php if($_SERVER['REMOTE_ADDR'] == '127.0.0.1'): ?>
    <!-- link admin sudah ada di dalam desain, tinggal uncomment atau biarkan -->
  <?php endif; ?>
</body>
</html>