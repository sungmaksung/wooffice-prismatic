<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];

// =====================================================
// STATISTIK UTAMA
// =====================================================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn();
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$totalReviews = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating) FROM reviews WHERE status = 'approved'")->fetchColumn();

// =====================================================
// LOGIN STATISTIK (HARI INI)
// =====================================================
$loginAttemptsToday = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE DATE(attempt_time) = CURDATE()")->fetchColumn();
$successLogins = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE DATE(attempt_time) = CURDATE() AND success = 1")->fetchColumn();
$failedLogins = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE DATE(attempt_time) = CURDATE() AND success = 0")->fetchColumn();

// =====================================================
// REVENUE CHART (6 BULAN TERAKHIR)
// =====================================================
$revenueChart = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, SUM(amount) as total 
    FROM payments 
    WHERE status = 'verified' AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at)
    ORDER BY created_at ASC
")->fetchAll();

// =====================================================
// ORDER STATUS CHART
// =====================================================
$orderStatus = $pdo->query("
    SELECT status, COUNT(*) as total 
    FROM orders 
    GROUP BY status
")->fetchAll();

// =====================================================
// AKTIVITAS TERBARU
// =====================================================
$recentActivities = $pdo->query("
    SELECT * FROM employee_activities 
    ORDER BY created_at DESC 
    LIMIT 15
")->fetchAll();

// =====================================================
// LOGIN SESSIONS TERBARU
// =====================================================
$recentSessions = $pdo->query("
    SELECT * FROM user_sessions 
    ORDER BY login_time DESC 
    LIMIT 10
")->fetchAll();

// =====================================================
// PAKET TERLARIS
// =====================================================
$topPackages = $pdo->query("
    SELECT p.name, COUNT(o.id) as total_orders, SUM(o.total_price) as revenue
    FROM packages p
    JOIN orders o ON p.id = o.package_id
    GROUP BY p.id
    ORDER BY total_orders DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .font-serif { font-family: 'Cormorant Garamond', serif; }
        
        /* Glassmorphism Card */
        .glass-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        /* Stat Card */
        .stat-card {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #8B5CF6;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #1E293B; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #8B5CF6; border-radius: 10px; }
        
        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <!-- Welcome Section -->
    <div class="mb-8 animate-fade-in">
        <div class="flex justify-between items-center flex-wrap gap-4">
            <div>
                <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">👑 Admin Dashboard</h1>
                <p class="text-[#94A3B8] mt-1">Selamat datang, <?= htmlspecialchars($admin_name) ?>! Pantau seluruh sistem di sini</p>
            </div>
            <div class="flex gap-2">
                <div class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> System Online
                </div>
                <div class="bg-purple-500/20 text-purple-400 px-3 py-1 rounded-full text-sm">
                    Last update: <?= date('H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.05s">
            <div class="flex items-center justify-between mb-3">
                <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                    <span class="text-2xl">👥</span>
                </div>
                <span class="text-xs text-green-400 bg-green-500/20 px-2 py-1 rounded-full">+12%</span>
            </div>
            <div class="text-3xl font-bold text-white"><?= $totalUsers ?></div>
            <div class="text-sm text-gray-400 mt-1">Total Pengguna</div>
            <div class="flex justify-between mt-3 text-xs">
                <span class="text-blue-400">Karyawan: <?= $totalEmployees ?></span>
                <span class="text-green-400">Client: <?= $totalClients ?></span>
            </div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.1s">
            <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center mb-3">
                <span class="text-2xl">📦</span>
            </div>
            <div class="text-3xl font-bold text-white"><?= $totalOrders ?></div>
            <div class="text-sm text-gray-400 mt-1">Total Pesanan</div>
            <div class="mt-2 text-xs text-gray-500"><?= $pendingPayments ?> pending payment</div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.15s">
            <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center mb-3">
                <span class="text-2xl">💰</span>
            </div>
            <div class="text-sm text-gray-400 mt-1">Total Revenue</div>
            <div class="mt-2 text-xs text-green-400">↑ 23% dari bulan lalu</div>
        </div>
        
        <div class="stat-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.2s">
            <div class="w-12 h-12 bg-pink-500/20 rounded-xl flex items-center justify-center mb-3">
                <span class="text-2xl">⭐</span>
            </div>
            <div class="text-3xl font-bold text-pink-400"><?= number_format($avgRating, 1) ?></div>
            <div class="text-sm text-gray-400 mt-1">Rata-rata Rating</div>
            <div class="mt-2 text-xs text-gray-500">Dari <?= $totalReviews ?> ulasan</div>
        </div>
    </div>
    
    <!-- Login Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="glass-card rounded-2xl p-5 text-center animate-fade-in" style="animation-delay: 0.25s">
            <div class="text-4xl mb-2">🔐</div>
            <div class="text-2xl font-bold text-purple-400"><?= $loginAttemptsToday ?></div>
            <div class="text-sm text-gray-400">Total Percobaan Login (Hari Ini)</div>
        </div>
        <div class="glass-card rounded-2xl p-5 text-center animate-fade-in" style="animation-delay: 0.3s">
            <div class="text-4xl mb-2">✅</div>
            <div class="text-2xl font-bold text-green-400"><?= $successLogins ?></div>
            <div class="text-sm text-gray-400">Login Berhasil</div>
        </div>
        <div class="glass-card rounded-2xl p-5 text-center animate-fade-in" style="animation-delay: 0.35s">
            <div class="text-4xl mb-2">❌</div>
            <div class="text-2xl font-bold text-red-400"><?= $failedLogins ?></div>
            <div class="text-sm text-gray-400">Login Gagal</div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="glass-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.4s">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-purple-500 rounded-full"></span> Revenue Overview (6 Bulan)
            </h3>
            <canvas id="revenueChart" height="250"></canvas>
        </div>
        
        <!-- Order Status Chart -->
        <div class="glass-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.45s">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-purple-500 rounded-full"></span> Status Pesanan
            </h3>
            <canvas id="orderChart" height="250"></canvas>
        </div>
    </div>
    
    <!-- Top Packages & Recent Activities -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Packages -->
        <div class="glass-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.5s">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🏆 Paket Terlaris
            </h3>
            <div class="space-y-3">
                <?php foreach($topPackages as $pkg): ?>
                <div class="flex items-center justify-between p-3 bg-[#1E293B] rounded-xl">
                    <div>
                        <p class="font-medium text-white"><?= htmlspecialchars($pkg['name']) ?></p>
                        <p class="text-xs text-gray-400"><?= $pkg['total_orders'] ?> pesanan</p>
                    </div>
              
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="glass-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.55s">
            <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 📋 Aktivitas Karyawan Terbaru
            </h3>
            <div class="space-y-3 max-h-80 overflow-y-auto pr-2">
                <?php foreach($recentActivities as $act): ?>
                <div class="flex items-start gap-3 p-2 hover:bg-[#2D3A5E] rounded-lg transition">
                    <div class="w-8 h-8 bg-purple-500/20 rounded-full flex items-center justify-center text-sm">
                        <?= substr($act['employee_name'], 0, 1) ?>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-white"><?= htmlspecialchars($act['action']) ?></p>
                        <p class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Login Sessions -->
    <div class="glass-card rounded-2xl p-5 animate-fade-in" style="animation-delay: 0.6s">
        <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
            <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 🔐 Riwayat Login User
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-[#334155]">
                    <tr class="text-left text-gray-400 text-sm">
                        <th class="pb-2">User</th>
                        <th class="pb-2">Role</th>
                        <th class="pb-2">Waktu Login</th>
                        <th class="pb-2">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentSessions as $session): ?>
                    <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                        <td class="py-2 text-white"><?= htmlspecialchars($session['user_name']) ?></td>
                        <td class="py-2">
                            <span class="px-2 py-0.5 rounded-full text-xs <?= $session['user_role'] == 'admin' ? 'bg-purple-500/20 text-purple-400' : ($session['user_role'] == 'employee' ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400') ?>">
                                <?= ucfirst($session['user_role']) ?>
                            </span>
                        </td>
                        <td class="py-2 text-gray-300"><?= date('d/m/Y H:i', strtotime($session['login_time'])) ?></td>
                        <td class="py-2 text-gray-400"><?= $session['ip_address'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Revenue Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($revenueChart, 'month')) ?>,
            datasets: [{
                label: 'Revenue (Juta)',
                data: <?= json_encode(array_map(function($row) { return $row['total'] / 1000000; }, $revenueChart)) ?>,
                borderColor: '#8B5CF6',
                backgroundColor: 'rgba(139,92,246,0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#A78BFA',
                pointBorderColor: '#6D28D9'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { labels: { color: '#94A3B8' } } },
            scales: { y: { grid: { color: '#334155' }, ticks: { color: '#94A3B8' } }, x: { ticks: { color: '#94A3B8' } } }
        }
    });
    
    // Order Status Chart
    const orderCtx = document.getElementById('orderChart').getContext('2d');
    const orderLabels = <?= json_encode(array_column($orderStatus, 'status')) ?>;
    const orderData = <?= json_encode(array_column($orderStatus, 'total')) ?>;
    
    new Chart(orderCtx, {
        type: 'doughnut',
        data: {
            labels: orderLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
            datasets: [{
                data: orderData,
                backgroundColor: ['#22C55E', '#F59E0B', '#EF4444', '#8B5CF6'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { labels: { color: '#94A3B8' } } }
        }
    });
</script>
</body>
</html>