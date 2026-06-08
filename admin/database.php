<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get database size
$dbSize = $pdo->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = 'wo_office'")->fetch();
$dbSizeFormatted = round($dbSize['size'] / 1024 / 1024, 2);

// Get table statistics
$tables = $pdo->query("
    SELECT 
        table_name, 
        table_rows, 
        round(((data_length + index_length) / 1024 / 1024), 2) as size_mb
    FROM information_schema.tables 
    WHERE table_schema = 'wo_office'
    ORDER BY (data_length + index_length) DESC
")->fetchAll();

// Get record counts
$counts = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'employees' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn(),
    'clients' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn(),
    'orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'payments' => $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
    'reviews' => $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'forum_posts' => $pdo->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn(),
    'messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
];

// Get last backup info (coba dengan try-catch)
$lastBackup = null;
try {
    $lastBackup = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 1")->fetch();
} catch(PDOException $e) {
    // Tabel belum ada, abaikan
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Database Stats - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .stat-card { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-4px); border-color: #8B5CF6; }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">🗄️ Database Statistics</h1>
        <p class="text-[#94A3B8] mt-1">Informasi lengkap tentang database sistem</p>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card rounded-2xl p-5">
            <div class="text-3xl mb-2">💾</div>
            <div class="text-2xl font-bold text-purple-400"><?= $dbSizeFormatted ?> MB</div>
            <div class="text-sm text-gray-400">Total Database Size</div>
        </div>
        <div class="stat-card rounded-2xl p-5">
            <div class="text-3xl mb-2">📊</div>
            <div class="text-2xl font-bold text-white"><?= count($tables) ?></div>
            <div class="text-sm text-gray-400">Total Tables</div>
        </div>
        <div class="stat-card rounded-2xl p-5">
            <div class="text-3xl mb-2">📝</div>
            <div class="text-2xl font-bold text-white"><?= number_format(array_sum($counts)) ?></div>
            <div class="text-sm text-gray-400">Total Records</div>
        </div>
        <div class="stat-card rounded-2xl p-5">
            <div class="text-3xl mb-2">💿</div>
            <div class="text-2xl font-bold text-yellow-400"><?= $lastBackup ? date('d/m/Y', strtotime($lastBackup['created_at'])) : 'Belum pernah' ?></div>
            <div class="text-sm text-gray-400">Last Backup</div>
        </div>
    </div>
    
    <!-- Record Counts -->
    <div class="glass-card rounded-2xl p-5 mb-8">
        <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
            <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 📋 Record Counts per Table
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach($counts as $table => $count): ?>
            <div class="bg-[#1E293B] rounded-xl p-3 text-center">
                <div class="text-2xl font-bold text-purple-400"><?= number_format($count) ?></div>
                <div class="text-xs text-gray-400 uppercase"><?= $table ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Table Details -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <h3 class="font-semibold text-white p-5 pb-0 flex items-center gap-2">
            <span class="w-2 h-2 bg-purple-500 rounded-full"></span> 📊 Table Details
        </h3>
        <div class="overflow-x-auto p-5 pt-3">
            <table class="w-full">
                <thead class="border-b border-[#334155]">
                    <tr class="text-left text-gray-400 text-sm">
                        <th class="pb-2">Table Name</th>
                        <th class="pb-2">Rows</th>
                        <th class="pb-2">Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tables as $table): ?>
                    <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                        <td class="py-2 text-white"><?= $table['table_name'] ?></td>
                        <td class="py-2 text-gray-300"><?= number_format($table['table_rows']) ?></td>
                        <td class="py-2 text-gray-300"><?= $table['size_mb'] ?> MB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>