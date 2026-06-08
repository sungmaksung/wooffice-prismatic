<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Filter dan sorting
$filter_type = $_GET['type'] ?? 'all';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM employee_activities WHERE 1=1";
if($filter_type != 'all') $query .= " AND action_type = '$filter_type'";
if($filter_date) $query .= " AND DATE(created_at) = '$filter_date'";
if($search) $query .= " AND (action LIKE '%$search%' OR employee_name LIKE '%$search%' OR details LIKE '%$search%')";
$query .= " ORDER BY created_at DESC";

$activities = $pdo->query($query)->fetchAll();

// Statistik
$totalActivities = count($activities);
$actionTypes = $pdo->query("SELECT action_type, COUNT(*) as count FROM employee_activities GROUP BY action_type")->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Aktivitas - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .glass-card { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(139, 92, 246, 0.2); }
        .filter-btn.active { background: linear-gradient(135deg, #8B5CF6, #6D28D9); color: white; }
        .filter-btn { background: #1E293B; color: #94A3B8; border: 1px solid #334155; transition: all 0.3s; }
        .filter-btn:hover { background: #334155; color: white; }
    </style>
</head>
<body class="bg-[#0F172A]">

<?php include 'sidebar.php'; ?>

<div class="admin-main-content">
    <div class="mb-8">
        <h1 class="font-serif text-3xl font-semibold text-[#A78BFA]">📜 Log Aktivitas</h1>
        <p class="text-[#94A3B8] mt-1">Pantau semua aktivitas karyawan dan sistem</p>
    </div>
    
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-purple-400"><?= $totalActivities ?></div>
            <div class="text-xs text-gray-400">Total Aktivitas</div>
        </div>
        <?php foreach($actionTypes as $type): ?>
        <div class="glass-card rounded-xl p-4 text-center">
            <div class="text-xl font-bold text-white"><?= $type['count'] ?></div>
            <div class="text-xs text-gray-400"><?= ucfirst($type['action_type']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
        <a href="?type=all" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'all' ? 'active' : '' ?>">📊 Semua</a>
        <a href="?type=view" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'view' ? 'active' : '' ?>">👁️ View</a>
        <a href="?type=create" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'create' ? 'active' : '' ?>">➕ Create</a>
        <a href="?type=update" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'update' ? 'active' : '' ?>">✏️ Update</a>
        <a href="?type=delete" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'delete' ? 'active' : '' ?>">🗑️ Delete</a>
        <a href="?type=verify" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'verify' ? 'active' : '' ?>">✅ Verify</a>
        <a href="?type=reject" class="filter-btn px-4 py-2 rounded-lg text-sm transition <?= $filter_type == 'reject' ? 'active' : '' ?>">❌ Reject</a>
        <div class="flex-1"></div>
        <form method="GET" class="flex gap-2">
            <input type="date" name="date" value="<?= $filter_date ?>" class="bg-[#1E293B] border border-[#334155] rounded-lg px-3 py-2 text-white text-sm">
            <input type="text" name="search" placeholder="Cari..." value="<?= htmlspecialchars($search) ?>" class="bg-[#1E293B] border border-[#334155] rounded-lg px-3 py-2 text-white text-sm w-48">
            <button type="submit" class="bg-purple-600 px-4 py-2 rounded-lg text-sm">Filter</button>
            <a href="activities.php" class="bg-gray-600 px-4 py-2 rounded-lg text-sm">Reset</a>
        </form>
    </div>
    
    <!-- Activities Table -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#0F172A] border-b border-[#334155]">
                    <tr class="text-left text-gray-400 text-sm">
                        <th class="p-4">ID</th>
                        <th>Karyawan</th>
                        <th>Aksi</th>
                        <th>Target</th>
                        <th>Detail</th>
                        <th>IP Address</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($activities as $act): ?>
                    <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                        <td class="p-4 text-gray-400">#<?= $act['id'] ?></td>
                        <td class="p-4">
                            <p class="font-medium text-white"><?= htmlspecialchars($act['employee_name']) ?></p>
                            <p class="text-xs text-gray-500">ID: <?= $act['employee_id'] ?></p>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                <?= $act['action_type'] == 'create' ? 'bg-green-500/20 text-green-400' : ($act['action_type'] == 'update' ? 'bg-yellow-500/20 text-yellow-400' : ($act['action_type'] == 'delete' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400')) ?>">
                                <?= htmlspecialchars($act['action']) ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-300"><?= ucfirst($act['target_type']) ?> <?= $act['target_id'] ? "#{$act['target_id']}" : '' ?></td>
                        <td class="p-4 text-gray-300 max-w-xs truncate"><?= htmlspecialchars($act['details'] ?? '-') ?></td>
                        <td class="p-4 text-gray-400 text-sm font-mono"><?= $act['ip_address'] ?? '-' ?></td>
                        <td class="p-4 text-gray-400 text-sm"><?= date('d/m/Y H:i:s', strtotime($act['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>