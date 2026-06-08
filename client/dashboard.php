<?php
include '../config/database.php';
redirectIfNotLoggedIn();
if (!isClient()) { header('Location: ../login.php?role=client'); exit(); }

$user_id = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user = $user->fetch();

$orders_count = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE client_id = ?");
$orders_count->execute([$user_id]);
$orders_count = $orders_count->fetchColumn();

$total_paid = $pdo->prepare("SELECT SUM(p.amount) FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.client_id = ? AND p.status = 'verified'");
$total_paid->execute([$user_id]);
$total_paid = $total_paid->fetchColumn() ?: 0;

$days_left = '-';
if ($user['wedding_date']) {
    $diff = ceil((strtotime($user['wedding_date']) - time()) / 86400);
    $days_left = $diff > 0 ? $diff : ($diff == 0 ? 0 : abs($diff));
}

$recent_orders = $pdo->prepare("SELECT o.*, p.name as package_name FROM orders o JOIN packages p ON o.package_id = p.id WHERE o.client_id = ? ORDER BY o.created_at DESC LIMIT 3");
$recent_orders->execute([$user_id]);

$show_welcome = !isset($_COOKIE['welcome_shown_' . $user_id]);
if ($show_welcome) {
    setcookie('welcome_shown_' . $user_id, '1', time() + 86400 * 30, '/');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Client - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&family=Caveat:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .font-script { font-family: 'Caveat', cursive; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; background: #0A0A0A; }
        .stat-card { 
            background: #1A1A1A; 
            border: 1px solid #2A2A2A;
            transition: all 0.3s ease; 
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(255,215,0,0.1); border-color: rgba(255,215,0,0.3); }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 20px 32px; } }
        
        .modal-welcome {
            position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(12px);
            z-index: 1000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.4s ease;
        }
        .modal-welcome.active { opacity: 1; visibility: visible; }
        .welcome-card {
            background: #1A1A1A; border-radius: 32px; max-width: 500px; width: 90%;
            padding: 40px 32px; text-align: center; transform: scale(0.9);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid rgba(255,215,0,0.2);
        }
        .modal-welcome.active .welcome-card { transform: scale(1); }
        .welcome-emoji { font-size: 64px; margin-bottom: 16px; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    </style>
</head>
<body class="bg-[#0A0A0A]">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#FFD700]">Halo, <?= $user['full_name'] ?>! 💕</h1>
        <p class="text-[#9CA3AF] mt-1">Selamat datang di portal client Prismatic Organizer</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-2xl p-6">
            <div class="text-3xl mb-2">📋</div>
            <div class="text-2xl font-bold text-white"><?= $orders_count ?></div>
            <div class="text-sm text-[#9CA3AF]">Total Pesanan</div>
        </div>
        <div class="stat-card rounded-2xl p-6">
            <div class="text-3xl mb-2">💰</div>
            <div class="text-2xl font-bold text-[#FFD700]">Rp <?= number_format($total_paid, 0, ',', '.') ?></div>
            <div class="text-sm text-[#9CA3AF]">Total Dibayar</div>
        </div>
        <div class="stat-card rounded-2xl p-6">
            <div class="text-3xl mb-2">📅</div>
            <div class="text-2xl font-bold text-white"><?= $days_left ?></div>
            <div class="text-sm text-[#9CA3AF]">Hari Menuju H-Hari</div>
        </div>
        <div class="stat-card rounded-2xl p-6 bg-gradient-to-r from-[#FFD70010] to-[#DAA52010] border-[#FFD700]/20">
            <div class="text-3xl mb-2">💬</div>
            <div class="text-sm text-[#FFD700] font-medium">Hubungi CS kapan saja!</div>
            <a href="chat.php" class="text-xs text-[#DAA520] mt-1 inline-block hover:text-[#FFD700] transition">Chat sekarang →</a>
        </div>
    </div>
    
    <div class="bg-gradient-to-r from-[#FFD700] to-[#DAA520] rounded-2xl p-6 mb-8 text-[#0A0A0A]">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-[#0A0A0A]/70 text-sm">Upcoming Event</p>
                <p class="text-2xl font-serif font-semibold"><?= $user['couple_name'] ?? $user['full_name'] ?></p>
                <p class="text-[#0A0A0A]/60 text-sm mt-1">📍 <?= $user['venue'] ?? 'Belum diisi' ?></p>
            </div>
            <div class="text-center">
                <p class="text-4xl font-serif font-bold"><?= $days_left ?></p>
                <p class="text-[#0A0A0A]/60 text-xs">Hari lagi!</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-[#0A0A0A]/70">Tanggal Pernikahan</p>
                <p class="font-semibold"><?= $user['wedding_date'] ? date('d F Y', strtotime($user['wedding_date'])) : 'Belum diatur' ?></p>
            </div>
        </div>
    </div>
    
    <div class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-serif font-semibold text-white">📋 Pesanan Terbaru</h2>
            <a href="orders.php" class="text-[#FFD700] text-sm hover:underline">Lihat semua →</a>
        </div>
        
        <?php if($recent_orders->rowCount() == 0): ?>
        <div class="bg-[#1A1A1A] rounded-2xl p-8 text-center border border-[#2A2A2A]">
            <span class="text-5xl mb-3 block">🎁</span>
            <p class="text-[#9CA3AF]">Belum ada pesanan. Yuk, pesan paket pernikahanmu!</p>
            <a href="packages.php" class="inline-block mt-4 bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-6 py-2 rounded-full text-sm hover:shadow-lg transition">Lihat Paket →</a>
        </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php while($order = $recent_orders->fetch()): ?>
                <div class="bg-[#1A1A1A] rounded-2xl p-5 border border-[#2A2A2A] hover:border-[#FFD700]/30 hover:shadow-md transition">
                    <div class="flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <h3 class="font-serif text-lg font-semibold text-white"><?= $order['package_name'] ?></h3>
                            <p class="text-sm text-[#9CA3AF]">Order #<?= $order['order_number'] ?></p>
                            <p class="text-sm text-[#9CA3AF]">📅 <?= date('d F Y', strtotime($order['wedding_date'])) ?></p>
                        </div>
                        <div>
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium 
                                <?= $order['status'] == 'approved' ? 'bg-green-900 text-green-300' : ($order['status'] == 'pending' ? 'bg-yellow-900 text-yellow-300' : 'bg-red-900 text-red-300') ?>">
                                <?= $order['status'] == 'approved' ? '✓ Disetujui' : ($order['status'] == 'pending' ? '⏳ Menunggu' : '✗ Ditolak') ?>
                            </span>
                        </div>
                        <div>
                            <span class="font-semibold text-[#FFD700]">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></span>
                        </div>
                        <div>
                            <a href="orders.php" class="text-[#DAA520] text-sm hover:text-[#FFD700] transition">Detail →</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-[#1A1A1A] rounded-2xl p-5 border border-[#2A2A2A]">
        <div class="flex items-start gap-4">
            <span class="text-3xl">💡</span>
            <div>
                <h3 class="font-semibold text-white">Tips dari Prismatic Organizer</h3>
                <p class="text-sm text-[#9CA3AF] mt-1">Jangan lupa untuk selalu mengecek email dan notifikasi untuk update terbaru dari tim kami. Jika ada pertanyaan, chat CS kapan saja!</p>
            </div>
        </div>
    </div>
</div>

<div id="welcomeModal" class="modal-welcome <?= $show_welcome ? 'active' : '' ?>">
    <div class="welcome-card">
        <div class="welcome-emoji">💕🎉💍</div>
        <h2 class="font-serif text-2xl font-semibold text-white">
            Selamat Datang, <em class="text-[#FFD700]"><?= $user['couple_name'] ?? $user['full_name'] ?></em>!
        </h2>
        <p class="text-[#9CA3AF] mt-3 leading-relaxed">
            Senang banget lihat kamu di sini! Persiapan pernikahan emang bikin pusing, 
            tapi tenang — kamu sekarang punya tim yang siap bantuin <strong>24/7</strong>.
        </p>
        <div class="mt-4 p-3 bg-[#FFD700]/10 rounded-xl text-sm text-[#FFD700] italic">
            💬 <strong>Fun Fact:</strong> Rata-rata pasangan revisi daftar tamu sebanyak 7 kali. Siap-siap ya! 😂
        </div>
        <button onclick="closeWelcome()" class="mt-6 bg-gradient-to-r from-[#FFD700] to-[#DAA520] text-[#0A0A0A] font-bold px-6 py-2 rounded-full hover:shadow-lg transition">
            Lanjut ke Dashboard →
        </button>
    </div>
</div>

<script>
    function closeWelcome() {
        document.getElementById('welcomeModal').classList.remove('active');
    }
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-fixed').classList.toggle('mobile-open');
    }
</script>
</body>
</html>