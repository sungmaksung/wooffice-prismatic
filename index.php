<?php include 'config/database.php'; 

// Load settings from JSON
$settings = [];
if(file_exists('config/settings.json')) {
    $settings = json_decode(file_get_contents('config/settings.json'), true);
}

// Default values if settings not found
$company_name = $settings['company_name'] ?? 'Prismatic Organizer';
$company_tagline = $settings['company_tagline'] ?? 'Truly Fantastic';
$company_email = $settings['company_email'] ?? 'hello@prismatic-organizer.com';
$company_phone = $settings['company_phone'] ?? '+62 822-1907-4421';
$company_address = $settings['company_address'] ?? 'Cimasuk Residence, Blok G-5, Daerah Suci, Karangpawitan, Garut';
$company_instagram = $settings['company_instagram'] ?? 'prismatic_eo_wo';
$company_whatsapp = $settings['company_whatsapp'] ?? '6282219074421';
$apk_version = $settings['apk_version'] ?? '1.0.0';
$apk_file = 'uploads/apk/wofice.apk';
$apk_exists = file_exists($apk_file);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= $company_name ?> — Wedding & Event Organizer</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&family=Caveat:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-dark:     #080808;
            --bg-card:     #141414;
            --bg-elevated: #1E1E1E;
            --gold:        #FFD700;
            --gold-dark:   #DAA520;
            --gold-light:  #FFED4A;
            --gold-glow:   rgba(255,215,0,0.25);
            --text-primary: #FFFFFF;
            --text-secondary: #A0A0A0;
            --text-muted:   #585858;
            --border:       #222222;
            --border-gold:  rgba(255,215,0,0.2);
            --ff-serif:     'Cormorant Garamond', Georgia, serif;
            --ff-sans:      'DM Sans', sans-serif;
            --ff-script:    'Caveat', cursive;
            --ease-expo:    cubic-bezier(0.16, 1, 0.3, 1);
            --radius-card:  28px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--ff-sans);
            background-color: var(--bg-dark);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-card); }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg,var(--gold),var(--gold-dark)); border-radius: 3px; }

        /* ═══════════════════════════════ NAVBAR ═══════════════════════════════ */
        nav {
            position: fixed;top:0;left:0;right:0;z-index:500;
            padding:18px 52px;
            display:flex;align-items:center;justify-content:space-between;
            transition:background .4s,padding .4s,box-shadow .4s;
            background: rgba(8,8,8,0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid transparent;
        }
        nav.scrolled {
            background: rgba(8,8,8,0.92);
            padding:12px 52px;
            border-bottom-color: var(--border-gold);
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
        }
        .nav-brand { display:flex;align-items:center;gap:14px;text-decoration:none; }
        .nav-logo-mark {
            width:42px;height:42px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            border-radius:50%;display:grid;place-items:center;overflow:hidden;
            box-shadow: 0 0 20px var(--gold-glow);
        }
        .nav-logo-mark img { width:100%;height:100%;object-fit:cover; }
        .nav-logo-mark img[src=""], .nav-logo-mark img:not([src]) { display: none; }
        .nav-brand-text h1 {
            font-family:var(--ff-serif);font-size:1.35rem;font-weight:600;
            color:var(--gold);letter-spacing:.03em;line-height:1;
        }
        .nav-brand-text p {
            font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;
            color:var(--text-secondary);margin-top:2px;
        }
        .nav-links { display:flex;align-items:center;gap:10px; }

        /* ── Download Buttons in Nav ── */
        .nav-download-group {
            display: flex;
            gap: 6px;
            margin-right: 8px;
        }
        .nav-dl-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: var(--ff-sans);
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 7px 13px;
            border-radius: 100px;
            transition: all .3s var(--ease-expo);
            cursor: pointer;
            border: 1px solid var(--border-gold);
            color: var(--gold);
            background: rgba(255,215,0,0.07);
            white-space: nowrap;
        }
        .nav-dl-btn i { font-size: 0.85rem; }
        .nav-dl-btn.android:hover {
            background: rgba(61,220,132,0.15);
            border-color: #3DDC84;
            color: #3DDC84;
            transform: translateY(-1px);
        }
        .nav-dl-btn.ios-btn { opacity: 0.55; cursor: not-allowed; }
        .nav-dl-btn.ios-btn:hover { opacity: 0.7; transform: none; }
        .badge-soon {
            background: rgba(255,215,0,0.15);
            padding: 1px 6px;
            border-radius: 20px;
            font-size: 0.55rem;
            color: var(--gold);
        }

        /* ── Generic Buttons ── */
        .btn {
            display:inline-flex;align-items:center;gap:6px;
            font-family:var(--ff-sans);font-size:.78rem;font-weight:500;
            letter-spacing:.06em;text-transform:uppercase;text-decoration:none;
            padding:10px 22px;border-radius:100px;border:none;
            transition:all .3s var(--ease-expo); cursor: pointer;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--bg-dark);
            box-shadow:0 4px 20px rgba(255,215,0,.3);
        }
        .btn-gold:hover { transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,215,0,.5); }
        .btn-dark {
            background: var(--bg-elevated);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        .btn-dark:hover { transform:translateY(-2px);background: #2a2a2a; }
        .btn-outline {
            background: transparent;
            color: var(--gold);
            border: 1.5px solid rgba(255,215,0,.3);
        }
        .btn-outline:hover { border-color: var(--gold); background: rgba(255,215,0,.08); }

        /* ═══════════════════════════════ HERO ═══════════════════════════════ */
        #hero {
            min-height:100vh;display:grid;
            grid-template-columns:1fr 1fr;
            align-items:center;padding:120px 52px 80px;
            gap:60px;position:relative;overflow:hidden;
        }
        .blob {
            position:absolute;border-radius:50%;filter:blur(110px);
            opacity:.15;pointer-events:none;
        }
        .blob-1 {
            width:600px;height:600px;
            background: radial-gradient(circle, #FFD700 0%, transparent 70%);
            top:-140px;right:-100px;animation: blobFloat 8s ease-in-out infinite;
        }
        .blob-2 {
            width:420px;height:420px;
            background: radial-gradient(circle, #DAA520 0%, transparent 70%);
            bottom:40px;left:-100px;animation: blobFloat 10s ease-in-out infinite reverse;
        }
        .blob-3 {
            width:300px;height:300px;
            background: radial-gradient(circle, rgba(255,215,0,0.4) 0%, transparent 70%);
            top:50%;left:50%;transform:translate(-50%,-50%);
            animation: blobFloat 12s ease-in-out infinite;
        }
        @keyframes blobFloat { 0%,100%{transform:translate(0,0);} 50%{transform:translate(20px,-30px);} }
        .hero-left { position:relative;z-index:10; }
        .hero-badge {
            display:inline-flex;align-items:center;gap:8px;
            background:rgba(255,215,0,.08);
            backdrop-filter:blur(8px);
            border:1px solid rgba(255,215,0,.25);
            border-radius:100px;
            padding:8px 16px;font-size:.78rem;color:var(--gold);
            margin-bottom:28px;opacity:0;
        }
        .hero-eyebrow {
            font-family:var(--ff-script);font-size:1.8rem;
            color:var(--gold);margin-bottom:8px;
            opacity:0;transform:translateY(20px);
        }
        .hero-h1 {
            font-family:var(--ff-serif);
            font-size:clamp(3.2rem,5.5vw,5.5rem);font-weight:600;
            line-height:1.08;color:var(--text-primary);margin-bottom:24px;
            opacity:0;transform:translateY(30px);
        }
        .hero-h1 em {
            font-style:italic;
            background:linear-gradient(135deg,var(--gold),var(--gold-dark));
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
        }
        .hero-desc {
            font-size:1.05rem;color:var(--text-secondary);line-height:1.75;
            max-width:460px;margin-bottom:36px;
            opacity:0;transform:translateY(20px);
        }
        .hero-cta { display:flex;gap:14px;flex-wrap:wrap;opacity:0; }
        .hero-joke {
            margin-top:36px;padding:16px 20px;
            background:rgba(255,255,255,.04);
            border-left:3px solid var(--gold);
            border-radius:0 12px 12px 0;
            font-size:.88rem;color:var(--text-secondary);line-height:1.6;
            max-width:460px;opacity:0;
            backdrop-filter: blur(4px);
        }
        .hero-right { position:relative;z-index:10;height:540px; }
        .photo-stack {
            position:absolute;border-radius:20px;overflow:hidden;
            box-shadow:0 24px 60px rgba(0,0,0,.6);
        }
        .photo-stack img { width:100%;height:100%;object-fit:cover;display:block; }
        .ps-1 { width:280px;height:360px;top:20px;left:40px; opacity:0;transform:rotate(-4deg) translateX(-40px); }
        .ps-2 { width:240px;height:300px;top:60px;right:20px; opacity:0;transform:rotate(3deg) translateX(40px); }
        .ps-3 { width:200px;height:240px;bottom:10px;left:130px; opacity:0;transform:rotate(1deg) translateY(40px); }
        .photo-tag {
            position:absolute;background:rgba(20,20,20,0.85);padding:8px 14px;
            border-radius:100px;font-size:.72rem;font-weight:500;
            letter-spacing:.06em;text-transform:uppercase;color:var(--gold);
            box-shadow:0 4px 16px rgba(0,0,0,.4);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-gold);
        }
        .pt-1 { top:14px;right:70px; }
        .pt-2 { bottom:20px;left:20px; }
        .floating-hearts { position:absolute;font-size:1.5rem;animation:floatHeart 4s ease-in-out infinite; opacity:0.5; }
        .fh-1 { top:0;right:0;animation-delay:0s; }
        .fh-2 { bottom:80px;right:10px;animation-delay:1.5s;font-size:1rem; }
        .fh-3 { top:200px;left:10px;animation-delay:.8s;font-size:1.2rem; }
        @keyframes floatHeart { 0%,100% { transform:translateY(0) rotate(0deg); } 50% { transform:translateY(-14px) rotate(10deg); } }

        /* ═══════════════════════════════ DOWNLOAD SECTION ═══════════════════════════════ */
        .download-section {
            padding: 60px 52px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0D0D0D 0%, #181818 50%, #0D0D0D 100%);
            border-top: 1px solid rgba(255,215,0,0.12);
            border-bottom: 1px solid rgba(255,215,0,0.12);
        }
        /* Decorative background pattern */
        .download-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 10% 50%, rgba(255,215,0,0.06) 0%, transparent 50%),
                radial-gradient(circle at 90% 50%, rgba(61,220,132,0.04) 0%, transparent 50%);
            pointer-events: none;
        }
        .download-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 40px;
            position: relative;
            z-index: 2;
        }
        .download-info-wrap {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .download-icon-wrap {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, rgba(255,215,0,0.15), rgba(255,215,0,0.05));
            border: 1px solid var(--border-gold);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 30px rgba(255,215,0,0.1);
        }
        .download-icon-wrap i {
            font-size: 2rem;
            color: var(--gold);
        }
        .download-text-info h3 {
            font-family: var(--ff-serif);
            font-size: 1.7rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            line-height: 1.2;
        }
        .download-text-info h3 em {
            font-style: italic;
            color: var(--gold);
        }
        .download-text-info p {
            color: var(--text-secondary);
            font-size: 0.88rem;
            line-height: 1.6;
            max-width: 420px;
        }
        .download-buttons-wrap {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* ── Store Buttons ── */
        .store-btn {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 18px;
            transition: all 0.35s var(--ease-expo);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            min-width: 190px;
        }
        .store-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .store-btn:hover::before { opacity: 1; }
        .store-btn:hover { transform: translateY(-4px); }

        /* Android Button */
        .store-btn.android {
            background: linear-gradient(135deg, rgba(61,220,132,0.12) 0%, rgba(61,220,132,0.05) 100%);
            border: 1.5px solid rgba(61,220,132,0.35);
            box-shadow: 0 4px 20px rgba(61,220,132,0.08);
        }
        .store-btn.android::before {
            background: linear-gradient(135deg, rgba(61,220,132,0.2) 0%, rgba(61,220,132,0.08) 100%);
        }
        .store-btn.android:hover {
            border-color: #3DDC84;
            box-shadow: 0 12px 40px rgba(61,220,132,0.25);
        }
        .store-btn.android .store-icon { color: #3DDC84; font-size: 2.2rem; }

        /* iOS Button */
        .store-btn.ios-store {
            background: linear-gradient(135deg, rgba(255,255,255,0.07) 0%, rgba(255,255,255,0.03) 100%);
            border: 1.5px solid rgba(255,255,255,0.15);
            opacity: 0.55;
            cursor: not-allowed;
        }
        .store-btn.ios-store:hover {
            transform: none;
            opacity: 0.65;
            box-shadow: none;
        }
        .store-btn.ios-store .store-icon { color: #E0E0E0; font-size: 2.2rem; }

        .store-btn-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .store-btn-text .store-label {
            font-size: 0.65rem;
            font-weight: 400;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-secondary);
        }
        .store-btn-text .store-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .store-btn-text .store-ver {
            font-size: 0.6rem;
            font-weight: 500;
            margin-top: 2px;
        }
        .android .store-ver { color: #3DDC84; }
        .ios-store .store-ver { color: var(--text-muted); font-style: italic; }

        /* Coming soon ribbon */
        .coming-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            color: var(--bg-dark);
            font-size: 0.52rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 0 16px 0 8px;
        }

        /* ═══════════════════════════════ PACKAGES ═══════════════════════════════ */
        #packages { padding:100px 52px;background: var(--bg-dark); }
        .pkg-header { text-align:center;margin-bottom:64px; }
        .section-label {
            display:inline-flex;align-items:center;gap:10px;
            font-size:.68rem;font-weight:600;letter-spacing:.22em;
            text-transform:uppercase;color:var(--gold);margin-bottom:14px;
        }
        .section-label::before { content:'';display:block;width:32px;height:1px;background:linear-gradient(90deg,transparent,var(--gold)); }
        .section-label::after { content:'';display:block;width:32px;height:1px;background:linear-gradient(90deg,var(--gold),transparent); }
        .section-title {
            font-family:var(--ff-serif);font-size:clamp(2.2rem,4vw,3.4rem);
            font-weight:600;line-height:1.15;color:var(--text-primary);
        }
        .section-title em { font-style:italic;color:var(--gold); }
        .pkg-sub { margin-top:12px;font-size:.95rem;color:var(--text-secondary); }
        .pkg-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(310px,1fr));
            gap:28px;
            max-width:1400px;
            margin:0 auto;
        }

        /* ── Package Card: Modern Glassmorphism + bg blur ── */
        .package-card {
            border-radius: var(--radius-card);
            overflow: hidden;
            cursor: pointer;
            transition: transform .45s var(--ease-expo), box-shadow .45s var(--ease-expo);
            opacity: 0;
            transform: translateY(40px);
            position: relative;
            isolation: isolate;
        }
        .package-card:hover {
            transform: translateY(-14px) scale(1.01);
            box-shadow: 0 40px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,215,0,0.3);
        }

        /* Full-card background image with heavy blur + saturation */
        .card-bg-full {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: blur(0px) saturate(1.3) brightness(0.35);
            transform: scale(1.08);
            transition: filter 0.5s ease, transform 0.5s ease;
            z-index: 0;
        }
        .package-card:hover .card-bg-full {
            filter: blur(2px) saturate(1.6) brightness(0.25);
            transform: scale(1.12);
        }

        /* Dark gradient overlay */
        .card-vignette {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(to bottom,
                    rgba(0,0,0,0.1) 0%,
                    rgba(0,0,0,0.3) 40%,
                    rgba(0,0,0,0.75) 100%);
            z-index: 1;
            transition: background 0.4s ease;
        }
        .package-card:hover .card-vignette {
            background:
                linear-gradient(to bottom,
                    rgba(0,0,0,0.2) 0%,
                    rgba(0,0,0,0.45) 40%,
                    rgba(0,0,0,0.88) 100%);
        }

        /* Gold shimmer border on hover */
        .card-border-glow {
            position: absolute;
            inset: 0;
            border-radius: var(--radius-card);
            border: 1.5px solid rgba(255,215,0,0);
            transition: border-color 0.4s ease, box-shadow 0.4s ease;
            z-index: 10;
            pointer-events: none;
        }
        .package-card:hover .card-border-glow {
            border-color: rgba(255,215,0,0.45);
            box-shadow: inset 0 0 40px rgba(255,215,0,0.04);
        }

        /* Card content sits above bg */
        .card-content {
            position: relative;
            z-index: 5;
        }

        /* Banner area: full-bleed photo with emoji */
        .pkg-card-banner {
            height: 175px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .banner-image {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            z-index: 0;
            transition: transform 0.6s ease;
            filter: saturate(1.2) brightness(0.75);
        }
        .package-card:hover .banner-image {
            transform: scale(1.08);
            filter: saturate(1.5) brightness(0.6);
        }
        .banner-gradient {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 70%;
            background: linear-gradient(to top, rgba(10,10,10,0.95), transparent);
            z-index: 1;
        }
        .pkg-emoji {
            font-size: 4rem;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.6));
            z-index: 2;
            position: relative;
            animation: emojiFloat 3s ease-in-out infinite;
        }
        @keyframes emojiFloat { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-5px);} }
        .pkg-featured-badge {
            position: absolute;
            top: 12px; right: 12px;
            z-index: 3;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--bg-dark);
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 5px 13px;
            border-radius: 100px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.4);
        }

        /* Card body */
        .pkg-card-body {
            padding: 22px 22px 24px;
            background: linear-gradient(to bottom, rgba(12,12,12,0.0) 0%, rgba(12,12,12,0.5) 100%);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .pkg-name {
            font-family: var(--ff-serif);
            font-size: 1.38rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        }
        .pkg-price {
            font-family: var(--ff-serif);
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(255,215,0,0.3);
        }
        .pkg-price span { font-size: 0.75rem; font-weight: 400; opacity: 0.8; }
        .pkg-desc {
            font-size: 0.77rem;
            color: rgba(200,200,200,0.85);
            line-height: 1.55;
            margin-bottom: 14px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .pkg-features {
            list-style: none;
            margin-bottom: 18px;
        }
        .pkg-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.72rem;
            color: rgba(200,200,200,0.8);
            padding: 5px 0;
        }
        .pkg-features li .chk { color: var(--gold); font-size: 0.85rem; flex-shrink: 0; }
        .more-features { cursor: pointer; transition: all 0.2s; }
        .more-features:hover .more-link { color: var(--gold-light); }
        .more-link { color: var(--gold-dark); transition: all 0.2s; font-weight: 500; }
        .arrow { display: inline-block; transition: transform 0.2s; }
        .more-features:hover .arrow { transform: translateX(4px); }
        .btn-pkg {
            width: 100%;
            padding: 12px;
            border-radius: 40px;
            border: none;
            font-family: var(--ff-sans);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .3s var(--ease-expo);
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--bg-dark);
            box-shadow: 0 4px 20px rgba(255,215,0,.2);
        }
        .btn-pkg:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255,215,0,.45);
        }

        /* ═══════════════════════════════ PACKAGE MODAL ═══════════════════════════════ */
        .package-modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.92); backdrop-filter: blur(20px);
            z-index: 2000; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .package-modal.active { display: flex; opacity: 1; }
        .package-modal-card {
            background: linear-gradient(135deg, #1A1A1A 0%, #111111 100%);
            border-radius: 32px; max-width: 540px; width: 90%; max-height: 85vh;
            overflow: hidden; transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255,215,0,0.2);
            box-shadow: 0 50px 100px rgba(0,0,0,0.7);
        }
        .package-modal.active .package-modal-card { transform: scale(1); }
        .package-modal-header {
            background: linear-gradient(135deg, #FFD700, #DAA520);
            padding: 22px 28px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .package-modal-title {
            font-family: var(--ff-serif); font-size: 1.6rem; font-weight: 700; color: #0A0A0A; margin: 0;
        }
        .package-modal-close {
            background: rgba(0,0,0,0.2); border: none; width: 38px; height: 38px;
            border-radius: 50%; font-size: 1.3rem; cursor: pointer; color: #0A0A0A; transition: all 0.3s;
        }
        .package-modal-close:hover { background: rgba(0,0,0,0.4); transform: rotate(90deg); }
        .package-modal-body { padding: 28px; overflow-y: auto; max-height: 58vh; }
        .modal-price { font-family: var(--ff-serif); font-size: 2.2rem; font-weight: 700; color: #FFD700; margin-bottom: 14px; }
        .modal-desc { color: #9CA3AF; line-height: 1.65; font-size: 0.9rem; margin-bottom: 22px; }
        .modal-divider { height: 1px; background: linear-gradient(90deg, transparent, #FFD700, transparent); margin: 18px 0; }
        .modal-features-title { color: #FFD700; font-size: 0.9rem; font-weight: 600; letter-spacing: 0.05em; margin-bottom: 18px; }
        .modal-features-list { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .modal-features-list li {
            display: flex; align-items: flex-start; gap: 12px;
            color: #D1D5DB; font-size: 0.85rem; padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .modal-chk { color: #FFD700; font-size: 1rem; flex-shrink: 0; }
        .package-modal-footer { padding: 20px 28px 28px; border-top: 1px solid rgba(255,255,255,0.05); }
        .btn-modal-order {
            width: 100%; background: linear-gradient(135deg, #FFD700, #DAA520); color: #0A0A0A;
            border: none; padding: 15px; border-radius: 40px; font-weight: 700; font-size: 0.9rem;
            cursor: pointer; transition: all 0.3s;
        }
        .btn-modal-order:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,215,0,0.4); }

        /* ═══════════════════════════════ TESTIMONIAL STRIP ═══════════════════════════════ */
        #testi-strip { padding:28px 0;background: var(--bg-elevated);overflow:hidden;border-top:1px solid var(--border);border-bottom:1px solid var(--border);}
        .testi-track { display:flex;gap:0;width:max-content;animation:marquee 28s linear infinite; }
        .testi-item { display:flex;align-items:center;gap:14px;padding:0 40px;white-space:nowrap; }
        .testi-item img { width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--gold); }
        .testi-item p { font-family:var(--ff-serif);font-size:1rem;font-style:italic;color:var(--text-secondary); }
        .testi-item span { font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);font-style:normal;display:block;margin-top:2px; }
        .testi-sep { color:rgba(255,215,0,.3);font-size:1.4rem;align-self:center;padding:0 8px; }
        @keyframes marquee { 0% { transform:translateX(0); } 100% { transform:translateX(-50%); } }

        /* ═══════════════════════════════ WHY US ═══════════════════════════════ */
        #why-us { padding:100px 52px;background: var(--bg-dark);position:relative; }
        .why-inner {
            max-width:1100px;margin:0 auto;
            display:grid;grid-template-columns:1fr 1fr;
            gap:80px;align-items:center;position:relative;z-index:2;
        }
        .why-left .section-label { color:var(--gold); }
        .why-left .section-title { color:var(--text-primary); }
        .why-left p { color:var(--text-secondary);font-size:.95rem;line-height:1.8;margin-top:20px; }
        .why-img-collage { position:relative;height:380px; }
        .why-img { position:absolute;border-radius:16px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.6);border:1px solid var(--border);}
        .why-img img { width:100%;height:100%;object-fit:cover; }
        .wi-1 { width:230px;height:300px;top:0;left:0;transform:rotate(-3deg); }
        .wi-2 { width:200px;height:260px;top:40px;right:0;transform:rotate(2deg); }
        .wi-3 { width:170px;height:200px;bottom:0;left:80px;transform:rotate(1deg); }

        /* ═══════════════════════════════ CONTACT ═══════════════════════════════ */
        #contact {
            padding: 80px 52px;
            background: linear-gradient(135deg, #0A0A0A 0%, #161616 100%);
            position: relative;
        }
        #contact::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #FFD700, transparent);
        }
        .contact-container { max-width: 1400px; margin: 0 auto; }
        .contact-header { text-align: center; margin-bottom: 60px; }
        .section-label-light {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.75rem; font-weight: 500; letter-spacing: 0.2em;
            text-transform: uppercase; color: #FFD700; margin-bottom: 16px;
        }
        .section-label-light::before, .section-label-light::after {
            content: ''; display: block; width: 40px; height: 1px;
            background: linear-gradient(90deg, transparent, #FFD700, transparent);
        }
        .section-title-light {
            font-family: var(--ff-serif); font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 600; color: #FFFFFF; margin-bottom: 16px;
        }
        .section-title-light em { font-style: italic; color: #FFD700; }
        .section-subtitle-light { font-size: 1rem; color: #9CA3AF; max-width: 600px; margin: 0 auto; }
        .contact-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; }
        .contact-cards { display: flex; flex-direction: column; gap: 18px; }
        .contact-card {
            background: #161616; border-radius: 20px; padding: 22px;
            display: flex; align-items: flex-start; gap: 18px;
            transition: all 0.3s ease; border: 1px solid #222;
            position: relative; overflow: hidden;
        }
        .contact-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 3px; height: 100%;
            background: linear-gradient(135deg, #FFD700, #DAA520);
            opacity: 0; transition: opacity 0.3s ease;
        }
        .contact-card:hover { transform: translateX(6px); border-color: rgba(255,215,0,0.25); }
        .contact-card:hover::before { opacity: 1; }
        .contact-card-icon {
            width: 52px; height: 52px;
            background: rgba(255,215,0,0.08); border-radius: 14px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .contact-card:hover .contact-card-icon { transform: scale(1.05); background: rgba(255,215,0,0.15); }
        .contact-card-icon i { font-size: 24px; }
        .contact-card-content { flex: 1; }
        .contact-card-content h4 {
            font-size: .95rem; font-weight: 600; color: #FFD700;
            letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 7px;
        }
        .contact-card-content p { color: #D1D5DB; font-size: 0.88rem; line-height: 1.5; margin-bottom: 7px; }
        .contact-phone { font-size: 1.05rem; font-weight: 600; color: #FFFFFF !important; font-family: monospace; }
        .contact-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.78rem; color: #FFD700; text-decoration: none;
            transition: all 0.3s ease; margin-top: 7px;
        }
        .contact-link:hover { gap: 10px; color: #FFED4A; }
        .contact-link.whatsapp { color: #25D366; }
        .contact-link.whatsapp:hover { color: #20B859; }
        .wa-note { font-size: 0.7rem; color: #9CA3AF; font-style: italic; }
        .social-icons { display: flex; gap: 10px; margin-top: 8px; }
        .social-icon {
            width: 40px; height: 40px;
            background: rgba(255,215,0,0.08); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; text-decoration: none;
            transition: all 0.3s ease; color: #FFD700;
        }
        .social-icon i { font-size: 1.3rem; }
        .social-icon:hover { background: #FFD700; color: #1A1A1A; transform: translateY(-3px); }
        .map-wrapper { background: #161616; border-radius: 22px; overflow: hidden; border: 1px solid #222; }
        .map-container { position: relative; height: 450px; }
        #map { height: 100%; width: 100%; z-index: 1; }
        .map-overlay {
            position: absolute; bottom: 20px; left: 20px; right: 20px; z-index: 10;
            background: rgba(0,0,0,0.78); backdrop-filter: blur(10px);
            border-radius: 12px; padding: 12px 16px;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .map-marker-info { display: flex; align-items: center; gap: 12px; }
        .marker-icon { font-size: 28px; }
        .marker-text { display: flex; flex-direction: column; }
        .marker-text strong { color: #FFD700; font-size: 0.85rem; margin-bottom: 2px; }
        .marker-text span { color: #D1D5DB; font-size: 0.7rem; }
        .map-caption {
            padding: 14px 20px; background: #111; border-top: 1px solid #222;
            font-size: 0.75rem; color: #9CA3AF; display: flex; align-items: center; gap: 8px;
        }

        /* ═══════════════════════════════ FOOTER ═══════════════════════════════ */
        footer { padding:60px 52px 40px;background: var(--bg-dark);text-align:center;border-top:1px solid var(--border);}
        .footer-logo { font-family:var(--ff-serif);font-size:2rem;color:var(--gold);margin-bottom:8px; }
        .footer-logo em { font-style:italic; }
        .footer-copy { font-size:.78rem;color:var(--text-muted); }

        /* ═══════════════════════════════ LOGIN MODAL ═══════════════════════════════ */
        .login-modal {
            display: none;position: fixed;inset: 0;
            background: rgba(0,0,0,0.88);backdrop-filter: blur(18px);
            z-index: 1000;align-items: center;justify-content: center;opacity: 0;transition: opacity 0.3s ease;
        }
        .login-modal.active { display: flex;opacity: 1; }
        .login-modal-card {
            background: linear-gradient(135deg, #1A1A1A, #121212);
            border-radius: 36px; max-width: 440px;width: 90%;padding: 36px 32px;text-align: center;
            transform: scale(0.9);transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255,215,0,0.18);
            box-shadow: 0 40px 80px rgba(0,0,0,0.6);
        }
        .login-modal.active .login-modal-card { transform: scale(1); }
        .login-modal-icon { font-size: 64px;margin-bottom: 20px; }
        .login-modal-title { font-family: var(--ff-serif);font-size: 1.9rem;font-weight: 600;color: var(--text-primary);margin-bottom: 14px; }
        .login-modal-message { color: var(--text-secondary);line-height: 1.65;margin-bottom: 28px;font-size: 0.95rem; }
        .login-modal-buttons { display: flex;gap: 14px;justify-content: center; }
        .btn-modal-login { background: linear-gradient(135deg, var(--gold), var(--gold-dark));color: var(--bg-dark);padding: 12px 28px;border-radius: 40px;text-decoration: none;font-weight: 600;transition: all 0.3s; }
        .btn-modal-login:hover { transform: translateY(-2px);box-shadow: 0 8px 20px rgba(255,215,0,0.4); }
        .btn-modal-register { background: transparent;color: var(--gold);border: 1.5px solid rgba(255,215,0,.3);padding: 12px 28px;border-radius: 40px;text-decoration: none;font-weight: 600;transition: all 0.3s; }
        .btn-modal-register:hover { background: rgba(255,215,0,0.1);border-color: var(--gold); }
        .btn-modal-close { background: transparent;color: var(--text-muted);border: none;font-size: 0.85rem;margin-top: 20px;cursor: pointer;transition: color 0.3s; }
        .btn-modal-close:hover { color: var(--gold); }
        .auto-close-timer { width: 100%;height: 3px;background: var(--border);border-radius: 3px;margin-top: 24px;overflow: hidden; }
        .auto-close-progress { width: 100%;height: 100%;background: linear-gradient(90deg, var(--gold), var(--gold-dark));animation: shrinkProgress 10s linear forwards; }
        @keyframes shrinkProgress { from { width: 100%; } to { width: 0%; } }

        /* ═══════════════════════════════ REVEAL ═══════════════════════════════ */
        .reveal { opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease; }
        .reveal.visible { opacity:1;transform:translateY(0); }

        /* ═══════════════════════════════ RESPONSIVE ═══════════════════════════════ */
        @media (max-width:1100px) {
            .download-inner { grid-template-columns: 1fr; }
            .download-buttons-wrap { justify-content: flex-start; }
        }
        @media (max-width:900px) {
            #hero { grid-template-columns:1fr;padding:120px 24px 60px; }
            .hero-right { display:none; }
            .why-inner { grid-template-columns:1fr; }
            #packages { padding:72px 24px; }
            #contact { padding:60px 24px; }
            .contact-grid { grid-template-columns:1fr; }
            nav { padding:16px 22px; }
            nav.scrolled { padding:12px 22px; }
            .pkg-grid { gap: 20px; }
            .pkg-card-body { padding: 18px; }
            .pkg-name { font-size: 1.2rem; }
            .pkg-price { font-size: 1.3rem; }
            .nav-brand-text h1 { font-size: 1rem; }
            .nav-brand-text p { font-size: 0.45rem; }
            .nav-logo-mark { width: 36px; height: 36px; }
            .nav-links .btn { padding: 6px 12px; font-size: 0.65rem; }
            /* Hide nav download buttons on mobile; show big ones in section */
            .nav-download-group { display: none; }
            .download-section { padding: 40px 24px; }
            .download-info-wrap { flex-direction: column; text-align: center; }
            .download-buttons-wrap { justify-content: center; }
            #why-us { padding: 72px 24px; }
        }
        @media (max-width: 560px) {
            .store-btn { min-width: unset; width: 100%; }
            .download-buttons-wrap { flex-direction: column; width: 100%; }
        }
    </style>
</head>
<body>

<!-- POPUP LOGIN MODAL -->
<div id="loginModal" class="login-modal">
    <div class="login-modal-card">
        <div class="login-modal-icon">🔐</div>
        <h2 class="login-modal-title">Oops! Kamu Belum Login</h2>
        <p class="login-modal-message">Yuk login dulu atau daftar kalau belum punya akun.<br>Cuma butuh 1 menit kok! 😊</p>
        <div class="login-modal-buttons">
            <a href="login.php?role=client" class="btn-modal-login">Login Sekarang</a>
            <a href="register.php" class="btn-modal-register">Daftar Akun</a>
        </div>
        <button class="btn-modal-close" onclick="closeLoginModal()">Tutup</button>
        <div class="auto-close-timer"><div class="auto-close-progress"></div></div>
    </div>
</div>

<!-- NAVBAR -->
<nav id="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-logo-mark">
            <img src="uploads/logo/icon.png" alt="<?= $company_name ?>" onerror="this.style.display='none'">
        </div>
        <div class="nav-brand-text">
            <h1><?= $company_name ?></h1>
            <p>Wedding & Event Organizer</p>
        </div>
    </a>
    <div class="nav-links">
        <!-- Download Buttons di Navbar -->
        <div class="nav-download-group">
            <?php if($apk_exists): ?>
            <a href="<?= $apk_file ?>" download class="nav-dl-btn android" id="downloadApkBtn">
                <i class="fab fa-android"></i> Android
                <span style="font-size:0.55rem;opacity:0.7;">v<?= $apk_version ?></span>
            </a>
            <?php else: ?>
            <span class="nav-dl-btn android" style="opacity:0.5;cursor:not-allowed;">
                <i class="fab fa-android"></i> Android
            </span>
            <?php endif; ?>
            <a href="javascript:void(0)" onclick="showIOSComingSoon()" class="nav-dl-btn ios-btn">
                <i class="fab fa-apple"></i> iOS
                <span class="badge-soon">Soon</span>
            </a>
        </div>

        <?php if (isLoggedIn()): ?>
            <?php if (isEmployee()): ?>
                <a href="employee/dashboard.php" class="btn btn-dark">Dashboard Employee</a>
            <?php else: ?>
                <a href="client/dashboard.php" class="btn btn-gold">Dashboard Client</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        <?php else: ?>
            <a href="login.php?role=client" class="btn btn-gold">Client Portal</a>
            <a href="login.php?role=employee" class="btn btn-dark">Employee Portal</a>
            <a href="register.php" class="btn btn-outline">Daftar</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section id="hero">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="hero-left">
        <div class="hero-badge" id="hero-badge"><span>✨</span><span><?= $company_tagline ?></span></div>
        <p class="hero-eyebrow" id="hero-eye">Siap menikah?</p>
        <h1 class="hero-h1" id="hero-h1">Hari <em>Terindah</em><br>Butuh <em>Perencana</em><br><em>Terbaik</em> 💍</h1>
        <p class="hero-desc" id="hero-desc"><s>Daripada pusing sendiri</s> — serahkan saja pada ahlinya! <?= $company_name ?> siap bikin hari spesialmu jadi momen yang <strong>fantastis dan tak terlupakan.</strong></p>
        <div class="hero-cta" id="hero-cta">
            <a href="#packages" class="btn btn-gold" style="padding:14px 32px;font-size:.85rem;">Lihat Paket ✨</a>
            <a href="javascript:void(0)" onclick="showLoginModal()" class="btn btn-outline" style="padding:14px 28px;font-size:.85rem;">Konsultasi Gratis →</a>
        </div>
        <div class="hero-joke" id="hero-joke">💬 <strong>"Makasih <?= $company_name ?>, nikahan saya berjalan lancar!</strong> Padahal saya pikir bakal banyak drama kayak sinetron. Ternyata enggak!" — <em>Mas Bejo &amp; Mbak Juminten</em></div>
    </div>
    <div class="hero-right">
        <div class="floating-hearts fh-1">💗</div>
        <div class="floating-hearts fh-2">✨</div>
        <div class="floating-hearts fh-3">🌸</div>
        <div class="photo-stack ps-1" id="ps1"><img src="uploads/index2.jpeg" alt="Wedding"></div>
        <div class="photo-stack ps-2" id="ps2"><img src="https://images.unsplash.com/photo-1519741497674-611481863552?w=600&q=80&fit=crop" alt="Wedding"></div>
        <div class="photo-stack ps-3" id="ps3"><img src="uploads/index1.jpeg" alt="Wedding"></div>
        <div class="photo-tag pt-1">✓ 500+ Pasangan Bahagia</div>
        <div class="photo-tag pt-2">❤️ Rated #1 di Garut</div>
    </div>
</section>

<!-- ═══════════════════════════ DOWNLOAD APP SECTION ═══════════════════════════ -->
<section class="download-section" id="download-app">
    <div class="download-inner">
        <div class="download-info-wrap">
            <div class="download-icon-wrap">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <div class="download-text-info">
                <h3>Download <em>Wofice App</em></h3>
                <p>Pesan paket, konsultasi, dan pantau persiapan pernikahan Anda langsung dari smartphone — kapan saja, di mana saja.</p>
            </div>
        </div>

        <div class="download-buttons-wrap">
            <!-- Android Button -->
            <?php if($apk_exists): ?>
            <a href="<?= $apk_file ?>" download class="store-btn android" id="downloadApkLarge">
                <i class="fab fa-android store-icon"></i>
                <div class="store-btn-text">
                    <span class="store-label">Download untuk</span>
                    <span class="store-name">Android</span>
                    <span class="store-ver">v<?= $apk_version ?> · APK</span>
                </div>
            </a>
            <?php else: ?>
            <a href="javascript:void(0)" onclick="alert('⚠️ File APK belum tersedia. Silahkan hubungi admin.')" class="store-btn android" style="opacity:0.45;cursor:not-allowed;">
                <i class="fab fa-android store-icon"></i>
                <div class="store-btn-text">
                    <span class="store-label">Download untuk</span>
                    <span class="store-name">Android</span>
                    <span class="store-ver">Segera Hadir</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- iOS Button (Coming Soon) -->
            <a href="javascript:void(0)" onclick="showIOSComingSoon()" class="store-btn ios-store">
                <span class="coming-ribbon">Soon</span>
                <i class="fab fa-apple store-icon"></i>
                <div class="store-btn-text">
                    <span class="store-label">Download untuk</span>
                    <span class="store-name">iPhone</span>
                    <span class="store-ver">🚀 Dalam Pengembangan</span>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- TESTIMONIAL MARQUEE -->
<div id="testi-strip">
    <div class="testi-track">
        <?php
        $reviews = $pdo->query("SELECT r.*, u.full_name, u.couple_name, p.name as package_name FROM reviews r JOIN users u ON r.client_id = u.id JOIN packages p ON r.package_id = p.id WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT 20")->fetchAll();
        if(count($reviews) > 0):
            foreach($reviews as $rev):
                $rating_stars = str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']);
                $reviewer_name = $rev['couple_name'] ?: $rev['full_name'];
        ?>
        <div class="testi-item">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($reviewer_name) ?>&background=FFD700&color=000" alt="">
            <div>
                <p>"<?= htmlspecialchars(substr($rev['review'], 0, 80)) ?>..."</p>
                <span>⭐ <?= $rating_stars ?> — <?= htmlspecialchars($reviewer_name) ?> (<?= $rev['package_name'] ?>)</span>
            </div>
        </div>
        <span class="testi-sep">✦</span>
        <?php endforeach; else:
            $default_reviews = [
                ['name'=>'Sari & Doni','text'=>'Makasih Prismatic, acaranya lancar jaya!','city'=>'Garut'],
                ['name'=>'Rina & Bagas','text'=>'Vendornya profesional semua, nggak nyesel pilih Prismatic!','city'=>'Bandung'],
                ['name'=>'Dewi & Arif','text'=>'Dekorasinya cantik melebihi ekspektasi!','city'=>'Jakarta'],
                ['name'=>'Ayu & Reza','text'=>'MC-nya lucu, tamu happy semua! Truly Fantastic!','city'=>'Yogyakarta']
            ];
            foreach($default_reviews as $rev): ?>
        <div class="testi-item">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($rev['name']) ?>&background=FFD700&color=000">
            <div>
                <p>"<?= $rev['text'] ?>"</p>
                <span>— <?= $rev['name'] ?>, <?= $rev['city'] ?></span>
            </div>
        </div>
        <span class="testi-sep">✦</span>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ═══════════════════════════ PACKAGES ═══════════════════════════ -->
<section id="packages">
    <div class="pkg-header reveal">
        <div class="section-label">Pilih Paket</div>
        <h2 class="section-title">Paket yang Pas<br>untuk <em>Momen Sakral</em>-mu</h2>
        <p class="pkg-sub">Dari yang ekonomis sampai yang bikin sempurna — semua ada, dan semua worth it! 😎</p>
    </div>
    <div class="pkg-grid">
        <?php
        $all_packages = $pdo->query("SELECT * FROM packages ORDER BY FIELD(slug, 'paket1','paket2','paket3','paket4','silver_indoor','silver_outdoor','gold_indoor','gold_outdoor','diamond_indoor','diamond_outdoor','ruby_outdoor','paket_custom')")->fetchAll();
        // Wedding themed background images for each package card
        $bg_images = [
            'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1519741497674-611481863552?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1555244162-803834f70033?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1606800052052-a08af7148866?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1523438885200-e635ba2c371e?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1470116945706-e6bf5d5a53ca?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1504196606672-aef5c9cefc92?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1439539698758-ba2680ecadb9?w=800&h=500&fit=crop&q=80',
            'https://images.unsplash.com/photo-1518049362265-d5b2a6467637?w=800&h=500&fit=crop&q=80',
        ];
        $emojis = ['🎈','💐','👑','✨','🥈','🥈','🥇','🥇','💎','💎','🔴','🎨'];
        $i = 0;
        foreach($all_packages as $pkg):
            $isFeatured = ($pkg['slug'] === 'paket3' || $pkg['slug'] === 'ruby_outdoor');
            $bg_url = $bg_images[$i] ?? $bg_images[0];
        ?>
        <div class="package-card <?= $isFeatured ? 'featured' : '' ?>">
            <!-- Full card background with blur + saturation -->
            <div class="card-bg-full" style="background-image: url('<?= $bg_url ?>');"></div>
            <div class="card-vignette"></div>
            <div class="card-border-glow"></div>

            <div class="card-content">
                <div class="pkg-card-banner">
                    <img src="<?= $bg_url ?>" alt="<?= htmlspecialchars($pkg['name']) ?>" class="banner-image">
                    <div class="banner-gradient"></div>
                    <?php if($isFeatured): ?><div class="pkg-featured-badge">⭐ Terpopuler</div><?php endif; ?>
                    <span class="pkg-emoji"><?= $emojis[$i] ?? '📦' ?></span>
                </div>
                <div class="pkg-card-body">
                    <h3 class="pkg-name"><?= $pkg['name'] ?></h3>
                    <?php if($pkg['price'] > 0): ?>
                        <p class="pkg-price"><span>Rp </span><?= number_format($pkg['price'], 0, ',', '.') ?></p>
                    <?php else: ?>
                        <p class="pkg-price" style="font-style:italic;">Custom Price</p>
                    <?php endif; ?>
                    <p class="pkg-desc"><?= substr($pkg['description'], 0, 100) ?>...</p>
                    <ul class="pkg-features">
                        <?php $features = explode(',', $pkg['features']); ?>
                        <?php foreach(array_slice($features, 0, 2) as $feature): ?>
                        <li><span class="chk">✦</span><span><?= trim($feature) ?></span></li>
                        <?php endforeach; ?>
                        <?php if(count($features) > 2): ?>
                        <li class="more-features" onclick="showPackageDetail(<?= $pkg['id'] ?>, '<?= addslashes($pkg['name']) ?>', '<?= addslashes($pkg['description']) ?>', <?= $pkg['price'] ?>, `<?= addslashes($pkg['features']) ?>`)">
                            <span class="chk">+</span>
                            <span class="more-link"><?= count($features) - 2 ?> fasilitas lainnya <span class="arrow">→</span></span>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <button onclick="orderPackage(<?= $pkg['id'] ?>, '<?= $pkg['slug'] ?>')" class="btn-pkg">
                        <?= $pkg['slug'] === 'paket_custom' ? 'Buat Paket Custom →' : 'Pesan Sekarang →' ?>
                    </button>
                </div>
            </div>
        </div>
        <?php $i++; endforeach; ?>
    </div>
</section>

<!-- MODAL DETAIL PAKET -->
<div id="packageModal" class="package-modal">
    <div class="package-modal-card">
        <div class="package-modal-header">
            <h3 id="modalPackageName" class="package-modal-title"></h3>
            <button class="package-modal-close" onclick="closePackageModal()">✕</button>
        </div>
        <div class="package-modal-body">
            <div class="modal-price" id="modalPackagePrice"></div>
            <div class="modal-desc" id="modalPackageDesc"></div>
            <div class="modal-divider"></div>
            <h4 class="modal-features-title">✨ Semua Fasilitas Paket:</h4>
            <ul id="modalPackageFeatures" class="modal-features-list"></ul>
        </div>
        <div class="package-modal-footer">
            <button onclick="closePackageModal(); orderPackageFromModal()" class="btn-modal-order">Pesan Paket Ini →</button>
        </div>
    </div>
</div>

<!-- WHY US -->
<section id="why-us">
    <div class="why-inner">
        <div class="why-left reveal">
            <div class="section-label">Kenapa Pilih Kami</div>
            <h2 class="section-title">Bukan Sekadar<br><em>Wedding Organizer</em></h2>
            <p><?= $company_name ?> hadir untuk mewujudkan pernikahan impian Anda dengan sentuhan profesional dan personal. Kami percaya setiap momen berharga layak mendapatkan perencanaan yang sempurna. Dengan pengalaman 8 tahun dan 500+ pasangan bahagia, kami siap menjadi bagian dari hari spesial Anda. 🌸</p>
            <div class="why-stats" style="display:flex;gap:32px;margin-top:36px;flex-wrap:wrap;">
                <div class="why-stat">
                    <div class="why-stat-num" style="font-family:var(--ff-serif);font-size:2.6rem;font-weight:300;color:var(--gold);">500<span>+</span></div>
                    <div class="why-stat-label" style="font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);">Pasangan Bahagia</div>
                </div>
                <div class="why-stat">
                    <div class="why-stat-num" style="font-family:var(--ff-serif);font-size:2.6rem;font-weight:300;color:var(--gold);">8<span>th</span></div>
                    <div class="why-stat-label" style="font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);">Tahun Pengalaman</div>
                </div>
                <div class="why-stat">
                    <div class="why-stat-num" style="font-family:var(--ff-serif);font-size:2.6rem;font-weight:300;color:var(--gold);">100<span>%</span></div>
                    <div class="why-stat-label" style="font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--text-muted);">Puas Terjamin</div>
                </div>
            </div>
        </div>
        <div class="why-img-collage reveal">
            <div class="why-img wi-1"><img src="https://images.unsplash.com/photo-1555244162-803834f70033?w=500&q=80&fit=crop" alt="Dekorasi"></div>
            <div class="why-img wi-2"><img src="https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=500&q=80&fit=crop" alt="Couple"></div>
            <div class="why-img wi-3"><img src="https://images.unsplash.com/photo-1583875762487-5f8f7c718d14?w=500&q=80&fit=crop" alt="Flowers"></div>
        </div>
    </div>
</section>

<!-- CONTACT & MAPS SECTION -->
<section id="contact">
    <div class="contact-container">
        <div class="contact-header">
            <div class="section-label-light">Hubungi Kami</div>
            <h2 class="section-title-light">Siap <em>Mewujudkan</em><br>Hari Spesialmu?</h2>
            <p class="section-subtitle-light">Kami siap membantu Anda 24/7 melalui berbagai platform</p>
        </div>

        <div class="contact-grid">
            <div class="contact-cards">
                <div class="contact-card">
                    <div class="contact-card-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="contact-card-content">
                        <h4>Alamat Operasional</h4>
                        <p><strong><?= nl2br(htmlspecialchars($company_address)) ?></strong></p>
                        <a href="https://maps.google.com/?q=<?= urlencode($company_address) ?>" target="_blank" class="contact-link"><i class="fas fa-external-link-alt"></i> Lihat di Google Maps</a>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card-icon" style="background: rgba(37,211,102,0.12); color: #25D366;">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="contact-card-content">
                        <h4>WhatsApp / Telepon</h4>
                        <p class="contact-phone"><?= $company_phone ?></p>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $company_whatsapp) ?>" target="_blank" class="contact-link whatsapp"><i class="fab fa-whatsapp"></i> Chat via WhatsApp</a>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card-icon" style="background: rgba(255,215,0,0.12); color: #FFD700;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="contact-card-content">
                        <h4>Email</h4>
                        <p><?= $company_email ?></p>
                        <a href="mailto:<?= $company_email ?>" class="contact-link"><i class="fas fa-paper-plane"></i> Kirim Email</a>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card-content">
                        <h4>Media Sosial</h4>
                        <div class="social-icons">
                            <a href="https://instagram.com/<?= $company_instagram ?>" target="_blank" class="social-icon"><i class="fab fa-instagram"></i></a>
                            <a href="https://www.tiktok.com/@prismatic59" target="_blank" class="social-icon"><i class="fab fa-tiktok"></i></a>
                        </div>
                    </div>
                </div>
                <div class="contact-card">
                    <div class="contact-card-icon" style="background: rgba(255,215,0,0.12); color: #FFD700;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="contact-card-content">
                        <h4>Jam Operasional</h4>
                        <p><strong>Senin - Sabtu:</strong> 09:00 - 18:00</p>
                        <p><strong>Minggu & Hari Libur:</strong> Tutup <span class="wa-note">(konsultasi via WA)</span></p>
                    </div>
                </div>
            </div>

            <div class="map-wrapper">
                <div class="map-container" id="mapContainer">
                    <div id="map"></div>
                    <div class="map-overlay">
                        <div class="map-marker-info">
                            <div class="marker-icon">📍</div>
                            <div class="marker-text">
                                <strong><?= $company_name ?></strong>
                                <span><?= nl2br(htmlspecialchars($company_address)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="map-caption">
                    <i class="fas fa-map-pin" style="color:var(--gold);"></i>
                    Lokasi Kantor Operasional: <strong><?= nl2br(htmlspecialchars($company_address)) ?></strong>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-logo"><?= $company_name ?> <em><?= $company_tagline ?></em></div>
    <p class="footer-tagline" style="font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;color:var(--text-muted);margin-bottom:28px;">Wedding & Event Organizer · Garut, Indonesia</p>
    <p class="footer-copy">© <?= date('Y') ?> <?= $company_name ?>. Made with 💕 for every love story. <?= $company_tagline ?></p>
</footer>

<script>
    // Initialize Map
    var map = L.map('map').setView([-7.2358, 107.9023], 14);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
        subdomains: 'abcd', maxZoom: 19
    }).addTo(map);
    var marker = L.marker([-7.2358, 107.9023]).addTo(map);
    marker.bindPopup('<b style="color:#FFD700"><?= $company_name ?></b><br><?= nl2br(htmlspecialchars($company_address)) ?>').openPopup();

    // GSAP Animations
    gsap.registerPlugin(ScrollTrigger);
    const tl = gsap.timeline({ defaults:{ease:'power3.out'} });
    tl.to('#hero-badge',{opacity:1,duration:.7,delay:.2})
      .to('#hero-eye',{opacity:1,y:0,duration:.6},'-=.3')
      .to('#hero-h1',{opacity:1,y:0,duration:.8},'-=.4')
      .to('#hero-desc',{opacity:1,y:0,duration:.6},'-=.5')
      .to('#hero-cta',{opacity:1,duration:.5},'-=.3')
      .to('#hero-joke',{opacity:1,duration:.5},'-=.2')
      .to('#ps1',{opacity:1,x:0,rotation:-4,duration:.9,ease:'back.out(1.4)'},'-=.6')
      .to('#ps2',{opacity:1,x:0,rotation:3,duration:.9,ease:'back.out(1.4)'},'-=.7')
      .to('#ps3',{opacity:1,y:0,rotation:1,duration:.9,ease:'back.out(1.4)'},'-=.7');

    gsap.utils.toArray('.package-card').forEach((card,i)=>{
        gsap.to(card,{opacity:1,y:0,duration:.7,delay:i*.05,ease:'power3.out',
            scrollTrigger:{trigger:card,start:'top 88%'}
        });
    });

    const obs = new IntersectionObserver(entries=>{
        entries.forEach(e=>{
            if(e.isIntersecting){ e.target.classList.add('visible'); obs.unobserve(e.target); }
        });
    },{threshold:.18});
    document.querySelectorAll('.reveal').forEach(el=>obs.observe(el));

    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll',()=>{ navbar.classList.toggle('scrolled', scrollY > 40); });

    let closeTimeout;
    function showLoginModal(){
        const modal = document.getElementById('loginModal');
        modal.classList.add('active');
        if(closeTimeout) clearTimeout(closeTimeout);
        closeTimeout = setTimeout(()=>{ closeLoginModal(); }, 10000);
    }
    function closeLoginModal(){
        const modal = document.getElementById('loginModal');
        modal.classList.remove('active');
        if(closeTimeout) clearTimeout(closeTimeout);
    }
    document.getElementById('loginModal').addEventListener('click', function(e){ if(e.target===this) closeLoginModal(); });

    // iOS Coming Soon
    function showIOSComingSoon() {
        alert('📱 Versi iOS sedang dalam tahap pengembangan!\n\nKami akan segera hadir di App Store. Pantau terus Instagram kami untuk info terbaru ya! 🚀');
    }

    let currentPackageId = null;
    let currentPackageSlug = null;

    function showPackageDetail(id, name, description, price, featuresStr) {
        currentPackageId = id;
        currentPackageSlug = name.toLowerCase().replace(/ /g, '_');
        document.getElementById('modalPackageName').innerHTML = name;
        document.getElementById('modalPackageDesc').innerHTML = description;
        document.getElementById('modalPackagePrice').innerHTML = price > 0
            ? 'Rp ' + new Intl.NumberFormat('id-ID').format(price)
            : 'Custom Price';
        let features = featuresStr.split(',');
        let html = '';
        features.forEach(feature => { html += `<li><span class="modal-chk">✓</span> ${feature.trim()}</li>`; });
        document.getElementById('modalPackageFeatures').innerHTML = html;
        document.getElementById('packageModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closePackageModal() {
        document.getElementById('packageModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function orderPackageFromModal() { if(currentPackageId) orderPackage(currentPackageId, currentPackageSlug); }

    document.getElementById('packageModal').addEventListener('click', function(e){ if(e.target===this) closePackageModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closePackageModal(); closeLoginModal(); } });

    function orderPackage(packageId, slug){
        <?php if(!isLoggedIn()): ?>
            showLoginModal();
        <?php elseif(isEmployee()): ?>
            alert('⚠️ HEY! Kamu adalah EMPLOYEE! Gak bisa pesan paket pakai akun karyawan dong! 😅\nLogin pakai akun client ya!');
            window.location.href = 'logout.php';
        <?php else: ?>
            window.location.href = 'client/order.php?package_id=' + packageId;
        <?php endif; ?>
    }

    // Track download APK event
    document.querySelectorAll('#downloadApkBtn, #downloadApkLarge').forEach(btn => {
        if(btn) {
            btn.addEventListener('click', function() {
                console.log('APK download started - version <?= $apk_version ?>');
            });
        }
    });
</script>
</body>
</html>