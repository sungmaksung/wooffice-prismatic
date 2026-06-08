<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$packages = $pdo->query("SELECT * FROM packages ORDER BY FIELD(slug, 'paket1','paket2','paket3','paket4','silver_indoor','silver_outdoor','gold_indoor','gold_outdoor','diamond_indoor','diamond_outdoor','ruby_outdoor','paket_custom')")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesan Paket - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .package-card { 
            background: #1A1A1A; 
            border: 1px solid #2A2A2A;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            position: relative;
        }
        .package-card:hover { 
            transform: translateY(-10px) scale(1.02); 
            box-shadow: 0 30px 50px rgba(0,0,0,0.5);
            border-color: rgba(255,215,0,0.4);
        }
        .card-bg-blur {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 140px;
            background-size: cover;
            background-position: center;
            filter: blur(8px);
            transform: scale(1.1);
            opacity: 0.6;
            transition: all 0.4s ease;
            z-index: 0;
        }
        .package-card:hover .card-bg-blur {
            transform: scale(1.2);
            opacity: 0.8;
            filter: blur(6px);
        }
        .card-content {
            position: relative;
            z-index: 2;
            background: linear-gradient(to bottom, rgba(26,26,26,0.8) 0%, #1A1A1A 100%);
        }
        .package-card:hover .card-content {
            background: linear-gradient(to bottom, rgba(26,26,26,0.9) 0%, #1A1A1A 100%);
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1A1A1A; }
        ::-webkit-scrollbar-thumb { background: #FFD700; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#FFD700]">📦 Pilih Paket Wedding</h1>
        <p class="text-[#9CA3AF] mt-1">Pilih paket yang sesuai dengan impian pernikahanmu</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php
        // Background images for each package (unsplash wedding themes)
        $bg_images = [
            'https://images.unsplash.com/photo-1519741497674-611481863552?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1555244162-803834f70033?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1519741497674-611481863552?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1555244162-803834f70033?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1583939003579-730e3918a45a?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?w=400&h=200&fit=crop',
            'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=400&h=200&fit=crop'
        ];
        
        $emojis = ['🎈', '💐', '👑', '✨', '🥈', '🥈', '🥇', '🥇', '💎', '💎', '🔴', '🎨'];
        $badges = ['', '', '⭐ Terpopuler', '', '', '', '', '', '', '', '⭐ Ultimate', ''];
        $i = 0;
        foreach($packages as $pkg):
            $bg_url = $bg_images[$i] ?? $bg_images[0];
        ?>
        <div class="package-card rounded-2xl overflow-hidden shadow-lg">
            <!-- Blur Background Image -->
            <div class="card-bg-blur" style="background-image: url('<?= $bg_url ?>');"></div>
            
            <!-- Card Content -->
            <div class="card-content">
                <div class="h-32 flex items-center justify-center relative">
                    <?php if($badges[$i]): ?>
                    <div class="absolute top-3 right-3 bg-[#FFD700] text-[#0A0A0A] text-[10px] font-bold px-2 py-1 rounded-full z-10"><?= $badges[$i] ?></div>
                    <?php endif; ?>
                    <span class="text-6xl drop-shadow-lg"><?= $emojis[$i] ?? '📦' ?></span>
                </div>
                <div class="p-5">
                    <h3 class="font-serif text-xl font-semibold text-white"><?= $pkg['name'] ?></h3>
                    <?php if($pkg['price'] > 0): ?>
                        <p class="text-2xl font-bold text-[#FFD700] mt-2">Rp <?= number_format($pkg['price'], 0, ',', '.') ?></p>
                    <?php else: ?>
                        <p class="text-2xl font-bold text-[#FFD700] mt-2">Custom Price</p>
                    <?php endif; ?>
                    <p class="text-sm text-[#9CA3AF] mt-2 line-clamp-2"><?= substr($pkg['description'], 0, 80) ?>...</p>
                    
                    <ul class="mt-4 space-y-1">
                        <?php $features = explode(',', $pkg['features']); ?>
                        <?php 
                        $display_features = array_slice($features, 0, 2);
                        foreach($display_features as $feature): 
                        ?>
                        <li class="text-sm text-[#9CA3AF] flex items-start gap-2">
                            <span class="text-[#FFD700] flex-shrink-0 mt-0.5">✦</span> 
                            <span class="line-clamp-1"><?= trim(substr($feature, 0, 45)) ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if(count($features) > 2): ?>
                        <li class="text-sm text-[#DAA520] ml-6">+ <?= count($features) - 2 ?> fasilitas lainnya</li>
                        <?php endif; ?>
                    </ul>
                    
                    <a href="order.php?package_id=<?= $pkg['id'] ?>" 
                       class="block text-center mt-6 bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-2.5 rounded-full hover:shadow-lg hover:scale-[1.02] transition-all">
                        <?= $pkg['slug'] == 'paket_custom' ? 'Buat Paket Custom →' : 'Pesan Sekarang →' ?>
                    </a>
                </div>
            </div>
        </div>
        <?php $i++; endforeach; ?>
    </div>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>
</body>
</html>