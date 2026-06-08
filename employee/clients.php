<?php
include '../config/database.php';
include 'log_activity.php';
redirectIfNotLoggedIn();
if (!isEmployee()) { header('Location: ../login.php?role=employee'); exit(); }

$filter = $_GET['filter'] ?? 'all';

// Query dengan ORDER BY created_at DESC (terbaru di atas)
$query = "SELECT * FROM users WHERE role = 'client' ORDER BY created_at DESC";
if($filter == 'active') $query = "SELECT * FROM users WHERE role = 'client' AND status = 'active' ORDER BY created_at DESC";
elseif($filter == 'pending') $query = "SELECT * FROM users WHERE role = 'client' AND status = 'pending' ORDER BY created_at DESC";
elseif($filter == 'blacklisted') $query = "SELECT * FROM users WHERE role = 'client' AND status = 'blacklisted' ORDER BY created_at DESC";

$clients = $pdo->query($query);

// Handle actions dengan LOG
if(isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    $client_name = $pdo->prepare("SELECT full_name, couple_name FROM users WHERE id = ?");
    $client_name->execute([$id]);
    $client = $client_name->fetch();
    $display_name = $client['couple_name'] ?? $client['full_name'];
    
    if($action == 'accept') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
        addNotification($id, 'Akun Disetujui', 'Akun Anda telah disetujui oleh admin. Silakan login!', 'success');
        logEmployeeActivity($pdo, "Menerima client: $display_name (ID: $id)", 'accept', 'client', $id, "Status berubah dari pending menjadi active");
        
    } elseif($action == 'blacklist') {
        $pdo->prepare("UPDATE users SET status = 'blacklisted' WHERE id = ?")->execute([$id]);
        addNotification($id, 'Akun Diblacklist', 'Akun Anda telah diblacklist. Hubungi CS untuk informasi lebih lanjut.', 'error');
        logEmployeeActivity($pdo, "Memblacklist client: $display_name (ID: $id)", 'blacklist', 'client', $id, "Status berubah menjadi blacklisted");
        
    } elseif($action == 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        logEmployeeActivity($pdo, "Menghapus client: $display_name (ID: $id)", 'delete', 'client', $id, "Data client dihapus permanen");
    }
    
    header("Location: clients.php?filter=$filter");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Client - Prismatic Organizer</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'DM Sans', sans-serif; }
        .main-content { margin-left: 280px; padding: 24px; min-height: 100vh; background: #0F172A; }
        
        /* CONTAINER UNTUK TABEL - SCROLLBAR DI DALAM */
        .table-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: auto;
        }
        
        /* Custom scrollbar untuk tabel container */
        .table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #1E293B;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: #3B82F6;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #60A5FA;
        }
        
        .filter-btn.active { 
            background: linear-gradient(135deg, #2563EB, #1D4ED8); 
            color: white; 
            box-shadow: 0 4px 12px rgba(37,99,235,0.3); 
        }
        .filter-btn { 
            background: #1E293B; 
            color: #94A3B8; 
            border: 1px solid #334155; 
            transition: all 0.3s ease; 
        }
        .filter-btn:hover { 
            background: #334155; 
            color: white; 
        }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 80px 16px 24px; }
            .table-container { max-height: calc(100vh - 280px); }
        }
    </style>
</head>
<body class="bg-[#0F172A]">

<div class="flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="main-content flex-1">
        <h1 class="text-2xl font-bold mb-6 text-[#60A5FA]">👥 Manajemen Client</h1>
        
        <!-- Filter Tabs -->
        <div class="flex gap-3 mb-6 flex-wrap">
            <a href="?filter=all" class="filter-btn px-5 py-2 rounded-xl font-medium transition <?= $filter == 'all' ? 'active' : '' ?>">📊 Semua</a>
            <a href="?filter=active" class="filter-btn px-5 py-2 rounded-xl font-medium transition <?= $filter == 'active' ? 'active' : '' ?>">✅ Client Aktif</a>
            <a href="?filter=pending" class="filter-btn px-5 py-2 rounded-xl font-medium transition <?= $filter == 'pending' ? 'active' : '' ?>">⏳ Menunggu ACC</a>
            <a href="?filter=blacklisted" class="filter-btn px-5 py-2 rounded-xl font-medium transition <?= $filter == 'blacklisted' ? 'active' : '' ?>">🚫 Blacklist</a>
        </div>
        
        <!-- Client Table Container dengan Scrollbar di DALAM -->
        <div class="bg-[#1E293B] rounded-2xl border border-[#334155] overflow-hidden">
            <div class="table-container">
                <table class="w-full">
                    <thead class="bg-[#0F172A] border-b border-[#334155] sticky top-0 z-10">
                        <tr class="text-left text-gray-400 text-sm">
                            <th class="p-4">Foto</th>
                            <th>Nama Pasangan</th>
                            <th>Email</th>
                            <th>No HP</th>
                            <th>Tanggal Nikah</th>
                            <th>Tanggal Daftar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        <tr>
                    </thead>
                    <tbody>
                        <?php while($client = $clients->fetch()): ?>
                        <tr class="border-b border-[#334155] hover:bg-[#2D3A5E] transition">
                            <td class="p-4">
                                <img src="../uploads/profiles/<?= $client['profile_picture'] ?>" class="w-10 h-10 rounded-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($client['couple_name'] ?? $client['full_name']) ?>&background=2563EB&color=fff'">
                            </td>
                            <td class="p-4 font-medium text-white"><?= htmlspecialchars($client['couple_name'] ?? $client['full_name']) ?></td>
                            <td class="p-4 text-gray-300"><?= $client['email'] ?></td>
                            <td class="p-4 text-gray-300"><?= $client['phone'] ?></td>
                            <td class="p-4 text-gray-300"><?= $client['wedding_date'] ? date('d/m/Y', strtotime($client['wedding_date'])) : '-' ?></td>
                            <td class="p-4 text-gray-400 text-sm"><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    <?= $client['status'] == 'active' ? 'bg-green-500/20 text-green-400' : ($client['status'] == 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') ?>">
                                    <?= $client['status'] == 'active' ? '✅ Aktif' : ($client['status'] == 'pending' ? '⏳ Menunggu' : '🚫 Blacklist') ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex gap-2">
                                    <?php if($client['status'] == 'pending'): ?>
                                    <a href="?action=accept&id=<?= $client['id'] ?>&filter=<?= $filter ?>" class="bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg text-sm transition" onclick="return confirm('Setujui client <?= addslashes($client['couple_name'] ?? $client['full_name']) ?>?')">✅ Accept</a>
                                    <?php endif; ?>
                                    <?php if($client['status'] != 'blacklisted'): ?>
                                    <a href="?action=blacklist&id=<?= $client['id'] ?>&filter=<?= $filter ?>" class="bg-orange-600 hover:bg-orange-500 text-white px-3 py-1.5 rounded-lg text-sm transition" onclick="return confirm('Blacklist client <?= addslashes($client['couple_name'] ?? $client['full_name']) ?>?')">🚫 Blacklist</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $client['id'] ?>&filter=<?= $filter ?>" class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg text-sm transition" onclick="return confirm('Hapus client <?= addslashes($client['couple_name'] ?? $client['full_name']) ?>? Data akan hilang permanen!')">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Info Total Client -->
        <div class="mt-4 text-center text-gray-500 text-sm">
            Total Client: <?= $clients->rowCount() ?> orang
        </div>
    </main>
</div>

<script>
    function toggleMobileSidebar() {
        document.querySelector('.sidebar-glass').classList.toggle('mobile-open');
    }
</script>
</body>
</html>