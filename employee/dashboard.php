<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$user_id = $_SESSION['user_id'];

// Log activity
logEmployeeActivity($pdo, 'Melihat Dashboard', 'view', 'dashboard', null, null);

// Stats
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
$pendingClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client' AND status = 'pending'")->fetchColumn();
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating) FROM reviews WHERE status = 'approved'")->fetchColumn();

// Monthly revenue chart
$monthlyRevenue = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, SUM(amount) as total 
    FROM payments 
    WHERE status = 'verified' AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY created_at ASC
")->fetchAll();

// UPCOMING EVENTS - Ambil yang wedding_date > TODAY (belum lewat)
$upcomingEvents = $pdo->query("
    SELECT o.*, u.couple_name, u.full_name, p.name as package_name 
    FROM orders o 
    JOIN users u ON o.client_id = u.id 
    JOIN packages p ON o.package_id = p.id 
    WHERE o.wedding_date > CURDATE() AND o.status = 'approved'
    ORDER BY o.wedding_date ASC 
    LIMIT 10
")->fetchAll();

// Count upcoming events for badge
$upcomingCount = count($upcomingEvents);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Employee - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        .stat-card {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-4px); border-color: #3B82F6; box-shadow: 0 10px 25px -5px rgba(59,130,246,0.2); }
        .glass-card {
            background: rgba(30,41,59,0.6);
            backdrop-filter: blur(10px);
            border: 1px solid #334155;
        }
        .blur-card {
            backdrop-filter: blur(12px);
            background: rgba(30,41,59,0.4);
            border: 1px solid rgba(59,130,246,0.2);
        }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 80px 16px 24px; } }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1E293B; }
        ::-webkit-scrollbar-thumb { background: #3B82F6; border-radius: 4px; }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#60A5FA]">📊 Dashboard Employee</h1>
        <p class="text-[#94A3B8] mt-1">Selamat datang, <?= $_SESSION['user_name'] ?>! Kelola wedding organizer dengan mudah</p>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                    <span class="text-2xl">👥</span>
                </div>
                <span class="text-xs text-green-400 bg-green-500/20 px-2 py-1 rounded-full">+12%</span>
            </div>
            <div class="text-3xl font-bold text-white"><?= $totalClients ?></div>
            <div class="text-sm text-gray-400 mt-1">Total Client</div>
        </div>
        <div class="stat-card rounded-2xl p-5">
            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center mb-3">
                <span class="text-2xl">⏳</span>
            </div>
            <div class="text-3xl font-bold text-yellow-400"><?= $pendingClients ?></div>
            <div class="text-sm text-gray-400 mt-1">Menunggu ACC</div>
        </div>
        <div class="stat-card rounded-2xl p-5">
            <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center mb-3">
                <span class="text-2xl">💳</span>
            </div>
            <div class="text-3xl font-bold text-orange-400"><?= $pendingPayments ?></div>
            <div class="text-sm text-gray-400 mt-1">Verifikasi Pembayaran</div>
        </div>
     
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-blue-500 rounded-full"></span> Revenue Overview (6 Bulan)
            </h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        
        <div class="glass-card rounded-2xl p-5">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full"></span> Kepuasan Client
            </h3>
            <div class="text-center py-4">
                <div class="text-6xl font-bold text-green-400"><?= number_format($avgRating, 1) ?></div>
                <div class="text-yellow-400 text-xl mt-2">
                    <?php for($i=1; $i<=5; $i++): ?>
                    <span><?= $i <= round($avgRating) ? '★' : '☆' ?></span>
                    <?php endfor; ?>
                </div>
                <p class="text-gray-400 text-sm mt-2">Dari <?= $totalClients ?> client</p>
            </div>
            <div class="mt-4 space-y-2">
                <?php
                $ratingStats = $pdo->query("SELECT rating, COUNT(*) as count FROM reviews WHERE status = 'approved' GROUP BY rating ORDER BY rating DESC")->fetchAll();
                $totalRatingCount = array_sum(array_column($ratingStats, 'count'));
                foreach($ratingStats as $rs):
                ?>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-yellow-400 w-12"><?= $rs['rating'] ?> ★</span>
                    <div class="flex-1 h-2 bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-yellow-500 rounded-full" style="width: <?= ($rs['count'] / max($totalRatingCount, 1)) * 100 ?>%"></div>
                    </div>
                    <span class="text-xs text-gray-400"><?= $rs['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Events & Monitoring Message -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Events -->
        <div class="glass-card rounded-2xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-white flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-500 rounded-full"></span> 📅 Upcoming Events
                </h3>
                <?php if($upcomingCount > 0): ?>
                <span class="bg-blue-500/20 text-blue-400 text-xs px-2 py-1 rounded-full"><?= $upcomingCount ?> event</span>
                <?php endif; ?>
            </div>
            <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                <?php foreach($upcomingEvents as $event): 
                    $daysLeft = ceil((strtotime($event['wedding_date']) - time()) / 86400);
                ?>
                <div class="flex items-center justify-between p-3 bg-[#1E293B] rounded-xl hover:bg-[#2D3A5E] transition">
                    <div>
                        <p class="font-medium text-white"><?= htmlspecialchars($event['couple_name'] ?? $event['full_name']) ?></p>
                        <p class="text-xs text-gray-400">📅 <?= date('d F Y', strtotime($event['wedding_date'])) ?></p>
                        <p class="text-xs text-gray-500">📍 <?= htmlspecialchars($event['venue']) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded-full text-xs font-medium">H-<?= $daysLeft ?></span>
                        <p class="text-xs text-gray-500 mt-1"><?= $event['package_name'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if($upcomingCount == 0): ?>
                <div class="text-center text-gray-500 py-8">
                    <span class="text-4xl mb-2 block">🎉</span>
                    <p>Belum ada event mendatang</p>
                    <p class="text-xs mt-1">Event baru akan muncul di sini</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Monitoring Message (Blur Effect) -->
        <div class="blur-card rounded-2xl p-6 flex flex-col items-center justify-center text-center min-h-[280px]">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full blur-2xl"></div>
                        <span class="relative text-6xl mb-4 inline-block animate-pulse">🕵️</span>
                    </div>
                    <h3 class="font-serif text-2xl font-semibold text-white mb-3">👀 PESAN PENTING!</h3>
                <div class="bg-blue-500/10 rounded-xl p-4 border border-blue-500/30">
            <p class="text-gray-300 leading-relaxed">
                Semua <span class="text-blue-400 font-semibold">aktivitas karyawan</span> kepantau real-time ya kak 😌
            </p>
            
            <p class="text-yellow-400 text-sm mt-2">
                ⚠️Mau AFK 2 jam terus bilang “lagi cek data”? Sistem punya pendapat lain😭
            </p>
       
            <div class="mt-4 flex gap-2">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                <span class="text-xs text-gray-400">Monitoring Active • 24/7</span>
            </div>
        </div>
    </div>
</div>

<script>
    // Revenue Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
            datasets: [{
                label: 'Revenue (Juta)',
                data: <?= json_encode(array_map(function($row) { return $row['total'] / 1000000; }, $monthlyRevenue)) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#60A5FA',
                pointBorderColor: '#1E3A5F'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { labels: { color: '#94A3B8' } } },
            scales: { y: { grid: { color: '#334155' }, ticks: { color: '#94A3B8' } }, x: { ticks: { color: '#94A3B8' } } }
        }
    });
    
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>