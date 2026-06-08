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
            transition: all 0.3s ease; 
        }
        .package-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            border-color: rgba(255,215,0,0.3);
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
        $emojis = ['🎈', '💐', '👑', '✨', '🥈', '🥈', '🥇', '🥇', '💎', '💎', '🔴', '🎨'];
        $badges = ['', '', '⭐ Terpopuler', '', '', '', '', '', '', '', '⭐ Ultimate', ''];
        $i = 0;
        foreach($packages as $pkg):
            $isFeatured = ($pkg['slug'] === 'paket3' || $pkg['slug'] === 'ruby_outdoor');
        ?>
        <div class="package-card rounded-2xl overflow-hidden shadow-lg">
            <div class="h-32 bg-gradient-to-r from-[#1A1A1A] to-[#252525] flex items-center justify-center relative">
                <?php if($badges[$i]): ?>
                <div class="absolute top-3 right-3 bg-[#FFD700] text-[#0A0A0A] text-[10px] font-bold px-2 py-1 rounded-full"><?= $badges[$i] ?></div>
                <?php endif; ?>
                <span class="text-6xl"><?= $emojis[$i] ?? '📦' ?></span>
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
                    <?php foreach(array_slice($features, 0, 2) as $feature): ?>
                    <li class="text-sm text-[#9CA3AF] flex items-center gap-2">
                        <span class="text-[#FFD700]">✓</span> <?= trim(substr($feature, 0, 35)) ?>
                    </li>
                    <?php endforeach; ?>
                    <?php if(count($features) > 2): ?>
                    <li class="text-sm text-[#DAA520]">+ <?= count($features) - 2 ?> fasilitas lainnya</li>
                    <?php endif; ?>
                </ul>
                
                <a href="order.php?package_id=<?= $pkg['id'] ?>" 
                   class="block text-center mt-6 bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold py-2.5 rounded-full hover:shadow-lg hover:scale-[1.02] transition-all">
                    <?= $pkg['slug'] == 'paket_custom' ? 'Buat Paket Custom →' : 'Pesan Sekarang →' ?>
                </a>
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